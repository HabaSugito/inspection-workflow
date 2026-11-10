import { StrictMode, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { assignInspector, completeInspection, listInspections, startInspection } from './api';
import { CreateInspectionForm } from './components/CreateInspectionForm';
import { InspectionDetail } from './components/InspectionDetail';
import { InspectionList } from './components/InspectionList';
import type { Inspection } from './types';

type LoadState = 'loading' | 'ready' | 'error';

function App() {
    const [inspections, setInspections] = useState<Inspection[]>([]);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [load, setLoad] = useState<LoadState>('loading');
    const [loadError, setLoadError] = useState('');
    const [creating, setCreating] = useState(false);
    const [pendingAction, setPendingAction] = useState(false);
    const [actionError, setActionError] = useState<{ id: number; message: string } | null>(null);

    useEffect(() => {
        let cancelled = false;

        listInspections()
            .then((loaded) => {
                if (cancelled) return;
                setInspections(loaded);
                // The list is newest first. Selecting it puts a detail and a
                // next-step control on screen without a click.
                setSelectedId(loaded[0]?.id ?? null);
                setLoad('ready');
            })
            .catch((error: unknown) => {
                if (cancelled) return;
                setLoadError(error instanceof Error ? error.message : 'Unknown error.');
                setLoad('error');
            });

        return () => {
            cancelled = true;
        };
    }, []);

    const selected = inspections.find((inspection) => inspection.id === selectedId) ?? null;

    function select(id: number) {
        setSelectedId(id);
        setActionError(null);
        setCreating(false);
    }

    function addCreated(inspection: Inspection) {
        setInspections((current) => [inspection, ...current]);
        select(inspection.id);
    }

    async function run(id: number, action: () => Promise<Inspection>) {
        setPendingAction(true);

        try {
            const updated = await action();

            setInspections((current) =>
                current.map((inspection) => (inspection.id === updated.id ? updated : inspection)),
            );
            setActionError(null);
        } catch (error: unknown) {
            let message = error instanceof Error ? error.message : 'Unknown error.';

            // The refusal may mean the list is out of date, so show what the server has now.
            try {
                setInspections(await listInspections());
            } catch {
                message += ' The list could not be refreshed, so it may be out of date.';
            }

            // Kept with its inspection, so it is not shown under another one
            // selected while the request was in flight.
            setActionError({ id, message });
        } finally {
            setPendingAction(false);
        }
    }

    return (
        <main className="mx-auto max-w-5xl p-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Inspections</h1>
                <button
                    type="button"
                    onClick={() => setCreating(true)}
                    disabled={load !== 'ready' || creating}
                    className="cursor-pointer rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                    New inspection
                </button>
            </div>
            <div className="mt-6 grid gap-6 md:grid-cols-2">
                <section className="rounded-lg border border-gray-200 bg-white">
                    {load === 'loading' && (
                        <p className="p-4 text-sm text-gray-500">Loading inspections…</p>
                    )}
                    {load === 'error' && (
                        <p role="alert" className="p-4 text-sm text-red-700">
                            Could not load inspections. {loadError}
                        </p>
                    )}
                    {load === 'ready' && inspections.length === 0 && (
                        <p className="p-4 text-sm text-gray-500">No inspections yet.</p>
                    )}
                    {load === 'ready' && inspections.length > 0 && (
                        <InspectionList
                            inspections={inspections}
                            selectedId={selectedId}
                            onSelect={select}
                        />
                    )}
                </section>
                <section className="rounded-lg border border-gray-200 bg-white p-4">
                    {creating ? (
                        <CreateInspectionForm
                            onCreated={addCreated}
                            onCancel={() => setCreating(false)}
                        />
                    ) : selected ? (
                        <InspectionDetail
                            inspection={selected}
                            pendingAction={pendingAction}
                            actionError={
                                actionError?.id === selected.id ? actionError.message : null
                            }
                            onAssign={(name) =>
                                run(selected.id, () => assignInspector(selected.id, name))
                            }
                            onStart={() => run(selected.id, () => startInspection(selected.id))}
                            onComplete={() =>
                                run(selected.id, () => completeInspection(selected.id))
                            }
                        />
                    ) : (
                        <p className="text-sm text-gray-500">
                            No inspection selected. Create one with New inspection.
                        </p>
                    )}
                </section>
            </div>
        </main>
    );
}

const container = document.getElementById('app');

if (!container) {
    throw new Error('Mount element #app is missing from the page.');
}

createRoot(container).render(
    <StrictMode>
        <App />
    </StrictMode>,
);
