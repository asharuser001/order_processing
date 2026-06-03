/**
 * AppRoutes.jsx — Client-side routing for the embedded app.
 *
 * React Router maps URL paths to the corresponding page components.
 * All pages are wrapped in AppLayout which provides the navigation frame.
 */

import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import AppLayout from './Components/Layout/AppLayout';
import Dashboard from './Pages/Dashboard';
import OrdersPage from './Pages/OrdersPage';
import OrderDetailPage from './Pages/OrderDetailPage';
import WebhookLogsPage from './Pages/WebhookLogsPage';
import Settings from './Pages/Settings';

export default function AppRoutes() {
    return (
        <Routes>
            <Route element={<AppLayout />}>
                <Route index element={<Navigate to="/dashboard" replace />} />
                <Route path="/dashboard"      element={<Dashboard />} />
                <Route path="/orders"         element={<OrdersPage />} />
                <Route path="/orders/:id"     element={<OrderDetailPage />} />
                <Route path="/webhook-logs"   element={<WebhookLogsPage />} />
                <Route path="/settings"       element={<Settings />} />
            </Route>
        </Routes>
    );
}
