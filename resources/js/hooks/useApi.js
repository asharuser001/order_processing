/**
 * useApi.js — Returns an Axios client authenticated via shopify.idToken().
 * The global shopify object is provided by the App Bridge CDN script loaded
 * in the Blade layout (shopify-app::layouts.default).
 */

import { useMemo } from 'react';
import { createAuthenticatedClient } from '../Services/api';

export default function useApi() {
    // createAuthenticatedClient() uses window.shopify.idToken() — stable reference.
    return useMemo(() => createAuthenticatedClient(), []);
}
