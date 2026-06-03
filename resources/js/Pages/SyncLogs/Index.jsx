/**
 * SyncLogs/Index.jsx — Inertia page
 *
 * Premium sync history screen.
 * Shows the history of order sync operations.
 * Receives `logs` (paginator) from SyncLogController.
 */

import React from 'react';
import { router } from '@inertiajs/react';
import {
    Page,
    Card,
    Text,
    Badge,
    BlockStack,
    InlineStack,
    Pagination,
} from '@shopify/polaris';
import AppLayout from '../../Components/Layout/AppLayout';
import { shopifyUrl } from '../../utils/shopifyRoute';

// ── Theme tokens ──────────────────────────────────────────────────────────

const C = {
    surface: '#FFFFFF',
    border: '#E3E8F2',
    borderSoft: '#EEF2F7',
    text: '#172033',
    textSub: '#475569',
    textMuted: '#64748B',
    primary: '#3B5BDB',
    primaryDark: '#2444B8',
    primarySoft: '#EEF2FF',
    blueSoft: '#EFF6FF',
    green: '#16A34A',
    greenSoft: '#ECFDF3',
    amber: '#D97706',
    amberSoft: '#FFF7ED',
    red: '#DC2626',
    redSoft: '#FEF2F2',
    purple: '#7C3AED',
    purpleSoft: '#F5F3FF',
    slateSoft: '#F8FAFC',
    shadow: '0 10px 30px rgba(15, 23, 42, 0.06)',
    shadowSm: '0 4px 14px rgba(15, 23, 42, 0.05)',
    radius: '18px',
    radiusMd: '14px',
};

const statusConfig = {
    running: {
        label: 'Running',
        tone: 'attention',
        color: C.amber,
        bg: C.amberSoft,
    },
    completed: {
        label: 'Completed',
        tone: 'success',
        color: C.green,
        bg: C.greenSoft,
    },
    failed: {
        label: 'Failed',
        tone: 'critical',
        color: C.red,
        bg: C.redSoft,
    },
};

function cleanStatus(status) {
    return String(status ?? 'running').toLowerCase();
}

function StatusPill({ status }) {
    const key = cleanStatus(status);
    const cfg = statusConfig[key] ?? {
        label: status || 'Unknown',
        tone: 'enabled',
        color: C.textMuted,
        bg: C.slateSoft,
    };

    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '7px',
                borderRadius: '999px',
                padding: '5px 10px',
                fontSize: '12px',
                lineHeight: 1,
                fontWeight: 750,
                color: cfg.color,
                background: cfg.bg,
                border: `1px solid ${cfg.bg}`,
                whiteSpace: 'nowrap',
                textTransform: 'capitalize',
            }}
        >
            <span
                style={{
                    width: '7px',
                    height: '7px',
                    borderRadius: '50%',
                    background: cfg.color,
                    boxShadow: `0 0 0 3px ${cfg.bg}`,
                    flexShrink: 0,
                }}
            />
            {cfg.label}
        </span>
    );
}

function TypePill({ type }) {
    return (
        <span
            style={{
                display: 'inline-flex',
                alignItems: 'center',
                borderRadius: '999px',
                padding: '5px 10px',
                background: C.blueSoft,
                color: C.primaryDark,
                fontSize: '12px',
                fontWeight: 750,
                whiteSpace: 'nowrap',
                textTransform: 'capitalize',
            }}
        >
            {String(type || 'orders').replace(/_/g, ' ')}
        </span>
    );
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString() : '—';
}

function formatError(message) {
    if (!message) return '—';
    return message.length > 95 ? `${message.substring(0, 95)}…` : message;
}

function getDuration(startedAt, completedAt) {
    if (!startedAt || !completedAt) return '—';

    const start = new Date(startedAt).getTime();
    const end = new Date(completedAt).getTime();

    if (!Number.isFinite(start) || !Number.isFinite(end) || end < start) return '—';

    const seconds = Math.floor((end - start) / 1000);

    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return minutes > 0 ? `${hours}h ${minutes}m` : `${hours}h`;
}

