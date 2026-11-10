export type InspectionStatus = 'pending' | 'assigned' | 'in_progress' | 'completed';

export interface Inspection {
    id: number;
    customer_name: string;
    vehicle_registration: string;
    requested_date: string;
    notes: string | null;
    status: InspectionStatus;
    inspector_name: string | null;
    created_at: string;
    updated_at: string;
}
