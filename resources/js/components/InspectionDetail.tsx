import type { ReactNode } from 'react';
import type { Inspection } from '../types';
import { StatusBadge } from './StatusBadge';

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-3 gap-2 py-2">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="col-span-2 text-sm">{children}</dd>
        </div>
    );
}

export function InspectionDetail({ inspection }: { inspection: Inspection }) {
    return (
        <div>
            <h2 className="font-mono text-lg font-semibold">{inspection.vehicle_registration}</h2>
            <dl className="mt-2 divide-y divide-gray-100">
                <Field label="Status">
                    <StatusBadge status={inspection.status} />
                </Field>
                <Field label="Customer">{inspection.customer_name}</Field>
                <Field label="Requested date">{inspection.requested_date}</Field>
                <Field label="Inspector">{inspection.inspector_name ?? '—'}</Field>
                <Field label="Notes">{inspection.notes ?? '—'}</Field>
            </dl>
        </div>
    );
}
