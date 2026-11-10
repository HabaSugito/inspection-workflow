import { StrictMode, useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { listInspections } from './api';
import { InspectionDetail } from './components/InspectionDetail';
import { InspectionList } from './components/InspectionList';
import type { Inspection } from './types';

type LoadState = 'loading' | 'ready' | 'error';

function App() {
    const [inspections, setInspections] = useState<Inspection[]>([]);
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [load, setLoad] = useState<LoadState>('loading');
    const [loadError, setLoadError] = useState('');

    useEffect(() => {
        let cancelled = false;

        listInspections()
            .then((loaded) => {
                if (cancelled) return;
                setInspections(loaded);
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

    return (
        <main className="mx-auto max-w-5xl p-6">
            <h1 className="text-2xl font-semibold">Inspections</h1>
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
                            onSelect={setSelectedId}
                        />
                    )}
                </section>
                <section className="rounded-lg border border-gray-200 bg-white p-4">
                    {selected ? (
                        <InspectionDetail inspection={selected} />
                    ) : (
                        <p className="text-sm text-gray-500">
                            Select an inspection to see its details.
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
