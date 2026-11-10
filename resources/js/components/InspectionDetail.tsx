import type { ReactNode } from 'react';
import type { Inspection } from '../types';
import { NextActionControl } from './NextActionControl';
import { StatusBadge } from './StatusBadge';

interface Props {
    inspection: Inspection;
    pendingAction: boolean;
    actionError: string | null;
    onAssign: (inspectorName: string) => void;
    onStart: () => void;
    onComplete: () => void;
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-3 gap-2 py-2">
            <dt className="text-sm text-gray-500">{label}</dt>
            <dd className="col-span-2 text-sm">{children}</dd>
        </div>
    );
}

function formatTime(value: string | null): string {
    return value === null ? '—' : new Date(value).toLocaleString();
}

export function InspectionDetail({
    inspection,
    pendingAction,
    actionError,
    onAssign,
    onStart,
    onComplete,
}: Props) {
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
                <Field label="Assigned">{formatTime(inspection.assigned_at)}</Field>
                <Field label="Started">{formatTime(inspection.started_at)}</Field>
                <Field label="Completed">{formatTime(inspection.completed_at)}</Field>
            </dl>
            <div className="mt-4 space-y-3">
                {actionError !== null && (
                    <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">
                        {actionError}
                    </p>
                )}
                <NextActionControl
                    key={inspection.id}
                    action={inspection.next_action}
                    disabled={pendingAction}
                    onAssign={onAssign}
                    onStart={onStart}
                    onComplete={onComplete}
                />
            </div>
        </div>
    );
}
