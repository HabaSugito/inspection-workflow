import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    ApiError,
    assignInspector,
    completeInspection,
    createInspection,
    listInspections,
    startInspection,
} from '../api';
import { inspection } from '../test-fixtures';
import type { Inspection } from '../types';
import { InspectionsPage } from './InspectionsPage';

vi.mock('../api', async (original) => ({
    ...(await original<typeof import('../api')>()),
    listInspections: vi.fn(),
    createInspection: vi.fn(),
    assignInspector: vi.fn(),
    startInspection: vi.fn(),
    completeInspection: vi.fn(),
}));

const list = vi.mocked(listInspections);
const create = vi.mocked(createInspection);
const assign = vi.mocked(assignInspector);
const start = vi.mocked(startInspection);
const complete = vi.mocked(completeInspection);

const newest = inspection({ id: 3, vehicle_registration: 'CC33 CCC', customer_name: 'Carol' });
const middle = inspection({
    id: 2,
    vehicle_registration: 'BB22 BBB',
    customer_name: 'Bob',
    status: 'assigned',
    inspector_name: 'Sam Carter',
    next_action: 'start',
});
const oldest = inspection({ id: 1, vehicle_registration: 'AA11 AAA', customer_name: 'Alice' });

function rows() {
    return screen.getAllByRole('row').slice(1);
}

function rowText(index: number) {
    return rows()[index].textContent;
}

/** The right panel: the detail of one inspection, the form, or a hint. */
function panel() {
    return within(screen.getByRole('region', { name: 'Selected inspection' }));
}

function listPanel() {
    return within(screen.getByRole('region', { name: 'Inspection list' }));
}

async function loaded() {
    render(<InspectionsPage />);
    await screen.findByRole('table');
}

beforeEach(() => {
    for (const mock of [list, create, assign, start, complete]) {
        mock.mockReset();
    }

    list.mockResolvedValue([newest, middle, oldest]);
});

