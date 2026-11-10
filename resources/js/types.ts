export type InspectionStatus = 'pending' | 'assigned' | 'in_progress' | 'completed';

export type NextAction = 'assign' | 'start' | 'complete';

export interface NewInspection {
    customer_name: string;
    vehicle_registration: string;
    requested_date: string;
    notes: string;
}

export interface Inspection {
    id: number;
    customer_name: string;
    vehicle_registration: string;
    requested_date: string;
    notes: string | null;
    status: InspectionStatus;
    inspector_name: string | null;
    next_action: NextAction | null;
    assigned_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
    updated_at: string;
}
