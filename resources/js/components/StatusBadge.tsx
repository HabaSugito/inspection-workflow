import type { InspectionStatus } from '../types';

const labels: Record<InspectionStatus, string> = {
    pending: 'Pending',
    assigned: 'Assigned',
    in_progress: 'In progress',
    completed: 'Completed',
};

const colours: Record<InspectionStatus, string> = {
    pending: 'bg-gray-200 text-gray-800',
    assigned: 'bg-blue-100 text-blue-800',
    in_progress: 'bg-amber-100 text-amber-800',
    completed: 'bg-green-100 text-green-800',
};

export function StatusBadge({ status }: { status: InspectionStatus }) {
    return (
        <span
            className={`inline-block rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${colours[status]}`}
        >
            {labels[status]}
        </span>
    );
}
