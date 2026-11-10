import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { InspectionsPage } from './components/InspectionsPage';

const container = document.getElementById('app');

if (!container) {
    throw new Error('Mount element #app is missing from the page.');
}

createRoot(container).render(
    <StrictMode>
        <InspectionsPage />
    </StrictMode>,
);
