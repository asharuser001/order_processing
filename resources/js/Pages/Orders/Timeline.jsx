/**
 * Orders/Timeline.jsx — Inertia page
 *
 * Premium timeline page for a single Shopify order.
 * Receives `order` (summary), `events` (timeline events), `lifecycle` (duration string)
 * from OrderController.
 */

import React from 'react';
import { router } from '@inertiajs/react';
import {
    Page,
    Layout,
    Button,
    BlockStack,
    InlineStack,
    Divider,
} from '@shopify/polaris';
import { shopifyUrl } from '../../utils/shopifyRoute';
import AppLayout from '../../Components/Layout/AppLayout';
import StatusBadge from '../../Components/UI/StatusBadge';
import SectionCard from '../../Components/UI/SectionCard';
import { T } from '../../design/tokens';

// ── Premium local UI tokens, aligned with dashboard/orders colors ──────

const UI = {
    pageBg: '#F6F8FB',
    card: '#FFFFFF',
    cardSoft: '#F8FAFC',
    border: '#E2E8F0',
    borderSoft: '#EEF2F7',
    text: '#0F172A',
    textSub: '#334155',
    textMuted: '#64748B',
    primary: '#4F46E5',
    primarySoft: '#EEF2FF',
    primaryBorder: '#C7D2FE',
    blue: '#2563EB',
    blueSoft: '#EFF6FF',
    green: '#059669',
    greenSoft: '#ECFDF5',
    amber: '#D97706',
    amberSoft: '#FFFBEB',
    red: '#DC2626',
    redSoft: '#FEF2F2',
    slate: '#475569',
    slateSoft: '#F1F5F9',
    shadow: '0 10px 30px rgba(15, 23, 42, 0.06)',
    shadowSm: '0 4px 14px rgba(15, 23, 42, 0.05)',
    radius: '18px',
    radiusMd: '14px',
    radiusSm: '10px',
};

const EVENT_STYLE = {
    order_created: {
        icon: '🛒',
        color: UI.blue,
        bg: UI.blueSoft,
        border: '#BFDBFE',
    },
    payment_pending: {
        icon: '⏳',
        color: UI.amber,
        bg: UI.amberSoft,
        border: '#FDE68A',
    },
    payment_partially_paid: {
        icon: '◐',
        color: UI.amber,
        bg: UI.amberSoft,
        border: '#FDE68A',
    },
    payment_completed: {
        icon: '💳',
        color: UI.green,
        bg: UI.greenSoft,
        border: '#A7F3D0',
    },
    order_updated: {
        icon: '✦',
        color: UI.primary,
        bg: UI.primarySoft,
        border: UI.primaryBorder,
    },
    order_fulfilled: {
        icon: '📦',
        color: UI.green,
        bg: UI.greenSoft,
        border: '#A7F3D0',
    },
    order_partially_fulfilled: {
        icon: '◒',
        color: UI.amber,
        bg: UI.amberSoft,
        border: '#FDE68A',
    },
    order_cancelled: {
        icon: '✕',
        color: UI.red,
        bg: UI.redSoft,
        border: '#FECACA',
    },
    order_refunded: {
        icon: '↩',
        color: UI.primary,
        bg: UI.primarySoft,
        border: UI.primaryBorder,
    },
    order_deleted: {
        icon: '🗑',
        color: UI.red,
        bg: UI.redSoft,
        border: '#FECACA',
    },
};

const SOURCE_LABELS = {
    webhook: 'Via Webhook',
    sync: 'Via Sync',
    retry: 'Via Retry',
    webhook_retry: 'Via Retry',
};

const HIDDEN_METADATA_KEYS = new Set(['admin_graphql_api_id']);

// ── Helpers ───────────────────────────────────────────────────────────

function formatDuration(seconds) {
    if (!seconds || seconds < 1) return null;
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;

    if (seconds < 86400) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        return m > 0 ? `${h}h ${m}m` : `${h}h`;
    }

    const d = Math.floor(seconds / 86400);
    const h = Math.floor((seconds % 86400) / 3600);

    return h > 0 ? `${d}d ${h}h` : `${d}d`;
}