function MetricCard({ label, value, tone = 'neutral', suffix }) {
    const toneMap = {
        success: { bg: C.greenSoft, color: C.green, icon: '✓' },
        failed: { bg: C.redSoft, color: C.red, icon: '!' },
        warning: { bg: C.amberSoft, color: C.amber, icon: '↻' },
        purple: { bg: C.purpleSoft, color: C.purple, icon: '●' },
        neutral: { bg: C.primarySoft, color: C.primary, icon: '●' },
    };

    const cfg = toneMap[tone] ?? toneMap.neutral;

    return (
        <div
            style={{
                background: C.surface,
                border: `1px solid ${C.border}`,
                borderRadius: C.radiusMd,
                padding: '14px 16px',
                boxShadow: C.shadowSm,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: '12px',
                minHeight: '74px',
            }}
        >
            <div>
                <div style={{ fontSize: '12px', color: C.textMuted, fontWeight: 750 }}>
                    {label}
                </div>
                <div style={{ fontSize: '24px', color: C.text, fontWeight: 850, marginTop: '4px' }}>
                    {value ?? 0}
                    {suffix && (
                        <span style={{ fontSize: '12px', color: C.textMuted, marginLeft: '4px', fontWeight: 700 }}>
                            {suffix}
                        </span>
                    )}
                </div>
            </div>
            <div
                style={{
                    width: '38px',
                    height: '38px',
                    borderRadius: '12px',
                    background: cfg.bg,
                    color: cfg.color,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontWeight: 900,
                    fontSize: '16px',
                }}
            >
                {cfg.icon}
            </div>
        </div>
    );
}

const TH = ({ children, align = 'left' }) => (
    <th
        style={{
            padding: '14px 16px',
            textAlign: align,
            fontSize: '11px',
            fontWeight: 850,
            color: C.textMuted,
            textTransform: 'uppercase',
            letterSpacing: '0.08em',
            background: C.slateSoft,
            borderBottom: `1px solid ${C.border}`,
            whiteSpace: 'nowrap',
        }}
    >
        {children}
    </th>
);

const TD = ({ children, align = 'left', muted = false }) => (
    <td
        style={{
            padding: '15px 16px',
            textAlign: align,
            verticalAlign: 'middle',
            fontSize: '13px',
            color: muted ? C.textMuted : C.textSub,
            borderBottom: `1px solid ${C.borderSoft}`,
        }}
    >
        {children}
    </td>
);

function NumberChip({ value, tone = 'neutral' }) {
    const toneMap = {
        success: { bg: C.greenSoft, color: C.green },
        failed: { bg: C.redSoft, color: C.red },
        neutral: { bg: C.slateSoft, color: C.textSub },
    };

    const cfg = toneMap[tone] ?? toneMap.neutral;

    return (
        <span
            style={{
                display: 'inline-flex',
                justifyContent: 'center',
                minWidth: '34px',
                padding: '5px 9px',
                borderRadius: '999px',
                background: cfg.bg,
                color: cfg.color,
                fontWeight: 800,
                fontSize: '12px',
            }}
        >
            {value ?? 0}
        </span>
    );
}

function EmptySyncState() {
    return (
        <div
            style={{
                padding: '54px 24px',
                textAlign: 'center',
                background: C.surface,
            }}
        >
            <div
                style={{
                    width: '56px',
                    height: '56px',
                    borderRadius: '18px',
                    background: C.primarySoft,
                    color: C.primary,
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '24px',
                    fontWeight: 800,
                    marginBottom: '14px',
                }}
            >
                ↻
            </div>
            <Text as="h3" variant="headingMd">
                No sync logs yet
            </Text>
            <div style={{ marginTop: '6px', color: C.textMuted, fontSize: '13px' }}>
                Order synchronization runs will appear here after you start a sync.
            </div>
        </div>
    );
}

// ── Page component ────────────────────────────────────────────────────────

