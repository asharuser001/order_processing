<?php

namespace App\Http\Middleware;

use Osiset\ShopifyApp\Contracts\ShopModel as IShopModel;
use Osiset\ShopifyApp\Http\Middleware\VerifyShopify as BaseVerifyShopify;
use Illuminate\Http\Request;

/**
 * Extends Osiset's VerifyShopify to support session-based authentication
 * for full-page (non-API) requests in SPA mode with Inertia.js.
 *
 * Problem: When Inertia falls back to a full-page GET (e.g. on page refresh or
 * when the session token hasn't loaded yet), the request has no ?shop= parameter
 * or X-Shop-Domain header, so getShopIfAlreadyInstalled() returns null and the
 * middleware issues a tokenRedirect() with host=null — causing the
 * authenticate/token page to throw "No host found in the URL".
 *
 * Fix: After the standard domain lookup fails, check if Laravel's auth session
 * already has an authenticated shop/user from a previous valid token exchange.
 * Non-API (full-page) requests with a valid session are allowed through.
 * API (Inertia XHR) requests still require a Bearer session token.
 */
class VerifyShopify extends BaseVerifyShopify
{
    /**
     * Get the shop if it is already installed, with a session-auth fallback.
     *
     * @param Request $request
     * @return IShopModel|null
     */
    protected function getShopIfAlreadyInstalled(Request $request): ?IShopModel
    {
        // Standard approach: find shop from request params / headers / referer
        $shop = parent::getShopIfAlreadyInstalled($request);
        if ($shop !== null) {
            return $shop;
        }

        // Fallback: use the already-authenticated user from the PHP session.
        // After the initial JWT exchange, Laravel's auth session remembers the
        // shop so subsequent full-page GETs don't need to repeat token auth.
        $user = $request->user();
        if (! ($user instanceof IShopModel) || ! $user->password || $user->trashed()) {
            return null;
        }

        // Never reuse a previous shop session when the current request clearly
        // targets another shop. This prevents cross-store leakage.
        $requestedShop = $request->query('shop') ?: $request->header('x-shopify-shop-domain');

        if ($requestedShop) {
            $requestedShop = strtolower((string) $requestedShop);
            $sessionShops = array_filter([
                strtolower((string) ($user->name ?? '')),
                strtolower((string) ($user->shopify_domain ?? '')),
            ]);

            if (! in_array($requestedShop, $sessionShops, true)) {
                return null;
            }
        }

        if ($user instanceof IShopModel && $user->password && ! $user->trashed()) {
            return $user;
        }

        return null;
    }

    /**
     * Determine whether the request should be treated as an API (token-auth) request.
     *
     * Inertia navigations carry X-Requested-With: XMLHttpRequest which would make
     * the base class treat them as API requests — forcing JWT bearer-token auth and
     * bypassing the session fallback above.  Because Inertia requests are same-site
     * same-origin navigations (not external API calls), we classify them as
     * full-page requests so the session-auth path is used instead.
     *
     * @param Request $request
     * @return bool
     */
    protected function isApiRequest(Request $request): bool
    {
        // Inertia requests carry X-Inertia: true — treat as full-page for auth purposes
        if ($request->hasHeader('X-Inertia')) {
            return false;
        }

        return parent::isApiRequest($request);
    }
}