function formatDateTime(value) {
    if (!value) return '—';

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function prettyText(value) {
    if (!value) return '—';

    return String(value)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function safeNumber(value) {
    const numeric = Number(value ?? 0);
    return Number.isFinite(numeric) ? numeric : 0;
}

function getEventStyle(eventType) {
    return EVENT_STYLE[eventType] ?? {
        icon: '•',
        color: UI.slate,
        bg: UI.slateSoft,
        border: UI.border,
    };
}

function getCompletionProgress(events = []) {
    const eventTypes = new Set(events.map((event) => event.event_type));

    const steps = [
        { key: 'order_created', label: 'Created' },
        { key: 'payment_completed', label: 'Paid' },
        { key: 'order_fulfilled', label: 'Fulfilled' },
    ];

    const completed = steps.filter((step) => eventTypes.has(step.key)).length;
    const percentage = steps.length ? Math.round((completed / steps.length) * 100) : 0;

    return { steps, percentage, eventTypes };
}

// ── Sub-components ────────────────────────────────────────────────────

function MiniMetric({ label, value, tone = 'neutral' }) {
    const tones = {
        neutral: { bg: UI.slateSoft, color: UI.slate, border: UI.border },
        blue: { bg: UI.blueSoft, color: UI.blue, border: '#BFDBFE' },
        green: { bg: UI.greenSoft, color: UI.green, border: '#A7F3D0' },
        amber: { bg: UI.amberSoft, color: UI.amber, border: '#FDE68A' },
        red: { bg: UI.redSoft, color: UI.red, border: '#FECACA' },
        indigo: { bg: UI.primarySoft, color: UI.primary, border: UI.primaryBorder },
    };

    const style = tones[tone] ?? tones.neutral;

    return (
        <div
            style={{
                background: style.bg,
                border: `1px solid ${style.border}`,
                borderRadius: UI.radiusMd,
                padding: '12px 14px',
                minWidth: 0,
            }}
        >
            <div style={{ fontSize: '11px', fontWeight: 700, color: style.color, textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                {label}
            </div>
            <div style={{ marginTop: '6px', fontSize: '16px', fontWeight: 750, color: UI.text, wordBreak: 'break-word' }}>
                {value ?? '—'}
            </div>
        </div>
    );
}

function InfoRow({ label, children }) {
    return (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                gap: '14px',
                padding: '11px 0',
                borderBottom: `1px solid ${UI.borderSoft}`,
            }}
        >
            <span style={{ fontSize: '13px', color: UI.textMuted, flexShrink: 0 }}>{label}</span>
            <span style={{ fontSize: '13px', color: UI.text, textAlign: 'right', fontWeight: 550 }}>{children}</span>
        </div>
    );
}

function SourceBadge({ source }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '6px',
                padding: '5px 10px',
                borderRadius: '999px',
                background: UI.primarySoft,
                color: UI.primary,
                border: `1px solid ${UI.primaryBorder}`,
                fontSize: '12px',
                fontWeight: 700,
                whiteSpace: 'nowrap',
            }}
        >
            <span
                style={{
                    width: '6px',
                    height: '6px',
                    borderRadius: '50%',
                    background: UI.primary,
                    display: 'inline-block',
                }}
            />
            {SOURCE_LABELS[source] ?? prettyText(source)}
        </span>
    );
}

function MetadataPill({ label, value }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                gap: '5px',
                alignItems: 'center',
                background: UI.slateSoft,
                border: `1px solid ${UI.borderSoft}`,
                borderRadius: '999px',
                padding: '5px 9px',
                fontSize: '11.5px',
                color: UI.textMuted,
                maxWidth: '100%',
            }}
        >
            <strong style={{ color: UI.textSub, fontWeight: 650 }}>{label}:</strong>
            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {String(value)}
            </span>
        </span>
    );
}

