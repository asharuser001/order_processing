/**
 * Orders/Index.jsx — Inertia page
 *
 * Premium Inertia order list using the same visual language as the dashboard.
 * Receives `orders` (paginator) and `filters` from OrderController.
 */

import React, { useState, useCallback } from 'react';
import { router } from '@inertiajs/react';
import {
    Page,
    Button,
    Text,
    BlockStack,
    InlineStack,
    Pagination,
    TextField,
    Select,
} from '@shopify/polaris';
import { SearchIcon, RefreshIcon } from '@shopify/polaris-icons';

import { shopifyUrl } from '../../utils/shopifyRoute';
import AppLayout from '../../Components/Layout/AppLayout';
import StatusBadge from '../../Components/UI/StatusBadge';
import { T } from '../../design/tokens';

// ── Filter option lists ────────────────────────────────────────────────

const financialOptions = [
    { label: 'All Payment Statuses', value: '' },
    { label: 'Pending', value: 'PENDING' },
    { label: 'Paid', value: 'PAID' },
    { label: 'Partially Paid', value: 'PARTIALLY_PAID' },
    { label: 'Refunded', value: 'REFUNDED' },
    { label: 'Voided', value: 'VOIDED' },
];

const fulfillmentOptions = [
    { label: 'All Fulfillment Statuses', value: '' },
    { label: 'Unfulfilled', value: 'UNFULFILLED' },
    { label: 'Fulfilled', value: 'FULFILLED' },
    { label: 'Partial', value: 'PARTIAL' },
];

const stageOptions = [
    { label: 'All Stages', value: '' },
    { label: 'Processing', value: 'Processing' },
    { label: 'Awaiting Payment', value: 'Awaiting Payment' },
    { label: 'Awaiting Balance', value: 'Awaiting Balance' },
    { label: 'Ready to Fulfill', value: 'Ready to Fulfill' },
    { label: 'In Fulfillment', value: 'In Fulfillment' },
    { label: 'Completed', value: 'Completed' },
    { label: 'Cancelled', value: 'Cancelled' },
    { label: 'Refunded', value: 'Refunded' },
    { label: 'Payment Delayed', value: 'Payment Delayed' },
    { label: 'Fulfillment Delayed', value: 'Fulfillment Delayed' },
];

// ── Theme fallbacks ────────────────────────────────────────────────────

const C = {
    bg: '#F5F7FB',
    surface: T?.surface ?? '#FFFFFF',
    surfaceSoft: '#F8FAFC',
    border: T?.border ?? '#E2E8F0',
    borderSoft: T?.borderSoft ?? '#EEF2F7',
    text: T?.text ?? '#111827',
    textSub: T?.textSub ?? '#475569',
    textMuted: T?.textMuted ?? '#94A3B8',
    primary: '#2563EB',
    primaryDark: '#1D4ED8',
    primarySoft: '#EFF6FF',
    indigoSoft: '#EEF2FF',
    greenSoft: '#ECFDF5',
    amberSoft: '#FFFBEB',
    redSoft: '#FEF2F2',
    radius: T?.radius ?? '16px',
    radiusMd: T?.radiusMd ?? '12px',
    shadow: T?.shadow ?? '0 12px 30px rgba(15, 23, 42, 0.06)',
    shadowMd: T?.shadowMd ?? '0 18px 45px rgba(15, 23, 42, 0.10)',
};

const formatMoney = (order) => {
    const amount = Number(order?.total_price ?? 0);
    return `${order?.currency ?? ''} ${amount.toFixed(2)}`.trim();
};