describe('InspectionsPage', () => {
    it('UI-202 says that the list is loading until the answer arrives', async () => {
        let answer: (value: Inspection[]) => void = () => undefined;
        list.mockReturnValue(new Promise<Inspection[]>((resolve) => (answer = resolve)));

        render(<InspectionsPage />);

        expect(screen.getByText('Loading inspections…')).toBeTruthy();
        expect(screen.queryByRole('table')).toBeNull();

        answer([newest]);

        expect(await screen.findByRole('table')).toBeTruthy();
        expect(listPanel().getByText('Carol')).toBeTruthy();
        expect(screen.queryByText('Loading inspections…')).toBeNull();
    });

    it('UI-203 says that there are no inspections, and invites the user to create one', async () => {
        list.mockResolvedValue([]);

        render(<InspectionsPage />);

        expect(await listPanel().findByText('No inspections yet.')).toBeTruthy();
        expect(
            panel().getByText('No inspection selected. Create one with New inspection.'),
        ).toBeTruthy();
        expect(screen.queryByRole('table')).toBeNull();
    });

    it('UI-204 shows the error when the list cannot be loaded', async () => {
        list.mockRejectedValue(new ApiError(500, 'Server Error'));

        render(<InspectionsPage />);

        expect((await listPanel().findByRole('alert')).textContent).toBe(
            'Could not load inspections. Server Error',
        );
        expect(screen.queryByRole('table')).toBeNull();
    });

    it('UI-408 selects the newest inspection once the list has loaded, with its next step on screen', async () => {
        await loaded();

        expect(panel().getByRole('heading').textContent).toBe('CC33 CCC');
        expect(panel().getByRole('button', { name: 'Assign inspector' })).toBeTruthy();
        expect(
            screen.getByRole('button', { name: 'View CC33 CCC' }).getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('UI-205 shows the inspection of the row that was selected', async () => {
        await loaded();

        await userEvent.click(listPanel().getByText('Bob'));

        expect(panel().getByRole('heading').textContent).toBe('BB22 BBB');
        expect(panel().getByText('Sam Carter')).toBeTruthy();
        expect(panel().getByRole('button', { name: 'Start inspection' })).toBeTruthy();
        expect(panel().queryByRole('button', { name: 'Assign inspector' })).toBeNull();
    });

    it('UI-402 puts a created inspection first in the list, selects it, and closes the form', async () => {
        const created = inspection({
            id: 4,
            vehicle_registration: 'DD44 DDD',
            customer_name: 'Dave',
        });
        create.mockResolvedValue(created);
        await loaded();

        await userEvent.click(screen.getByRole('button', { name: 'New inspection' }));
        expect(panel().getByRole('heading').textContent).toBe('New inspection');
        await userEvent.type(panel().getByLabelText('Customer name'), 'Dave');
        await userEvent.click(panel().getByRole('button', { name: 'Create inspection' }));

        expect(await panel().findByText('DD44 DDD')).toBeTruthy();
        expect(rows()).toHaveLength(4);
        expect(rowText(0)).toContain('DD44 DDD');
        expect(rowText(1)).toContain('CC33 CCC');
        expect(panel().queryByLabelText('Customer name')).toBeNull();
        expect(
            screen.getByRole('button', { name: 'View DD44 DDD' }).getAttribute('aria-pressed'),
        ).toBe('true');
        expect(list).toHaveBeenCalledTimes(1);
    });

    it('UI-406 shows the inspection that was selected before when the form is cancelled', async () => {
        await loaded();
        await userEvent.click(listPanel().getByText('Bob'));

        await userEvent.click(screen.getByRole('button', { name: 'New inspection' }));
        await userEvent.type(panel().getByLabelText('Customer name'), 'Not kept');
        await userEvent.click(panel().getByRole('button', { name: 'Cancel' }));

        expect(panel().getByRole('heading').textContent).toBe('BB22 BBB');
        expect(rows()).toHaveLength(3);
        expect(create).not.toHaveBeenCalled();

        await userEvent.click(screen.getByRole('button', { name: 'New inspection' }));
        expect((panel().getByLabelText('Customer name') as HTMLInputElement).value).toBe('');
    });

    it('UI-304 shows the new status in the row and in the detail after a step, and offers the next one', async () => {
        assign.mockResolvedValue({
            ...newest,
            status: 'assigned',
            inspector_name: 'Sam Carter',
            next_action: 'start',
        });
        await loaded();

        await userEvent.type(panel().getByLabelText('Inspector name'), 'Sam Carter');
        await userEvent.click(panel().getByRole('button', { name: 'Assign inspector' }));

        expect(await panel().findByRole('button', { name: 'Start inspection' })).toBeTruthy();
        expect(assign).toHaveBeenCalledWith(3, 'Sam Carter');
        expect(rowText(0)).toContain('Assigned');
        expect(rowText(2)).toContain('Pending');
        expect(panel().queryByRole('alert')).toBeNull();
    });

    it('UI-304 sends each step to the inspection that is selected', async () => {
        start.mockResolvedValue({ ...middle, status: 'in_progress', next_action: 'complete' });
        complete.mockResolvedValue({ ...middle, status: 'completed', next_action: null });
        await loaded();
        await userEvent.click(listPanel().getByText('Bob'));

        await userEvent.click(panel().getByRole('button', { name: 'Start inspection' }));
        await userEvent.click(await panel().findByRole('button', { name: 'Complete inspection' }));

        await vi.waitFor(() => expect(rowText(1)).toContain('Completed'));
        expect(start).toHaveBeenCalledWith(2);
        expect(complete).toHaveBeenCalledWith(2);
        expect(panel().queryAllByRole('button')).toHaveLength(0);
    });

    it('UI-305 shows the refusal and reloads the list, so the real status is on screen', async () => {
        const assignedElsewhere = {
            ...newest,
            status: 'assigned' as const,
            inspector_name: 'Other Tab',
            next_action: 'start' as const,
        };
        assign.mockRejectedValue(
            new ApiError(409, 'This inspection is assigned. The only action allowed is: start.'),
        );
        await loaded();
        list.mockResolvedValue([assignedElsewhere, middle, oldest]);

        await userEvent.type(panel().getByLabelText('Inspector name'), 'Late');
        await userEvent.click(panel().getByRole('button', { name: 'Assign inspector' }));

        expect((await panel().findByRole('alert')).textContent).toBe(
            'This inspection is assigned. The only action allowed is: start.',
        );
        expect(list).toHaveBeenCalledTimes(2);
        expect(rowText(0)).toContain('Assigned');
        expect(panel().getByText('Other Tab')).toBeTruthy();
        expect(panel().getByRole('button', { name: 'Start inspection' })).toBeTruthy();
    });

    it('UI-305 says so when the list cannot be reloaded after a refusal', async () => {
        assign.mockRejectedValue(new ApiError(409, 'Refused.'));
        await loaded();
        list.mockRejectedValue(new ApiError(0, 'Could not reach the server.'));

        await userEvent.type(panel().getByLabelText('Inspector name'), 'Late');
        await userEvent.click(panel().getByRole('button', { name: 'Assign inspector' }));

        expect((await panel().findByRole('alert')).textContent).toBe(
            'Refused. The list could not be refreshed, so it may be out of date.',
        );
    });

    it('UI-305 does not show the refusal of one inspection under another', async () => {
        assign.mockRejectedValue(new ApiError(409, 'Refused.'));
        await loaded();

        await userEvent.type(panel().getByLabelText('Inspector name'), 'Late');
        await userEvent.click(panel().getByRole('button', { name: 'Assign inspector' }));
        await panel().findByRole('alert');
        await userEvent.click(listPanel().getByText('Alice'));

        expect(panel().getByRole('heading').textContent).toBe('AA11 AAA');
        expect(panel().queryByRole('alert')).toBeNull();
    });

    it('UI-305 does not show a late refusal under the inspection that was selected meanwhile', async () => {
        let refuse: (error: ApiError) => void = () => undefined;
        assign.mockReturnValue(new Promise<Inspection>((_, reject) => (refuse = reject)));
        await loaded();

        await userEvent.type(panel().getByLabelText('Inspector name'), 'Late');
        await userEvent.click(panel().getByRole('button', { name: 'Assign inspector' }));
        await userEvent.click(listPanel().getByText('Alice'));
        refuse(new ApiError(409, 'Refused.'));
        await vi.waitFor(() => expect(list).toHaveBeenCalledTimes(2));

        expect(panel().getByRole('heading').textContent).toBe('AA11 AAA');
        expect(panel().queryByRole('alert')).toBeNull();
    });
});
