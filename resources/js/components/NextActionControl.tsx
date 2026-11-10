import { useState, type FormEvent } from 'react';
import type { NextAction } from '../types';

interface Props {
    action: NextAction | null;
    disabled: boolean;
    onAssign: (inspectorName: string) => void;
    onStart: () => void;
    onComplete: () => void;
}

const buttonClass =
    'cursor-pointer rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300';

export function NextActionControl({ action, disabled, onAssign, onStart, onComplete }: Props) {
    const [inspectorName, setInspectorName] = useState('');

    switch (action) {
        case null:
            return null;
        case 'assign': {
            const submit = (event: FormEvent) => {
                event.preventDefault();
                onAssign(inspectorName.trim());
            };

            return (
                <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
                    <label className="text-sm">
                        <span className="block text-gray-500">Inspector name</span>
                        <input
                            type="text"
                            value={inspectorName}
                            disabled={disabled}
                            onChange={(event) => setInspectorName(event.target.value)}
                            className="mt-1 rounded-md border border-gray-300 px-2 py-1.5"
                        />
                    </label>
                    <button
                        type="submit"
                        className={buttonClass}
                        disabled={disabled || inspectorName.trim() === ''}
                    >
                        Assign inspector
                    </button>
                </form>
            );
        }
        case 'start':
            return (
                <button type="button" className={buttonClass} disabled={disabled} onClick={onStart}>
                    Start inspection
                </button>
            );
        case 'complete':
            return (
                <button
                    type="button"
                    className={buttonClass}
                    disabled={disabled}
                    onClick={onComplete}
                >
                    Complete inspection
                </button>
            );
        default: {
            const unhandled: never = action;

            return unhandled;
        }
    }
}
