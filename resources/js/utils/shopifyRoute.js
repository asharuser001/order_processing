/**
 * shopifyRoute.js — URL helper for Shopify embedded app navigation.
 *
 * Shopify embedded apps require specific query params (host, shop, embedded,
 * hmac, timestamp, session) to be present on every URL, otherwise App Bridge
 * loses its context and the app shows a "page not found" or auth error.
 *
 * These helpers preserve those params automatically on every navigation.
 *
 * Usage:
 *   import { shopifyUrl, withShopifyQuery } from '@/utils/shopifyRoute';
 *
 *   // Navigate with Inertia preserving Shopify params:
 *   router.get(shopifyUrl('/orders', { status: 'pending' }));
 *
 *   // Or use the helper directly for href attributes:
 *   <a href={shopifyUrl('/dashboard')}>Dashboard</a>
 */

/**
 * The Shopify query parameter keys that must be preserved on every navigation.
 *
 * NOTE: `hmac` and `timestamp` are intentionally excluded. They are one-time
 * verification params signed by Shopify for the initial embedded app load URL.
 * If forwarded to subsequent requests, Osiset re-validates the HMAC against the
 * new URL's query params and always fails → SignatureVerificationException.
 * Only `host`, `shop`, `embedded`, and `session` are needed for App Bridge context.
 */
const SHOPIFY_PARAMS = ['host', 'shop', 'embedded', 'session'];

/**
 * Reads the current Shopify query params from window.location.search.
 * Returns an object with only the params that are present and non-empty.
 *
 * @returns {Record<string, string>}
 */
export function getShopifyParams() {
    if (typeof window === 'undefined') return {};
    const current = new URLSearchParams(window.location.search);
    return Object.fromEntries(
        SHOPIFY_PARAMS
            .map(k => [k, current.get(k)])
            .filter(([, v]) => v != null && v !== '')
    );
}

/**
 * Appends Shopify query params (host, shop, embedded, etc.) from the current
 * URL to the given URL. Caller-supplied `query` params take precedence over
 * the preserved Shopify params.
 *
 * @param {string} url   - The base URL (e.g. '/orders' or '/orders?status=open')
 * @param {Record<string, string|number|boolean>} [query={}] - Extra query params to append
 * @returns {string}     - The URL with all query params merged in
 */
export function withShopifyQuery(url, query = {}) {
    const preserved = getShopifyParams();
    const merged = { ...preserved, ...query };

    const qs = new URLSearchParams(
        Object.entries(merged)
            .filter(([, v]) => v != null && v !== '' && v !== false)
            .map(([k, v]) => [k, String(v)])
    ).toString();

    if (!qs) return url;
    return url.includes('?') ? `${url}&${qs}` : `${url}?${qs}`;
}

/**
 * Convenience alias — builds a URL with Shopify query params preserved.
 *
 * @param {string} path  - The path (e.g. '/orders')
 * @param {Record<string, string|number|boolean>} [query={}] - Extra query params
 * @returns {string}
 */
export function shopifyUrl(path, query = {}) {
    return withShopifyQuery(path, query);
}
