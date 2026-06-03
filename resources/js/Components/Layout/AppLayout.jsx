/**
 * AppLayout.jsx — Shared Inertia layout for all authenticated pages.
 *
 * Wraps every page with flash message banners from Inertia page props.
 * Intercepts App Bridge navigate events and delegates to Inertia router.
 */

import React, { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Banner, Frame } from '@shopify/polaris';
import { shopifyUrl } from '../../utils/shopifyRoute';

export default function AppLayout({ children }) {
    const { props } = usePage();
    const flash = props.flash ?? {};
    const [successDismissed, setSuccessDismissed] = useState(false);
    const [errorDismissed,   setErrorDismissed]   = useState(false);

    // Reset dismiss state when flash changes (new page load)
    useEffect(() => {
        setSuccessDismissed(false);
        setErrorDismissed(false);
    }, [flash.success, flash.error]);

    // Shopify App Bridge 3.x fires a 'navigate' event for sidebar nav-menu clicks.
    // Delegate to Inertia so the Bearer token interceptor is applied.
    useEffect(() => {
        if (!window.shopify?.on) return;
        const unsub = window.shopify.on('navigate', ({ path }) => {
            if (path && path !== window.location.pathname) {
                router.visit(shopifyUrl(path));
            }
        });
        return () => { if (typeof unsub === 'function') unsub(); };
    }, []);

    return (
        <Frame>
            {flash.success && !successDismissed && (
                <Banner tone="success" onDismiss={() => setSuccessDismissed(true)}>
                    <p>{flash.success}</p>
                </Banner>
            )}
            {flash.error && !errorDismissed && (
                <Banner tone="critical" onDismiss={() => setErrorDismissed(true)}>
                    <p>{flash.error}</p>
                </Banner>
            )}
            {children}
        </Frame>
    );
}

