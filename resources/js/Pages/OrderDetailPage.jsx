import React, { useState, useEffect, useCallback } from 'react';
import { Page, Layout, Badge, Banner, Text } from '@shopify/polaris';
import { useParams, useNavigate } from 'react-router-dom';
import { createAuthenticatedClient, api } from '../Services/api';
import LoadingState from '../Components/Common/LoadingState';
import ErrorState from '../Components/Common/ErrorState';

// ── Event type config ────────────────────────────────────────────────────

const EVENT_STYLE = {
    created:   { color: '#5c6ac4', bg: '#f4f5fa', emoji: '🛒', label: 'Order Created' },
    updated:   { color: '#637381', bg: '#f4f6f8', emoji: '✏️',  label: 'Order Updated' },
    paid:      { color: '#108043', bg: '#f0f7ed', emoji: '💳', label: 'Payment Received' },
    fulfilled: { color: '#006e52', bg: '#ecf5f2', emoji: '📦', label: 'Order Fulfilled' },
    cancelled: { color: '#de3618', bg: '#fdf3f2', emoji: '✕',  label: 'Order Cancelled' },
};

// ── Timeline event item ──────────────────────────────────────────────────

function TimelineEvent({ event, isLast }) {
    const s = EVENT_STYLE[event.event_type] ?? { color: '#637381', bg: '#f4f6f8', emoji: '📌', label: event.event_label };

    return (
        <div style={{ display: 'flex', gap: '16px' }}>
            {/* dot + line */}
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flexShrink: 0 }}>
                <div style={{
                    width: '36px', height: '36px', borderRadius: '50%',
                    background: s.bg, border: `2px solid ${s.color}`,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: '15px', flexShrink: 0,
                }}>
                    {s.emoji}
                </div>
                {!isLast && (
                    <div style={{ width: '2px', flexGrow: 1, background: '#e1e3e5', marginTop: '4px', minHeight: '24px' }} />
                )}
            </div>

            {/* content */}
            <div style={{ paddingBottom: isLast ? '4px' : '28px', flex: 1, paddingTop: '6px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '4px' }}>
                    <div style={{ fontSize: '14px', fontWeight: '600', color: s.color }}>
                        {event.event_label}
                    </div>
                    {event.happened_at_formatted && (
                        <div style={{ fontSize: '12px', color: '#6d7175' }}>
                            {new Date(event.happened_at_formatted).toLocaleString()}
                        </div>
                    )}
                </div>
                {event.duration_label && (
                    <div style={{ fontSize: '12px', color: '#8c9196', marginTop: '4px' }}>
                        ⏱ {event.duration_label} since previous event
                    </div>
                )}
                {event.source === 'sync' && (
                    <div style={{
                        display: 'inline-block', marginTop: '6px',
                        padding: '2px 8px', borderRadius: '4px',
                        background: '#e8f4fd', color: '#1a73e8',
                        fontSize: '11px', fontWeight: '500',
                    }}>
                        Via Sync
                    </div>
                )}
            </div>
        </div>
    );
}

// ── KV row helper ────────────────────────────────────────────────────────

function InfoRow({ label, children, last }) {
    return (
        <div style={{
            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
            padding: '12px 0',
            borderBottom: last ? 'none' : '1px solid #f1f2f3',
        }}>
            <div style={{ fontSize: '13px', color: '#6d7175', fontWeight: '500' }}>{label}</div>
            <div style={{ fontSize: '14px', color: '#202223', fontWeight: '500', textAlign: 'right' }}>{children}</div>
        </div>
    );
}

// ── Main page ────────────────────────────────────────────────────────────