export default function SyncLogsIndex({ logs }) {
    const [hovered, setHovered] = React.useState(null);

    const items = logs?.data ?? [];

    const totals = React.useMemo(() => {
        const completed = items.filter((log) => cleanStatus(log.status) === 'completed').length;
        const running = items.filter((log) => cleanStatus(log.status) === 'running').length;
        const failed = items.filter((log) => cleanStatus(log.status) === 'failed').length;
        const syncedRecords = items.reduce((sum, log) => sum + Number(log.synced_records ?? 0), 0);

        return {
            total: logs?.total ?? items.length,
            completed,
            running,
            failed,
            syncedRecords,
        };
    }, [items, logs?.total]);

    function goTo(url) {
        if (!url) return;
        router.get(shopifyUrl(url), {}, { preserveScroll: true, preserveState: true });
    }

    return (
        <AppLayout>
            <Page
                title="Sync Logs"
                subtitle="Review order synchronization runs, record counts, failures, and processing duration."
                backAction={{ content: 'Dashboard', url: shopifyUrl('/dashboard') }}
            >
                <BlockStack gap="500">
                    {/* Hero */}
                    <div
                        style={{
                            background: `linear-gradient(135deg, ${C.primaryDark} 0%, ${C.primary} 52%, #6D7CF6 100%)`,
                            color: '#fff',
                            borderRadius: '22px',
                            padding: '22px',
                            boxShadow: '0 18px 45px rgba(59, 91, 219, 0.24)',
                            position: 'relative',
                            overflow: 'hidden',
                        }}
                    >
                        <div
                            style={{
                                position: 'absolute',
                                right: '-70px',
                                top: '-80px',
                                width: '210px',
                                height: '210px',
                                borderRadius: '50%',
                                background: 'rgba(255,255,255,0.14)',
                            }}
                        />
                        <div
                            style={{
                                position: 'absolute',
                                right: '70px',
                                bottom: '-90px',
                                width: '170px',
                                height: '170px',
                                borderRadius: '50%',
                                background: 'rgba(255,255,255,0.10)',
                            }}
                        />

                        <div
                            style={{
                                position: 'relative',
                                zIndex: 1,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: '18px',
                                flexWrap: 'wrap',
                            }}
                        >
                            <div>
                                <div
                                    style={{
                                        fontSize: '12px',
                                        fontWeight: 850,
                                        letterSpacing: '0.12em',
                                        textTransform: 'uppercase',
                                        opacity: 0.82,
                                    }}
                                >
                                    Sync operations
                                </div>
                                <div style={{ fontSize: '25px', fontWeight: 850, marginTop: '6px' }}>
                                    Order synchronization history
                                </div>
                                <div
                                    style={{
                                        fontSize: '13px',
                                        opacity: 0.82,
                                        marginTop: '7px',
                                        maxWidth: '640px',
                                    }}
                                >
                                    Track every sync run, check imported record counts, spot failed jobs, and review timing from one clean operational screen.
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Metrics */}
                    <div
                        style={{
                            display: 'grid',
                            gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))',
                            gap: '14px',
                        }}
                    >
                        <MetricCard label="Total Runs" value={totals.total} />
                        <MetricCard label="Completed" value={totals.completed} tone="success" />
                        <MetricCard label="Running" value={totals.running} tone="warning" />
                        <MetricCard label="Failed" value={totals.failed} tone="failed" />
                        <MetricCard label="Synced Records" value={totals.syncedRecords} tone="purple" />
                    </div>

                    {/* Table */}
                    <Card padding="0">
                        <div
                            style={{
                                padding: '18px 20px',
                                borderBottom: `1px solid ${C.borderSoft}`,
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                gap: '12px',
                                flexWrap: 'wrap',
                            }}
                        >
                            <div>
                                <Text as="h2" variant="headingMd">
                                    Sync run history
                                </Text>
                                <div style={{ marginTop: '4px', color: C.textMuted, fontSize: '13px' }}>
                                    Monitor order sync progress, imported totals, and errors.
                                </div>
                            </div>

                            <InlineStack gap="200" align="end">
                                <Badge tone="info">{logs?.total ?? 0} total</Badge>
                                {totals.failed > 0 && <Badge tone="critical">{totals.failed} failed</Badge>}
                                {totals.running > 0 && <Badge tone="attention">{totals.running} running</Badge>}
                            </InlineStack>
                        </div>

                        {items.length === 0 ? (
                            <EmptySyncState />
                        ) : (
                            <>
                                <div style={{ overflowX: 'auto' }}>
                                    <table
                                        style={{
                                            width: '100%',
                                            borderCollapse: 'separate',
                                            borderSpacing: 0,
                                            minWidth: '980px',
                                        }}
                                    >
                                        <thead>
                                            <tr>
                                                <TH>Type</TH>
                                                <TH>Status</TH>
                                                <TH align="center">Total</TH>
                                                <TH align="center">Synced</TH>
                                                <TH align="center">Failed</TH>
                                                <TH>Started At</TH>
                                                <TH>Completed At</TH>
                                                <TH>Duration</TH>
                                                <TH>Error</TH>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.map((log) => {
                                                const isFailed = cleanStatus(log.status) === 'failed';

                                                return (
                                                    <tr
                                                        key={log.id}
                                                        onMouseEnter={() => setHovered(log.id)}
                                                        onMouseLeave={() => setHovered(null)}
                                                        style={{
                                                            background:
                                                                hovered === log.id
                                                                    ? C.slateSoft
                                                                    : isFailed
                                                                        ? 'linear-gradient(90deg, #FEF2F2 0%, #FFFFFF 22%)'
                                                                        : C.surface,
                                                            transition: 'background 0.15s ease',
                                                        }}
                                                    >
                                                        <TD>
                                                            <TypePill type={log.sync_type} />
                                                        </TD>
                                                        <TD>
                                                            <StatusPill status={log.status} />
                                                        </TD>
                                                        <TD align="center">
                                                            <NumberChip value={log.total_records ?? 0} />
                                                        </TD>
                                                        <TD align="center">
                                                            <NumberChip value={log.synced_records ?? 0} tone="success" />
                                                        </TD>
                                                        <TD align="center">
                                                            <NumberChip value={log.failed_records ?? 0} tone={(log.failed_records ?? 0) > 0 ? 'failed' : 'neutral'} />
                                                        </TD>
                                                        <TD muted>{formatDate(log.started_at)}</TD>
                                                        <TD muted>{formatDate(log.completed_at)}</TD>
                                                        <TD muted>{getDuration(log.started_at, log.completed_at)}</TD>
                                                        <TD>
                                                            <span
                                                                title={log.error_message || ''}
                                                                style={{
                                                                    color: log.error_message ? C.red : C.textMuted,
                                                                    fontSize: '12px',
                                                                    lineHeight: 1.45,
                                                                }}
                                                            >
                                                                {formatError(log.error_message)}
                                                            </span>
                                                        </TD>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>

                                <div
                                    style={{
                                        padding: '14px 18px',
                                        borderTop: `1px solid ${C.borderSoft}`,
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center',
                                        gap: '12px',
                                        flexWrap: 'wrap',
                                        background: C.slateSoft,
                                    }}
                                >
                                    <span style={{ fontSize: '13px', color: C.textMuted, fontWeight: 650 }}>
                                        {logs?.total
                                            ? `Showing ${logs.from ?? 0}–${logs.to ?? 0} of ${logs.total} sync logs`
                                            : 'No sync logs'}
                                    </span>

                                    <Pagination
                                        hasPrevious={!!logs?.prev_page_url}
                                        hasNext={!!logs?.next_page_url}
                                        onPrevious={() => goTo(logs?.prev_page_url)}
                                        onNext={() => goTo(logs?.next_page_url)}
                                    />
                                </div>
                            </>
                        )}
                    </Card>
                </BlockStack>
            </Page>
        </AppLayout>
    );
}