const formatEvent = (value) => {
    if (!value) return 'No event yet';

    return String(value)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const normalize = (value) => String(value ?? '').toLowerCase();

const getOrdersData = (orders) => orders?.data ?? [];

function StatSummary({ orders }) {
    const total = orders?.total ?? getOrdersData(orders).length ?? 0;
    const currentPage = orders?.current_page ?? 1;
    const lastPage = orders?.last_page ?? 1;
    const from = orders?.from ?? 0;
    const to = orders?.to ?? 0;

    const items = [
        { label: 'Total Orders', value: total, bg: C.primarySoft, color: C.primary },
        { label: 'Current Page', value: `${currentPage}/${lastPage}`, bg: C.indigoSoft, color: '#4F46E5' },
        { label: 'Showing', value: total ? `${from}-${to}` : '0', bg: C.greenSoft, color: '#059669' },
    ];

    return (
        <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
            gap: '12px',
            marginBottom: '18px',
        }}>
            {items.map((item) => (
                <div
                    key={item.label}
                    style={{
                        background: C.surface,
                        border: `1px solid ${C.borderSoft}`,
                        borderRadius: C.radiusMd,
                        boxShadow: '0 8px 20px rgba(15, 23, 42, 0.04)',
                        padding: '14px 16px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        gap: '12px',
                    }}
                >
                    <div>
                        <div style={{ fontSize: '12px', color: C.textMuted, fontWeight: 600 }}>
                            {item.label}
                        </div>
                        <div style={{ marginTop: '4px', fontSize: '22px', color: C.text, fontWeight: 800 }}>
                            {item.value}
                        </div>
                    </div>
                    <div style={{
                        width: '38px',
                        height: '38px',
                        borderRadius: '14px',
                        background: item.bg,
                        color: item.color,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        fontWeight: 800,
                    }}>
                        •
                    </div>
                </div>
            ))}
        </div>
    );
}

function FilterBar({
    searchValue,
    onSearchChange,
    onSearchSubmit,
    onClear,
    filters,
    onFilterChange,
}) {
    return (
        <div style={{
            background: `linear-gradient(180deg, ${C.surface} 0%, ${C.surfaceSoft} 100%)`,
            border: `1px solid ${C.borderSoft}`,
            borderRadius: C.radius,
            boxShadow: C.shadow,
            padding: '16px',
            marginBottom: '16px',
        }}>
            <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
                gap: '12px',
                alignItems: 'end',
            }}>
                <div>
                    <div style={{ marginBottom: '6px', fontSize: '12px', color: C.textMuted, fontWeight: 700 }}>
                        Search
                    </div>
                    <TextField
                        label="Search"
                        labelHidden
                        prefix={<SearchIcon />}
                        placeholder="Search by order, customer, email…"
                        value={searchValue}
                        onChange={onSearchChange}
                        clearButton
                        onClearButtonClick={() => {
                            onSearchChange('');
                            onClear();
                        }}
                        autoComplete="off"
                    />
                </div>

                <div>
                    <div style={{ marginBottom: '6px', fontSize: '12px', color: C.textMuted, fontWeight: 700 }}>
                        Payment
                    </div>
                    <Select
                        label="Payment Status"
                        labelHidden
                        options={financialOptions}
                        value={filters.financial_status ?? ''}
                        onChange={onFilterChange('financial_status')}
                    />
                </div>

                <div>
                    <div style={{ marginBottom: '6px', fontSize: '12px', color: C.textMuted, fontWeight: 700 }}>
                        Fulfillment
                    </div>
                    <Select
                        label="Fulfillment Status"
                        labelHidden
                        options={fulfillmentOptions}
                        value={filters.fulfillment_status ?? ''}
                        onChange={onFilterChange('fulfillment_status')}
                    />
                </div>

                <div>
                    <div style={{ marginBottom: '6px', fontSize: '12px', color: C.textMuted, fontWeight: 700 }}>
                        Stage
                    </div>
                    <Select
                        label="Current Stage"
                        labelHidden
                        options={stageOptions}
                        value={filters.current_stage ?? ''}
                        onChange={onFilterChange('current_stage')}
                    />
                </div>
            </div>

            <div style={{
                display: 'flex',
                gap: '8px',
                justifyContent: 'flex-end',
                flexWrap: 'wrap',
                marginTop: '12px',
            }}>
                <Button variant="primary" onClick={onSearchSubmit}>
                    Search
                </Button>

                <Button onClick={onClear} icon={RefreshIcon}>
                    Reset
                </Button>
            </div>
        </div>
    );
}

function EmptyRows() {
    return (
        <div style={{
            padding: '56px 20px',
            textAlign: 'center',
            color: C.textMuted,
        }}>
            <div style={{
                width: '56px',
                height: '56px',
                borderRadius: '18px',
                background: C.primarySoft,
                color: C.primary,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto 14px',
                fontSize: '24px',
                fontWeight: 800,
            }}>
                #
            </div>
            <Text variant="headingMd" as="h3">No orders found</Text>
            <div style={{ marginTop: '6px', fontSize: '13px' }}>
                Try changing filters or sync orders from the dashboard.
            </div>
        </div>
    );
}

