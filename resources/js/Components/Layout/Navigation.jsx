/**
 * Navigation.jsx — Polaris side navigation for the embedded app.
 *
 * Links map to React Router paths. The active item is highlighted
 * by Polaris automatically via the `url` prop.
 */

import React from 'react';
import { Navigation as PolarisNavigation } from '@shopify/polaris';
import { useLocation } from 'react-router-dom';
import {
    HomeIcon,
    OrderIcon,
    AlertCircleIcon,
    SettingsIcon,
} from '@shopify/polaris-icons';

export default function Navigation() {
    const { pathname } = useLocation();

    return (
        <PolarisNavigation location={pathname}>
            <PolarisNavigation.Section
                items={[
                    {
                        url: '/dashboard',
                        label: 'Dashboard',
                        icon: HomeIcon,
                        selected: pathname === '/dashboard',
                    },
                    {
                        url: '/orders',
                        label: 'Orders',
                        icon: OrderIcon,
                        selected: pathname.startsWith('/orders'),
                    },
                    {
                        url: '/webhook-logs',
                        label: 'Webhook Logs',
                        icon: AlertCircleIcon,
                        selected: pathname === '/webhook-logs',
                    },
                    {
                        url: '/settings',
                        label: 'Settings',
                        icon: SettingsIcon,
                        selected: pathname === '/settings',
                    },
                ]}
            />
        </PolarisNavigation>
    );
}
