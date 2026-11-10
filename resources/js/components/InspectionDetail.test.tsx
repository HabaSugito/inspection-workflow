import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { inspection } from '../test-fixtures';
import type { Inspection } from '../types';
import { InspectionDetail } from './InspectionDetail';

function show(shown: Inspection, actionError: string | null = null) {
    render(
        <InspectionDetail
            inspection={shown}
            pendingAction={false}
            actionError={actionError}
            onAssign={vi.fn()}
            onStart={vi.fn()}
            onComplete={vi.fn()}
        />,
    );
}

/** The value shown next to a label in the detail's list of fields. */
function valueOf(label: string): string | null {
    return screen.getByText(label, { selector: 'dt' }).nextElementSibling?.textContent ?? null;
}

describe('InspectionDetail', () => {
    it('UI-205 shows the customer, registration, requested date, notes, status and inspector', () => {
        show(
            inspection({
                customer_name: 'Jane Smith',
                vehicle_registration: 'AB12 CDE',
                requested_date: '2027-01-15',
                notes: 'Morning visit',
                status: 'assigned',
                inspector_name: 'Sam Carter',
                next_action: 'start',
            }),
        );

        expect(screen.getByRole('heading').textContent).toBe('AB12 CDE');
        expect(valueOf('Customer')).toBe('Jane Smith');
        expect(valueOf('Requested date')).toBe('2027-01-15');
        expect(valueOf('Notes')).toBe('Morning visit');
        expect(valueOf('Status')).toBe('Assigned');
        expect(valueOf('Inspector')).toBe('Sam Carter');
    });

    it('UI-205 shows a dash, not the text "null", for notes and an inspector that are not set', () => {
        show(inspection({ notes: null, inspector_name: null }));

        expect(valueOf('Notes')).toBe('—');
        expect(valueOf('Inspector')).toBe('—');
    });

    it('UI-306 shows a time for each step that was taken, and a dash for each that was not', () => {
        show(
            inspection({
                status: 'in_progress',
                next_action: 'complete',
                assigned_at: '2026-11-10T10:00:00Z',
                started_at: '2026-11-11T11:30:00Z',
                completed_at: null,
            }),
        );

        expect(valueOf('Assigned at')).toContain('2026');
        expect(valueOf('Assigned at')).not.toBe(valueOf('Started at'));
        expect(valueOf('Started at')).toContain('2026');
        expect(valueOf('Completed at')).toBe('—');
        expect(screen.getByText('Times are shown in your local time zone.')).toBeTruthy();
    });

    it('UI-306 shows no step time for a new inspection', () => {
        show(inspection());

        expect(valueOf('Assigned at')).toBe('—');
        expect(valueOf('Started at')).toBe('—');
        expect(valueOf('Completed at')).toBe('—');
    });

    it('UI-305 shows the message of a failed step as an alert, and none when there is no failure', () => {
        show(inspection(), 'This inspection is assigned.');
        expect(screen.getByRole('alert').textContent).toBe('This inspection is assigned.');
    });

    it('UI-305 shows no alert when no step has failed', () => {
        show(inspection());

        expect(screen.queryByRole('alert')).toBeNull();
    });
});