function OrdersTable({ orders, onPreviousPage, onNextPage }) {
    const [hovered, setHovered] = useState(null);
    const rows = getOrdersData(orders);

    return (
        <div style={{
            background: C.surface,
            border: `1px solid ${C.borderSoft}`,
            borderRadius: C.radius,
            boxShadow: C.shadow,
            overflow: 'hidden',
        }}>
            <div style={{
                padding: '18px 20px',
                borderBottom: `1px solid ${C.borderSoft}`,
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                gap: '12px',
            }}>
                <div>
                    <Text variant="headingMd" as="h2">Order Operations</Text>
                    <div style={{ marginTop: '4px', color: C.textMuted, fontSize: '13px' }}>
                        Track payment status, fulfillment status, and timeline progress.
                    </div>
                </div>

                <div style={{
                    background: C.primarySoft,
                    color: C.primaryDark,
                    borderRadius: '999px',
                    padding: '6px 12px',
                    fontSize: '12px',
                    fontWeight: 700,
                }}>
                    {orders?.total ?? rows.length} records
                </div>
            </div>

            {rows.length === 0 ? (
                <EmptyRows />
            ) : (
                <div style={{ overflowX: 'auto' }}>
                    <table style={{
                        width: '100%',
                        minWidth: '980px',
                        borderCollapse: 'separate',
                        borderSpacing: 0,
                    }}>
                        <thead>
                            <tr>
                                {[
                                    'Order',
                                    'Customer',
                                    'Email',
                                    'Payment',
                                    'Fulfillment',
                                    'Stage',
                                    'Last Event',
                                    'Total',
                                    'Action',
                                ].map((heading, index) => (
                                    <th
                                        key={heading}
                                        style={{
                                            padding: '13px 18px',
                                            background: '#F8FAFC',
                                            borderBottom: `1px solid ${C.borderSoft}`,
                                            color: C.textMuted,
                                            fontSize: '11px',
                                            fontWeight: 800,
                                            letterSpacing: '0.07em',
                                            textTransform: 'uppercase',
                                            textAlign: index === 7 ? 'right' : index === 8 ? 'center' : 'left',
                                            whiteSpace: 'nowrap',
                                        }}
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody>
                            {rows.map((order) => (
                                <tr
                                    key={order.id}
                                    onMouseEnter={() => setHovered(order.id)}
                                    onMouseLeave={() => setHovered(null)}
                                    style={{
                                        background: hovered === order.id ? '#F8FAFF' : C.surface,
                                        transition: 'background 0.16s ease',
                                    }}
                                >
                                    <td style={cellStyle()}>
                                        <button
                                            type="button"
                                            onClick={() => router.visit(shopifyUrl(`/orders/${order.id}`))}
                                            style={{
                                                border: 'none',
                                                background: 'transparent',
                                                padding: 0,
                                                cursor: 'pointer',
                                                color: C.primaryDark,
                                                fontWeight: 800,
                                                fontSize: '13px',
                                            }}
                                        >
                                            {order.order_name ?? '—'}
                                        </button>
                                    </td>

                                    <td style={cellStyle()}>
                                        <div style={{ fontWeight: 650, color: C.text }}>
                                            {order.customer_name ?? '—'}
                                        </div>
                                    </td>

                                    <td style={cellStyle()}>
                                        <span style={{ color: C.textMuted, fontSize: '12px' }}>
                                            {order.customer_email ?? '—'}
                                        </span>
                                    </td>

                                    <td style={cellStyle()}>
                                        <StatusBadge status={normalize(order.financial_status)} />
                                    </td>

                                    <td style={cellStyle()}>
                                        <StatusBadge status={normalize(order.fulfillment_status)} />
                                    </td>

                                    <td style={cellStyle()}>
                                        <StatusBadge status={order.current_stage} />
                                    </td>

                                    <td style={cellStyle()}>
                                        <span style={{ color: C.textSub, fontSize: '12px', fontWeight: 600 }}>
                                            {formatEvent(order.last_event_type)}
                                        </span>
                                    </td>

                                    <td style={cellStyle('right')}>
                                        <span style={{ color: C.text, fontSize: '13px', fontWeight: 800 }}>
                                            {formatMoney(order)}
                                        </span>
                                    </td>

                                    <td style={cellStyle('center')}>
                                        <Button
                                            size="slim"
                                            onClick={() => router.visit(shopifyUrl(`/orders/${order.id}`))}
                                        >
                                            Timeline
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div style={{
                padding: '14px 18px',
                borderTop: `1px solid ${C.borderSoft}`,
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                gap: '12px',
                flexWrap: 'wrap',
                background: '#FBFCFE',
            }}>
                <span style={{ fontSize: '13px', color: C.textMuted }}>
                    {orders?.total
                        ? `Showing ${orders.from ?? 0}–${orders.to ?? 0} of ${orders.total} orders`
                        : 'No orders found'}
                </span>

                {orders && (
                    <Pagination
                        hasPrevious={!!orders.prev_page_url}
                        hasNext={!!orders.next_page_url}
                        onPrevious={() => onPreviousPage?.(orders.prev_page_url)}
                        onNext={() => onNextPage?.(orders.next_page_url)}
                    />
                )}
            </div>
        </div>
    );
}

function cellStyle(align = 'left') {
    return {
        padding: '14px 18px',
        borderBottom: `1px solid ${C.borderSoft}`,
        textAlign: align,
        verticalAlign: 'middle',
        whiteSpace: 'nowrap',
        fontSize: '13px',
    };
}

function pageFromPaginatorUrl(url) {
    if (!url) return null;

    try {
        const parsed = new URL(url, window.location.origin);
        const page = Number(parsed.searchParams.get('page'));
        return Number.isInteger(page) && page > 0 ? page : null;
    } catch {
        return null;
    }
}

// ── Page component ─────────────────────────────────────────────────────

export default function OrdersIndex({ orders, filters: initialFilters }) {
    const [filters, setFilters] = useState(initialFilters ?? {});
    const [searchValue, setSearchValue] = useState(initialFilters?.search ?? '');

    const applyFilters = useCallback((newFilters) => {
        router.get(shopifyUrl('/orders'), newFilters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, []);

    const handleSearchSubmit = () => {
        const next = { ...filters, search: searchValue };
        setFilters(next);
        applyFilters(next);
    };

    const handleFilterChange = (key) => (value) => {
        const next = { ...filters, [key]: value, page: 1 };
        setFilters(next);
        applyFilters(next);
    };

    const handleFiltersClearAll = () => {
        setFilters({});
        setSearchValue('');
        applyFilters({});
    };

    const handlePaginatorNavigate = useCallback((targetUrl) => {
        const page = pageFromPaginatorUrl(targetUrl);

        if (!page) {
            return;
        }

        const next = {
            ...filters,
            search: searchValue,
            page,
        };

        setFilters(next);
        applyFilters(next);
    }, [applyFilters, filters, searchValue]);

    return (
        <AppLayout>
            <Page
                title="Orders"
                subtitle="All Shopify orders for your store"
                primaryAction={{
                    content: 'Refresh Orders',
                    onAction: () => applyFilters(filters),
                }}
            >
                <BlockStack gap="500">
                    <div style={{
                        background: `linear-gradient(135deg, ${C.primarySoft} 0%, #FFFFFF 58%, ${C.indigoSoft} 100%)`,
                        border: `1px solid ${C.borderSoft}`,
                        borderRadius: C.radius,
                        boxShadow: C.shadow,
                        padding: '22px',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        gap: '16px',
                        flexWrap: 'wrap',
                    }}>
                        <div>
                            <Text variant="headingLg" as="h1">Order Processing Center</Text>
                            <div style={{ marginTop: '6px', color: C.textSub, fontSize: '13px', maxWidth: '650px' }}>
                                Monitor Shopify orders, payment state, fulfillment progress, and timeline events from one operational view.
                            </div>
                        </div>

                        <div style={{
                            display: 'flex',
                            gap: '10px',
                            alignItems: 'center',
                            flexWrap: 'wrap',
                        }}>
                            <Button onClick={() => router.visit(shopifyUrl('/dashboard'))}>
                                Dashboard
                            </Button>
                            <Button variant="primary" onClick={() => applyFilters(filters)}>
                                Refresh
                            </Button>
                        </div>
                    </div>

                    <StatSummary orders={orders} />

                    <FilterBar
                        searchValue={searchValue}
                        onSearchChange={setSearchValue}
                        onSearchSubmit={handleSearchSubmit}
                        onClear={handleFiltersClearAll}
                        filters={filters}
                        onFilterChange={handleFilterChange}
                    />

                    <OrdersTable
                        orders={orders}
                        onPreviousPage={handlePaginatorNavigate}
                        onNextPage={handlePaginatorNavigate}
                    />
                </BlockStack>
            </Page>
        </AppLayout>
    );
}
