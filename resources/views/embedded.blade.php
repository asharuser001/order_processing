@extends('shopify-app::layouts.default')

@section('styles')
    {{-- Polaris stylesheet --}}
    <link rel="stylesheet" href="https://unpkg.com/@shopify/polaris@13/build/esm/styles.css" />
    @routes
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
@endsection

@section('content')
    @inertia
@endsection

@section('scripts')
    @parent

    <ui-nav-menu>
        <a href="/dashboard" rel="home">Dashboard</a>
        <a href="/orders">Orders</a>
        <a href="/webhook-logs">Webhook Logs</a>
        <a href="/sync-logs">Sync Logs</a>
        <a href="/settings">Settings</a>
    </ui-nav-menu>

    <script>
        // Intercept all fetch() calls to attach the Shopify session token.
        // shopify.idToken() is provided by the App Bridge CDN script loaded
        // by the parent layout (shopify-app::layouts.default).
        const { fetch: originalFetch } = window;

        window.fetch = async (...args) => {
            let [resource, config] = args;

            const token = await shopify.idToken();

            config = {
                ...config,
                headers: {
                    ...config?.headers,
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`,
                },
            };

            return originalFetch(resource, config);
        };
    </script>
@endsection
