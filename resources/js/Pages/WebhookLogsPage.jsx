/**
 * WebhookLogsPage.jsx — View all webhook events with retry support.
 *
 * Uses direct API calls (not Inertia props) so it keeps its own Frame
 * wrapper for Toast support. AppLayout is NOT used here to avoid nesting Frame.
 *
 * Shows: topic, order ID, status, attempts, error message, processed_at
 */

import React, { useState, useEffect, useCallback } from 'react';
import {
    Page, IndexTable, Text, Button,
    Select, Pagination, Tooltip, Toast, Frame,
} from '@shopify/polaris';
import { createAuthenticatedClient, api } from '../Services/api';
import LoadingState from '../Components/Common/LoadingState';
import ErrorState from '../Components/Common/ErrorState';
import EmptyState from '../Components/Common/EmptyState';
import StatusBadge from '../Components/UI/StatusBadge';
import { T } from '../design/tokens';

const STATUS_OPTIONS = [
    { label: 'All Statuses', value: '' },
    { label: 'Pending',      value: 'pending' },
    { label: 'Processing',   value: 'processing' },
    { label: 'Success',      value: 'success' },
    { label: 'Failed',       value: 'failed' },
];

export default function WebhookLogsPage() {
    const client = createAuthenticatedClient();

    const [events,       setEvents]       = useState([]);
    const [meta,         setMeta]         = useState(null);
    const [loading,      setLoading]      = useState(true);
    const [error,        setError]        = useState(null);
    const [page,         setPage]         = useState(1);
    const [statusFilter, setStatusFilter] = useState('');
    const [toast,        setToast]        = useState(null);
    const [retrying,     setRetrying]     = useState(null);

    const fetchEvents = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.getWebhookEvents(client, {
                status:   statusFilter || undefined,
                page,
                per_page: 20,
            });
            setEvents(res.data.data.data ?? []);
            setMeta(res.data.data);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load webhook events.');
        } finally {
            setLoading(false);
        }
    }, [statusFilter, page]);

    useEffect(() => { fetchEvents(); }, [fetchEvents]);

    const handleRetry = async (id) => {
        setRetrying(id);
        try {
            const res = await api.retryWebhookEvent(client, id);
            setToast({ content: res.data.message, error: false });
            fetchEvents();
        } catch (err) {
            setToast({
                content: err.response?.data?.message || 'Retry failed.',
                error: true,
            });
        } finally {
            setRetrying(null);
        }
    };

    const resourceName = { singular: 'event', plural: 'events' };

    const rows = events.map((event, index) => {
        const payload = event.payload ?? {};
        const orderId = payload.id ?? payload.order_id ?? '—';
        const isFailed = event.status === 'failed';

        return (
            <IndexTable.Row
                id={String(event.id)}
                key={event.id}
                position={index}
                tone={isFailed ? 'critical' : undefined}
            >
                <IndexTable.Cell>
                    <Text variant="bodySm" fontWeight="semibold" as="span">
                        {event.topic}
                    </Text>
                </IndexTable.Cell>
                <IndexTable.Cell>
                    <span style={{ fontSize: '12px', color: T.textSub }}>{orderId}</span>
                </IndexTable.Cell>
                <IndexTable.Cell>
                    <StatusBadge status={event.status} />
                </IndexTable.Cell>
                <IndexTable.Cell>
                    <span style={{ fontSize: '12px', color: T.textSub }}>{event.attempts}</span>
                </IndexTable.Cell>
                <IndexTable.Cell>
                    {event.error_message ? (
                        <Tooltip content={event.error_message}>
                            <span style={{ fontSize: '12px', color: '#991b1b', cursor: 'default' }}>
                                {event.error_message.substring(0, 55)}
                                {event.error_message.length > 55 ? '…' : ''}
                            </span>
                        </Tooltip>
                    ) : (
                        <span style={{ color: T.textMuted }}>—</span>
                    )}
                </IndexTable.Cell>
                <IndexTable.Cell>
                    <span style={{ fontSize: '12px', color: T.textMuted }}>
                        {event.processed_at
                            ? new Date(event.processed_at).toLocaleString()
                            : '—'}
                    </span>
                </IndexTable.Cell>
                <IndexTable.Cell>
                    {isFailed && (
                        <Button
                            variant="plain"
                            loading={retrying === event.id}
                            onClick={() => handleRetry(event.id)}
                        >
                            Retry
                        </Button>
                    )}
                </IndexTable.Cell>
            </IndexTable.Row>
        );
    });

    return (
        <Frame>
            {toast && (
                <Toast
                    content={toast.content}
                    error={toast.error}
                    onDismiss={() => setToast(null)}
                />
            )}

            <Page
                title="Webhook Logs"
                subtitle="Monitor and retry Shopify webhook deliveries"
            >
                {/* ── Container card ─────────────────────────── */}
                <div style={{
                    background:   T.surface,
                    borderRadius: T.radius,
                    border:       `1px solid ${T.border}`,
                    boxShadow:    T.shadow,
                    overflow:     'hidden',
                }}>
                    {/* ── Toolbar ──────────────────────────────── */}
                    <div style={{
                        padding:      '14px 20px',
                        borderBottom: `1px solid ${T.borderSoft}`,
                        background:   '#fafbfc',
                        display:      'flex',
                        alignItems:   'center',
                        gap:          '12px',
                        flexWrap:     'wrap',
                    }}>
                        <span style={{ fontSize: '13px', fontWeight: '600', color: T.text, flex: 1 }}>
                            Webhook Events
                        </span>
                        <div style={{ minWidth: '180px' }}>
                            <Select
                                label="Filter by status"
                                labelHidden
                                options={STATUS_OPTIONS}
                                value={statusFilter}
                                onChange={(v) => { setStatusFilter(v); setPage(1); }}
                            />
                        </div>
                    </div>

                    {/* ── Content ───────────────────────────────── */}
                    {loading ? (
                        <LoadingState label="Loading webhook logs…" />
                    ) : error ? (
                        <ErrorState message={error} onRetry={fetchEvents} />
                    ) : events.length === 0 ? (
                        <EmptyState
                            heading="No webhook events"
                            description="Webhook events will appear here as Shopify sends them."
                        />
                    ) : (
                        <>
                            <IndexTable
                                resourceName={resourceName}
                                itemCount={events.length}
                                headings={[
                                    { title: 'Topic' },
                                    { title: 'Order ID' },
                                    { title: 'Status' },
                                    { title: 'Attempts' },
                                    { title: 'Error' },
                                    { title: 'Processed At' },
                                    { title: '' },
                                ]}
                                selectable={false}
                            >
                                {rows}
                            </IndexTable>

                            <div style={{
                                padding:        '12px 20px',
                                borderTop:      `1px solid ${T.borderSoft}`,
                                display:        'flex',
                                justifyContent: 'center',
                            }}>
                                <Pagination
                                    hasPrevious={page > 1}
                                    onPrevious={() => setPage((p) => p - 1)}
                                    hasNext={(meta?.last_page ?? 1) > page}
                                    onNext={() => setPage((p) => p + 1)}
                                    label={`Page ${page} of ${meta?.last_page ?? 1}`}
                                />
                            </div>
                        </>
                    )}
                </div>
            </Page>
        </Frame>
    );
}

