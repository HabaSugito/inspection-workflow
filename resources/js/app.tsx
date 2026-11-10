import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

function App() {
    return (
        <main className="mx-auto max-w-5xl p-6">
            <h1 className="text-2xl font-semibold">Inspections</h1>
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
