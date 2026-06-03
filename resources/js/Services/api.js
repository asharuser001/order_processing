/**
 * api.js — Centralised Axios API client
 *
 * Uses the global shopify.idToken() provided by the App Bridge CDN script
 * (loaded by shopify-app::layouts.default) to authenticate every request.
 */

import axios from 'axios';

/**
 * Create an axios client that attaches the Shopify session token on every request.
 * shopify.idToken() is a global available inside the Shopify Admin iframe.
 */
export function createAuthenticatedClient() {
    const client = axios.create({
        baseURL: '/api',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
    });

    client.interceptors.request.use(async (config) => {
        try {
            const token = await window.shopify.idToken();
            config.headers['Authorization'] = `Bearer ${token}`;
        } catch (err) {
            console.error('[api] Failed to get session token:', err);
        }
        return config;
    });

    return client;
}

// ── Pre-built request helpers ────────────────────────────────────────────

export const api = {
    getDashboardStats: (client) => client.get('/dashboard/stats'),
    getDashboardChart: (client) => client.get('/dashboard/chart'),
    getShop: (client) => client.get('/shop'),
    getOrders: (client, params = {}) => client.get('/orders', { params }),
    getOrder: (client, id) => client.get(`/orders/${id}`),
    getOrderTimeline: (client, id) => client.get(`/orders/${id}/timeline`),
    syncOrders: (client) => client.post('/orders/sync'),
    getWebhookEvents: (client, params = {}) => client.get('/webhook-events', { params }),
    retryWebhookEvent: (client, id) => client.post(`/webhook-events/${id}/retry`),
};
