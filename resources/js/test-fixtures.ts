import type { Inspection } from './types';

export function inspection(overrides: Partial<Inspection> = {}): Inspection {
    return {
        id: 1,
        customer_name: 'Jane Smith',
        vehicle_registration: 'AB12 CDE',
        requested_date: '2027-01-15',
        notes: null,
        status: 'pending',
        inspector_name: null,
        next_action: 'assign',
        assigned_at: null,
        started_at: null,
        completed_at: null,
        created_at: '2026-11-10T09:00:00Z',
        updated_at: '2026-11-10T09:00:00Z',
        ...overrides,
    };
}