export default function OrderDetailPage() {
    const { id }   = useParams();
    const client   = createAuthenticatedClient();
    const navigate = useNavigate();

    const [data, setData]       = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError]     = useState(null);

    const fetchTimeline = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.getOrderTimeline(client, id);
            setData(res.data.data);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load order timeline.');
        } finally {
            setLoading(false);
        }
    }, [id]);

    useEffect(() => { fetchTimeline(); }, [fetchTimeline]);

    if (loading) return <LoadingState label="Loading timeline..." />;
    if (error)   return <ErrorState message={error} onRetry={fetchTimeline} />;

    const { summary, timeline } = data;

    const financialColor = {
        paid:     '#108043', pending: '#916a00',
        voided:   '#6d7175', refunded: '#1a73e8',
    }[summary?.financial_status] ?? '#6d7175';

    const financialBadgeStatus = {
        paid:     'success', pending: 'attention',
        voided:   'default', refunded: 'info',
    }[summary?.financial_status] ?? 'default';

    return (
        <Page
            title={summary?.order_name ?? `Order #${id}`}
            backAction={{ content: 'Orders', onAction: () => navigate('/orders') }}
        >
            {/* ── Missing events warning ──────────────────── */}
            {summary?.missing_events?.length > 0 && (
                <div style={{ marginBottom: '20px' }}>
                    <Banner title="Missing timeline events" status="warning">
                        <p>
                            Expected but not yet received:{' '}
                            <strong>{summary.missing_events.join(', ')}</strong>. These may arrive via webhook later.
                        </p>
                    </Banner>
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: '300px 1fr', gap: '20px', alignItems: 'start' }}>

                {/* ── Summary card ──────────────────────────── */}
                <div style={{
                    background: '#fff', borderRadius: '12px',
                    border: '1px solid #e1e3e5',
                    boxShadow: '0 1px 4px rgba(0,0,0,0.05)',
                    overflow: 'hidden',
                }}>
                    {/* card header */}
                    <div style={{ padding: '16px 20px', borderBottom: '1px solid #f1f2f3', background: '#fafbfb' }}>
                        <div style={{ fontSize: '13px', fontWeight: '600', color: '#6d7175', textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                            Order Summary
                        </div>
                    </div>

                    {/* total highlight */}
                    <div style={{ padding: '20px', borderBottom: '1px solid #f1f2f3', textAlign: 'center' }}>
                        <div style={{ fontSize: '11px', color: '#6d7175', fontWeight: '600', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: '6px' }}>
                            Order Total
                        </div>
                        <div style={{ fontSize: '32px', fontWeight: '700', color: '#202223' }}>
                            {summary?.currency} {parseFloat(summary?.total_price ?? 0).toFixed(2)}
                        </div>
                        <div style={{ marginTop: '8px' }}>
                            <Badge status={financialBadgeStatus}>
                                {summary?.financial_status ?? 'unknown'}
                            </Badge>
                        </div>
                    </div>

                    {/* KV rows */}
                    <div style={{ padding: '0 20px' }}>
                        <InfoRow label="Customer">{summary?.customer_name ?? '—'}</InfoRow>
                        <InfoRow label="Email">
                            <span style={{ fontSize: '13px' }}>{summary?.customer_email ?? '—'}</span>
                        </InfoRow>
                        <InfoRow label="Fulfillment">{summary?.fulfillment_status ?? 'unfulfilled'}</InfoRow>
                        {summary?.total_duration_label && (
                            <InfoRow label="Lifecycle" last>{summary.total_duration_label}</InfoRow>
                        )}
                    </div>
                </div>

                {/* ── Timeline card ──────────────────────────── */}
                <div style={{
                    background: '#fff', borderRadius: '12px',
                    border: '1px solid #e1e3e5',
                    boxShadow: '0 1px 4px rgba(0,0,0,0.05)',
                    overflow: 'hidden',
                }}>
                    {/* card header */}
                    <div style={{ padding: '16px 20px', borderBottom: '1px solid #f1f2f3', background: '#fafbfb', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <div style={{ fontSize: '13px', fontWeight: '600', color: '#6d7175', textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                            Order Timeline
                        </div>
                        <div style={{
                            fontSize: '12px', fontWeight: '500', color: '#5c6ac4',
                            background: '#f4f5fa', padding: '3px 10px', borderRadius: '100px',
                        }}>
                            {timeline.length} event{timeline.length !== 1 ? 's' : ''}
                        </div>
                    </div>

                    <div style={{ padding: '24px' }}>
                        {timeline.length === 0 ? (
                            <div style={{ textAlign: 'center', padding: '40px 0', color: '#6d7175' }}>
                                <div style={{ fontSize: '32px', marginBottom: '12px' }}>📭</div>
                                <div style={{ fontSize: '14px' }}>No timeline events recorded yet.</div>
                                <div style={{ fontSize: '13px', color: '#8c9196', marginTop: '4px' }}>Events will appear here as webhooks arrive.</div>
                            </div>
                        ) : (
                            timeline.map((event, index) => (
                                <TimelineEvent
                                    key={event.id}
                                    event={event}
                                    isLast={index === timeline.length - 1}
                                />
                            ))
                        )}
                    </div>
                </div>
            </div>
        </Page>
    );
}
