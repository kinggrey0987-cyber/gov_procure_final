import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const rawAppName = import.meta.env.VITE_APP_NAME;
const appName =
    rawAppName && rawAppName.trim() !== '' && rawAppName.trim().toLowerCase() !== 'laravel'
        ? rawAppName.trim()
        : 'GovSupply Connect';

createInertiaApp({
    title: (title) => {
        const clean = (title || '').replace(/\s*[-|]\s*Laravel\b/gi, '').replace(/\bLaravel\b/gi, '').trim();
        return clean || 'GovSupply Connect';
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});




