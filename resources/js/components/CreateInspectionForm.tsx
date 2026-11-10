import { useState, type FormEvent } from 'react';
import { ApiError, createInspection, type FieldErrors } from '../api';
import type { Inspection, NewInspection } from '../types';

interface Props {
    onCreated: (inspection: Inspection) => void;
    onCancel: () => void;
}

const fields: { name: keyof NewInspection; label: string; type: 'text' | 'date' | 'textarea' }[] = [
    { name: 'customer_name', label: 'Customer name', type: 'text' },
    { name: 'vehicle_registration', label: 'Vehicle registration', type: 'text' },
    { name: 'requested_date', label: 'Requested date', type: 'date' },
    { name: 'notes', label: 'Notes (optional)', type: 'textarea' },
];

const empty: NewInspection = {
    customer_name: '',
    vehicle_registration: '',
    requested_date: '',
    notes: '',
};

const inputClass = 'mt-1 w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm';

export function CreateInspectionForm({ onCreated, onCancel }: Props) {
    const [values, setValues] = useState<NewInspection>(empty);
    const [submitting, setSubmitting] = useState(false);
    const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
    const [formError, setFormError] = useState<string | null>(null);

    async function submit(event: FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setFieldErrors({});
        setFormError(null);

        try {
            onCreated(await createInspection(values));
        } catch (error: unknown) {
            const errors = error instanceof ApiError ? error.fieldErrors : {};
            const known = fields.some((field) => field.name in errors);

            setFieldErrors(errors);

            // An error that belongs to no field on this form must still be seen.
            if (!known) {
                setFormError(error instanceof Error ? error.message : 'Unknown error.');
            }

            setSubmitting(false);
        }
    }

    return (
        <form onSubmit={submit} noValidate>
            <h2 className="text-lg font-semibold">New inspection</h2>
            {formError !== null && (
                <p role="alert" className="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {formError}
                </p>
            )}
            <div className="mt-3 space-y-3">
                {fields.map(({ name, label, type }) => {
                    const messages = fieldErrors[name] ?? [];
                    const errorId = `${name}-error`;
                    const common = {
                        id: name,
                        name,
                        value: values[name],
                        disabled: submitting,
                        className: inputClass,
                        'aria-invalid': messages.length > 0,
                        'aria-describedby': messages.length > 0 ? errorId : undefined,
                    };

                    return (
                        <div key={name}>
                            <label htmlFor={name} className="text-sm text-gray-500">
                                {label}
                            </label>
                            {type === 'textarea' ? (
                                <textarea
                                    {...common}
                                    rows={3}
                                    onChange={(event) =>
                                        setValues({ ...values, [name]: event.target.value })
                                    }
                                />
                            ) : (
                                <input
                                    {...common}
                                    type={type}
                                    onChange={(event) =>
                                        setValues({ ...values, [name]: event.target.value })
                                    }
                                />
                            )}
                            {messages.length > 0 && (
                                <p id={errorId} className="mt-1 text-sm text-red-700">
                                    {messages.join(' ')}
                                </p>
                            )}
                        </div>
                    );
                })}
            </div>
            <div className="mt-4 flex gap-2">
                <button
                    type="submit"
                    disabled={submitting}
                    className="cursor-pointer rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                    Create inspection
                </button>
                <button
                    type="button"
                    disabled={submitting}
                    onClick={onCancel}
                    className="cursor-pointer rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:bg-gray-50 disabled:cursor-not-allowed"
                >
                    Cancel
                </button>
            </div>
        </form>
    );
}
