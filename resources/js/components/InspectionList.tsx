import type { Inspection } from '../types';
import { StatusBadge } from './StatusBadge';

interface Props {
    inspections: Inspection[];
    selectedId: number | null;
    onSelect: (id: number) => void;
}

export function InspectionList({ inspections, selectedId, onSelect }: Props) {
    return (
        <table className="w-full text-left text-sm">
            <thead className="border-b border-gray-200 text-xs text-gray-500 uppercase">
                <tr>
                    <th className="px-4 py-2 font-medium">Status</th>
                    <th className="px-4 py-2 font-medium">Registration</th>
                    <th className="px-4 py-2 font-medium">Customer</th>
                </tr>
            </thead>
            <tbody>
                {inspections.map((inspection) => (
                    <tr
                        key={inspection.id}
                        aria-selected={inspection.id === selectedId}
                        className={`border-b border-gray-100 last:border-0 ${
                            inspection.id === selectedId ? 'bg-blue-50' : 'hover:bg-gray-50'
                        }`}
                    >
                        <td className="px-4 py-2">
                            <StatusBadge status={inspection.status} />
                        </td>
                        <td className="px-4 py-2 font-mono">
                            <button
                                type="button"
                                className="cursor-pointer underline-offset-2 hover:underline"
                                onClick={() => onSelect(inspection.id)}
                            >
                                {inspection.vehicle_registration}
                            </button>
                        </td>
                        <td className="px-4 py-2">{inspection.customer_name}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
