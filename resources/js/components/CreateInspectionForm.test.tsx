import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, createInspection } from '../api';
import { inspection } from '../test-fixtures';
import type { Inspection } from '../types';
import { CreateInspectionForm } from './CreateInspectionForm';

vi.mock('../api', async (original) => ({
    ...(await original<typeof import('../api')>()),
    createInspection: vi.fn(),
}));

const create = vi.mocked(createInspection);

function setup() {
    const onCreated = vi.fn();
    const onCancel = vi.fn();
    render(<CreateInspectionForm onCreated={onCreated} onCancel={onCancel} />);

    return { onCreated, onCancel };
}

function field(label: string) {
    return screen.getByLabelText(label) as HTMLInputElement;
}

async function fillIn() {
    await userEvent.type(field('Customer name'), 'Jane Smith');
    await userEvent.type(field('Vehicle registration'), 'ab12 cde');
    await userEvent.type(field('Requested date'), '2027-01-15');
    await userEvent.type(field('Notes (optional)'), 'Morning visit');
}

function submitButton() {
    return screen.getByRole('button', { name: 'Create inspection' }) as HTMLButtonElement;
}

beforeEach(() => {
    create.mockReset();
});

describe('CreateInspectionForm', () => {
    it('UI-401 shows the four fields and the two buttons', () => {
        setup();

        expect(field('Customer name').type).toBe('text');
        expect(field('Vehicle registration').type).toBe('text');
        expect(field('Requested date').type).toBe('date');
        expect(field('Notes (optional)').tagName).toBe('TEXTAREA');
        expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual([
            'Create inspection',
            'Cancel',
        ]);
    });

    it('UI-402 sends what was typed and hands on the inspection the server returned', async () => {
        const stored = inspection({ id: 9, vehicle_registration: 'AB12 CDE' });
        create.mockResolvedValue(stored);
        const { onCreated } = setup();

        await fillIn();
        await userEvent.click(submitButton());

        expect(create).toHaveBeenCalledTimes(1);
        expect(create).toHaveBeenCalledWith({
            customer_name: 'Jane Smith',
            vehicle_registration: 'ab12 cde',
            requested_date: '2027-01-15',
            notes: 'Morning visit',
        });
        expect(onCreated).toHaveBeenCalledTimes(1);
        expect(onCreated).toHaveBeenCalledWith(stored);
    });

    it('OQ-403 sends an empty form to the server, and does not decide in the page that it is invalid', async () => {
        create.mockResolvedValue(inspection());
        setup();

        await userEvent.click(submitButton());

        expect(create).toHaveBeenCalledWith({
            customer_name: '',
            vehicle_registration: '',
            requested_date: '',
            notes: '',
        });
    });

    it('UI-404 shows each refusal under its own field, keeps what was typed, and creates nothing', async () => {
        create.mockRejectedValue(
            new ApiError(422, 'The requested date field must be a date after or equal to today.', {
                requested_date: [
                    'The requested date field must be a date after or equal to today.',
                ],
                customer_name: ['The customer name field must not be greater than 255 characters.'],
            }),
        );
        const { onCreated } = setup();

        await fillIn();
        await userEvent.click(submitButton());

        const dateError = await screen.findByText(
            'The requested date field must be a date after or equal to today.',
        );
        const nameError = screen.getByText(
            'The customer name field must not be greater than 255 characters.',
        );
        expect(field('Requested date').getAttribute('aria-describedby')).toBe(dateError.id);
        expect(field('Requested date').getAttribute('aria-invalid')).toBe('true');
        expect(field('Customer name').getAttribute('aria-describedby')).toBe(nameError.id);
        expect(field('Vehicle registration').getAttribute('aria-invalid')).toBe('false');
        expect(field('Vehicle registration').getAttribute('aria-describedby')).toBeNull();
        expect(screen.queryByRole('alert')).toBeNull();

        expect(field('Customer name').value).toBe('Jane Smith');
        expect(field('Vehicle registration').value).toBe('ab12 cde');
        expect(field('Requested date').value).toBe('2027-01-15');
        expect(field('Notes (optional)').value).toBe('Morning visit');
        expect(onCreated).not.toHaveBeenCalled();
        expect(submitButton().disabled).toBe(false);
    });

    it('UI-404 removes an old refusal when the next attempt succeeds for that field', async () => {
        create.mockRejectedValueOnce(
            new ApiError(422, 'The customer name field is required.', {
                customer_name: ['The customer name field is required.'],
            }),
        );
        create.mockRejectedValueOnce(
            new ApiError(422, 'The requested date field is required.', {
                requested_date: ['The requested date field is required.'],
            }),
        );
        setup();

        await userEvent.click(submitButton());
        await screen.findByText('The customer name field is required.');
        await userEvent.click(submitButton());
        await screen.findByText('The requested date field is required.');

        expect(screen.queryByText('The customer name field is required.')).toBeNull();
    });

    it('UI-405 shows one message for a failure that is not about a field, and keeps what was typed', async () => {
        create.mockRejectedValue(new ApiError(0, 'Could not reach the server.'));
        const { onCreated } = setup();

        await fillIn();
        await userEvent.click(submitButton());

        expect((await screen.findByRole('alert')).textContent).toBe('Could not reach the server.');
        expect(field('Customer name').value).toBe('Jane Smith');
        expect(field('Requested date').value).toBe('2027-01-15');
        expect(onCreated).not.toHaveBeenCalled();
        expect(submitButton().disabled).toBe(false);
    });

    it('UI-405 shows a refusal for a field the form does not have, so that no error is lost', async () => {
        create.mockRejectedValue(
            new ApiError(422, 'The status field is prohibited.', {
                status: ['The status field is prohibited.'],
            }),
        );
        setup();

        await userEvent.click(submitButton());

        expect((await screen.findByRole('alert')).textContent).toBe(
            'The status field is prohibited.',
        );
    });

    it('UI-405 removes the message of an earlier failure when the next attempt is refused for a field', async () => {
        create.mockRejectedValueOnce(new ApiError(0, 'Could not reach the server.'));
        create.mockRejectedValueOnce(
            new ApiError(422, 'The customer name field is required.', {
                customer_name: ['The customer name field is required.'],
            }),
        );
        setup();

        await userEvent.click(submitButton());
        await screen.findByRole('alert');
        await userEvent.click(submitButton());
        await screen.findByText('The customer name field is required.');

        expect(screen.queryByRole('alert')).toBeNull();
    });

    it('UI-406 cancels without sending anything', async () => {
        const { onCancel, onCreated } = setup();

        await fillIn();
        await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(onCancel).toHaveBeenCalledTimes(1);
        expect(create).not.toHaveBeenCalled();
        expect(onCreated).not.toHaveBeenCalled();
    });

    it('UI-407 sends one request when the form is submitted twice before the first answer', async () => {
        let answer: (value: Inspection) => void = () => undefined;
        create.mockReturnValue(new Promise<Inspection>((resolve) => (answer = resolve)));
        const { onCreated } = setup();
        await fillIn();
        const form = submitButton().closest('form') as HTMLFormElement;

        // Two submit events in the same tick: the second arrives before React
        // has rendered the disabled button.
        form.requestSubmit();
        form.requestSubmit();

        expect(create).toHaveBeenCalledTimes(1);
        await vi.waitFor(() => expect(submitButton().disabled).toBe(true));
        expect((screen.getByRole('button', { name: 'Cancel' }) as HTMLButtonElement).disabled).toBe(
            true,
        );
        expect(field('Customer name').disabled).toBe(true);

        await userEvent.click(submitButton());
        expect(create).toHaveBeenCalledTimes(1);

        answer(inspection({ id: 9 }));
        await vi.waitFor(() => expect(onCreated).toHaveBeenCalledTimes(1));
    });
});