function ProgressTracker({ events = [] }) {
    const { steps, percentage, eventTypes } = getCompletionProgress(events);

    return (
        <div
            style={{
                background: `linear-gradient(135deg, ${UI.primarySoft}, #FFFFFF)`,
                border: `1px solid ${UI.primaryBorder}`,
                borderRadius: UI.radius,
                padding: '18px',
                boxShadow: UI.shadowSm,
            }}
        >
            <InlineStack align="space-between" blockAlign="center">
                <div>
                    <div style={{ fontSize: '13px', fontWeight: 750, color: UI.text }}>
                        Order Progress
                    </div>
                    <div style={{ marginTop: '2px', fontSize: '12px', color: UI.textMuted }}>
                        Created → Paid → Fulfilled
                    </div>
                </div>

                <div style={{ fontSize: '20px', fontWeight: 800, color: UI.primary }}>
                    {percentage}%
                </div>
            </InlineStack>

            <div
                style={{
                    height: '8px',
                    background: '#E0E7FF',
                    borderRadius: '999px',
                    marginTop: '14px',
                    overflow: 'hidden',
                }}
            >
                <div
                    style={{
                        width: `${percentage}%`,
                        height: '100%',
                        background: `linear-gradient(90deg, ${UI.primary}, ${UI.blue})`,
                        borderRadius: '999px',
                        transition: 'width 0.2s ease',
                    }}
                />
            </div>

            <div
                style={{
                    display: 'grid',
                    gridTemplateColumns: 'repeat(3, 1fr)',
                    gap: '10px',
                    marginTop: '14px',
                }}
            >
                {steps.map((step) => {
                    const isDone = eventTypes.has(step.key);

                    return (
                        <div
                            key={step.key}
                            style={{
                                borderRadius: UI.radiusSm,
                                padding: '9px 10px',
                                background: isDone ? UI.greenSoft : '#FFFFFF',
                                border: `1px solid ${isDone ? '#A7F3D0' : UI.border}`,
                                color: isDone ? UI.green : UI.textMuted,
                                fontSize: '12px',
                                fontWeight: 700,
                                textAlign: 'center',
                            }}
                        >
                            {isDone ? '✓ ' : ''}{step.label}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function TimelineEvent({ event, isLast = false }) {
    const style = getEventStyle(event.event_type);
    const happenedAt = formatDateTime(event.happened_at);
    const durationText = formatDuration(event.duration_from_previous);

    const metaEntries = event.metadata
        ? Object.entries(event.metadata).filter(([key, value]) => (
            !HIDDEN_METADATA_KEYS.has(String(key).toLowerCase())
            && value !== null
            && value !== undefined
            && value !== ''
        ))
        : [];

    return (
        <div style={{ display: 'grid', gridTemplateColumns: '52px minmax(0, 1fr)', gap: '0', position: 'relative' }}>
            <div style={{ display: 'flex', justifyContent: 'center', position: 'relative' }}>
                {!isLast && (
                    <div
                        style={{
                            position: 'absolute',
                            top: '42px',
                            bottom: '-18px',
                            width: '2px',
                            background: `linear-gradient(${style.border}, ${UI.borderSoft})`,
                        }}
                    />
                )}

                <div
                    style={{
                        width: '38px',
                        height: '38px',
                        borderRadius: '999px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        background: style.bg,
                        color: style.color,
                        border: `1px solid ${style.border}`,
                        boxShadow: '0 6px 18px rgba(15, 23, 42, 0.08)',
                        zIndex: 1,
                        fontSize: '16px',
                    }}
                >
                    {style.icon}
                </div>
            </div>

            <div
                style={{
                    background: UI.card,
                    border: `1px solid ${UI.border}`,
                    borderRadius: UI.radius,
                    boxShadow: UI.shadowSm,
                    padding: '16px',
                    marginBottom: isLast ? '0' : '18px',
                }}
            >
                <InlineStack align="space-between" blockAlign="start" gap="300">
                    <div style={{ minWidth: 0 }}>
                        <div style={{ fontSize: '15px', fontWeight: 750, color: UI.text }}>
                            {event.event_label ?? prettyText(event.event_type)}
                        </div>
                        <div style={{ marginTop: '5px', fontSize: '12.5px', color: UI.textMuted }}>
                            {happenedAt}
                        </div>
                    </div>

                    <SourceBadge source={event.source} />
                </InlineStack>

                {durationText && (
                    <div
                        style={{
                            marginTop: '12px',
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '7px',
                            padding: '6px 10px',
                            borderRadius: '999px',
                            background: UI.blueSoft,
                            border: '1px solid #BFDBFE',
                            color: UI.blue,
                            fontSize: '12px',
                            fontWeight: 700,
                        }}
                    >
                        ⏱ {durationText} after previous event
                    </div>
                )}

                {metaEntries.length > 0 && (
                    <div style={{ marginTop: '13px', display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                        {metaEntries.map(([key, value]) => (
                            <MetadataPill key={key} label={prettyText(key)} value={value} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function EmptyTimeline() {
    return (
        <div
            style={{
                textAlign: 'center',
                padding: '46px 18px',
                color: UI.textMuted,
                background: UI.cardSoft,
                borderRadius: UI.radius,
                border: `1px dashed ${UI.border}`,
            }}
        >
            <div style={{ fontSize: '32px', marginBottom: '10px' }}>🕒</div>
            <div style={{ fontSize: '15px', fontWeight: 750, color: UI.text }}>
                No timeline events yet
            </div>
            <div style={{ fontSize: '13px', marginTop: '5px' }}>
                Sync the order or wait for Shopify webhooks to create lifecycle events.
            </div>
        </div>
    );
}

// ── Page component ────────────────────────────────────────────────────

export default function Timeline({ order, events = [], lifecycle }) {
    const [refreshing, setRefreshing] = React.useState(false);

    function handleRefresh() {
        setRefreshing(true);

        router.post(shopifyUrl(`/orders/${order.id}/refresh`), {}, {
            preserveScroll: true,
            onFinish: () => setRefreshing(false),
        });
    }

    const sortedEvents = [...(events ?? [])].sort((a, b) => {
        const aTime = new Date(a.happened_at ?? a.created_at ?? 0).getTime();
        const bTime = new Date(b.happened_at ?? b.created_at ?? 0).getTime();

        return aTime - bTime;
    });

    const totalAmount = `${order.currency ?? ''} ${safeNumber(order.total_price).toFixed(2)}`.trim();
    const latestEvent = order.last_event_type ? prettyText(order.last_event_type) : 'No Events';
    const currentStage = order.current_stage ?? 'Processing';

    return (
        <AppLayout>
            <div style={{ background: UI.pageBg, minHeight: '100vh', margin: '-16px', padding: '16px' }}>
                <Page
                    title={order.order_name ?? 'Order Timeline'}
                    subtitle={order.customer_name ?? 'Full order lifecycle and webhook activity'}
                    backAction={{ content: 'Orders', url: shopifyUrl('/orders') }}
                    primaryAction={
                        <Button variant="primary" loading={refreshing} onClick={handleRefresh}>
                            Refresh Order
                        </Button>
                    }
                >
                    <BlockStack gap="500">
                        {/* Hero summary */}
                        <div
                            style={{
                                background: `linear-gradient(135deg, ${UI.primary}, ${UI.blue})`,
                                borderRadius: '22px',
                                padding: '24px',
                                color: '#FFFFFF',
                                boxShadow: '0 18px 45px rgba(79, 70, 229, 0.22)',
                                overflow: 'hidden',
                                position: 'relative',
                            }}
                        >
                            <div
                                style={{
                                    position: 'absolute',
                                    width: '180px',
                                    height: '180px',
                                    right: '-60px',
                                    top: '-80px',
                                    borderRadius: '999px',
                                    background: 'rgba(255,255,255,0.12)',
                                }}
                            />
                            <InlineStack align="space-between" blockAlign="start" gap="400">
                                <div style={{ position: 'relative', zIndex: 1 }}>
                                    <div style={{ fontSize: '13px', fontWeight: 700, opacity: 0.82, textTransform: 'uppercase', letterSpacing: '0.08em' }}>
                                        Order Timeline
                                    </div>
                                    <div style={{ marginTop: '8px', fontSize: '28px', lineHeight: 1.1, fontWeight: 800 }}>
                                        {order.order_name ?? 'Order'}
                                    </div>
                                    <div style={{ marginTop: '8px', fontSize: '14px', opacity: 0.88 }}>
                                        {order.customer_name ?? 'Customer'} · {order.customer_email ?? 'No email'}
                                    </div>
                                </div>

                                <div
                                    style={{
                                        position: 'relative',
                                        zIndex: 1,
                                        background: 'rgba(255,255,255,0.14)',
                                        border: '1px solid rgba(255,255,255,0.22)',
                                        borderRadius: UI.radius,
                                        padding: '14px 16px',
                                        textAlign: 'right',
                                        minWidth: '160px',
                                        backdropFilter: 'blur(10px)',
                                    }}
                                >
                                    <div style={{ fontSize: '12px', opacity: 0.82 }}>Order Total</div>
                                    <div style={{ marginTop: '5px', fontSize: '22px', fontWeight: 800 }}>
                                        {totalAmount}
                                    </div>
                                </div>
                            </InlineStack>
                        </div>

                        {/* Metrics */}
                        <div
                            style={{
                                display: 'grid',
                                gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))',
                                gap: '14px',
                            }}
                        >
                            <MiniMetric label="Payment" value={<StatusBadge status={(order.financial_status ?? '').toLowerCase()} />} tone="green" />
                            <MiniMetric label="Fulfillment" value={<StatusBadge status={(order.fulfillment_status ?? '').toLowerCase()} />} tone="blue" />
                            <MiniMetric label="Current Stage" value={<StatusBadge status={currentStage} />} tone="indigo" />
                            <MiniMetric label="Lifecycle" value={lifecycle ?? '—'} tone="amber" />
                        </div>

                        <Layout>
                            {/* Left: Order Summary */}
                            <Layout.Section variant="oneThird">
                                <BlockStack gap="400">
                                    <ProgressTracker events={sortedEvents} />

                                    <SectionCard title="Order Summary" subtitle="Important order details">
                                        <div style={{ padding: '4px 20px 10px' }}>
                                            <InfoRow label="Order #">{order.order_name ?? '—'}</InfoRow>
                                            <InfoRow label="Customer">{order.customer_name ?? '—'}</InfoRow>
                                            <InfoRow label="Email">
                                                <span style={{ wordBreak: 'break-all' }}>{order.customer_email ?? '—'}</span>
                                            </InfoRow>
                                            <InfoRow label="Total">
                                                <strong>{totalAmount}</strong>
                                            </InfoRow>

                                            <div style={{ margin: '10px 0' }}>
                                                <Divider />
                                            </div>

                                            <InfoRow label="Payment">
                                                <StatusBadge status={(order.financial_status ?? '').toLowerCase()} />
                                            </InfoRow>
                                            <InfoRow label="Fulfillment">
                                                <StatusBadge status={(order.fulfillment_status ?? '').toLowerCase()} />
                                            </InfoRow>
                                            <InfoRow label="Current Stage">
                                                <StatusBadge status={currentStage} />
                                            </InfoRow>

                                            <div style={{ margin: '10px 0' }}>
                                                <Divider />
                                            </div>

                                            <InfoRow label="Last Event">{latestEvent}</InfoRow>
                                            <InfoRow label="Last Event At">{formatDateTime(order.last_event_at)}</InfoRow>
                                            <InfoRow label="Created">{formatDateTime(order.shopify_created_at)}</InfoRow>
                                        </div>
                                    </SectionCard>
                                </BlockStack>
                            </Layout.Section>

                            {/* Right: Timeline Events */}
                            <Layout.Section>
                                <SectionCard
                                    title="Activity Timeline"
                                    subtitle={`${sortedEvents.length} event${sortedEvents.length !== 1 ? 's' : ''} captured from sync/webhooks`}
                                >
                                    <div style={{ padding: '20px' }}>
                                        {sortedEvents.length === 0 ? (
                                            <EmptyTimeline />
                                        ) : (
                                            <BlockStack gap="0">
                                                {sortedEvents.map((event, index) => (
                                                    <TimelineEvent
                                                        key={event.id ?? `${event.event_type}-${index}`}
                                                        event={event}
                                                        isLast={index === sortedEvents.length - 1}
                                                    />
                                                ))}
                                            </BlockStack>
                                        )}
                                    </div>
                                </SectionCard>
                            </Layout.Section>
                        </Layout>
                    </BlockStack>
                </Page>
            </div>
        </AppLayout>
    );
}
