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
                    <th className="px-4 py-2">
                        <span className="sr-only">Open</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                {inspections.map((inspection) => (
                    <tr
                        key={inspection.id}
                        onClick={() => onSelect(inspection.id)}
                        className={`cursor-pointer border-b border-gray-100 last:border-0 ${
                            inspection.id === selectedId ? 'bg-blue-50' : 'hover:bg-gray-50'
                        }`}
                    >
                        <td className="px-4 py-2">
                            <StatusBadge status={inspection.status} />
                        </td>
                        <td className="px-4 py-2 font-mono">{inspection.vehicle_registration}</td>
                        <td className="px-4 py-2">{inspection.customer_name}</td>
                        <td className="px-4 py-2 text-right">
                            {/* The row's own click handler does the selecting. */}
                            <button
                                type="button"
                                aria-pressed={inspection.id === selectedId}
                                aria-label={`View ${inspection.vehicle_registration}`}
                                className="cursor-pointer rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium hover:bg-gray-50"
                            >
                                View
                            </button>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
