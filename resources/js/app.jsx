/**
 * app.jsx — Inertia + React application entry point
 *
 * App Bridge is loaded via the CDN by shopify-app::layouts.default (Osiset).
 * Inertia handles server-driven page rendering; Polaris provides UI theming.
 */

import React from 'react';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { AppProvider } from '@shopify/polaris';
import enTranslations from '@shopify/polaris/locales/en.json';

// Inject the Shopify session token into every Inertia XHR request.
// window.sessionToken is kept fresh by shopify-app::partials.token_handler (MPA mode).
// Without this header the verify.shopify middleware rejects all AJAX/Inertia requests
// with "Session token is invalid" (HTTP 400).
router.on('before', (event) => {
    const token = window.sessionToken;
    if (token) {
        const visit = event.detail.visit;
        visit.headers = { ...(visit.headers ?? {}), Authorization: `Bearer ${token}` };
    }
});

createInertiaApp({
    // Automatically resolve page components from Pages/ directory
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        return pages[`./Pages/${name}.jsx`];
    },

    setup({ el, App, props }) {
        createRoot(el).render(
            <React.StrictMode>
                <AppProvider i18n={enTranslations}>
                    <App {...props} />
                </AppProvider>
            </React.StrictMode>
        );
    },

    progress: {
        color: '#5c6ac4',
    },
});

