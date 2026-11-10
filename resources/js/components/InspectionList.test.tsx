import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { inspection } from '../test-fixtures';
import { InspectionList } from './InspectionList';

const inspections = [
    inspection({
        id: 3,
        vehicle_registration: 'CC33 CCC',
        customer_name: 'Carol',
        status: 'in_progress',
    }),
    inspection({
        id: 2,
        vehicle_registration: 'BB22 BBB',
        customer_name: 'Bob',
        status: 'assigned',
    }),
    inspection({
        id: 1,
        vehicle_registration: 'AA11 AAA',
        customer_name: 'Alice',
        status: 'pending',
    }),
];

function dataRows() {
    return screen.getAllByRole('row').slice(1);
}

describe('InspectionList', () => {
    it('UI-201 shows the status, registration and customer of each inspection, in the order given', () => {
        render(<InspectionList inspections={inspections} selectedId={null} onSelect={vi.fn()} />);

        const rows = dataRows();

        expect(rows).toHaveLength(3);
        expect(within(rows[0]).getByText('In progress')).toBeTruthy();
        expect(within(rows[0]).getByText('CC33 CCC')).toBeTruthy();
        expect(within(rows[0]).getByText('Carol')).toBeTruthy();
        expect(within(rows[2]).getByText('Pending')).toBeTruthy();
        expect(within(rows[2]).getByText('Alice')).toBeTruthy();
    });

    it('UI-409 selects the inspection of the row that was clicked, wherever the click lands', async () => {
        const onSelect = vi.fn();
        render(<InspectionList inspections={inspections} selectedId={null} onSelect={onSelect} />);

        await userEvent.click(screen.getByText('Bob'));

        expect(onSelect).toHaveBeenCalledTimes(1);
        expect(onSelect).toHaveBeenCalledWith(2);
    });

    it('UI-410 selects with the keyboard alone: Tab to a View button, then Enter', async () => {
        const onSelect = vi.fn();
        render(<InspectionList inspections={inspections} selectedId={null} onSelect={onSelect} />);

        await userEvent.tab();
        await userEvent.tab();
        expect(document.activeElement).toBe(screen.getByRole('button', { name: 'View BB22 BBB' }));
        await userEvent.keyboard('{Enter}');

        expect(onSelect).toHaveBeenCalledTimes(1);
        expect(onSelect).toHaveBeenCalledWith(2);
    });

    it('UI-410 marks only the selected inspection as pressed', () => {
        render(<InspectionList inspections={inspections} selectedId={2} onSelect={vi.fn()} />);

        const pressed = screen
            .getAllByRole('button')
            .map(
                (button) =>
                    `${button.getAttribute('aria-label')}=${button.getAttribute('aria-pressed')}`,
            );

        expect(pressed).toEqual([
            'View CC33 CCC=false',
            'View BB22 BBB=true',
            'View AA11 AAA=false',
        ]);
    });
});
