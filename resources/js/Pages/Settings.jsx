/**
 * Settings.jsx
 *
 * Premium settings screen.
 * Uses direct API calls (not Inertia props) for shop data.
 * Wrapped with AppLayout for consistent page background and navigation.
 */

import React, { useState, useEffect, useCallback } from 'react';
import { Page, BlockStack, Button, InlineStack, Badge, Text } from '@shopify/polaris';
import { createAuthenticatedClient, api } from '../Services/api';
import AppLayout from '../Components/Layout/AppLayout';
import LoadingState from '../Components/Common/LoadingState';
import ErrorState from '../Components/Common/ErrorState';
import StatusBadge from '../Components/UI/StatusBadge';

// ── Premium theme tokens ────────────────────────────────────────────────

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
    teal: '#0D9488',
    tealSoft: '#F0FDFA',
    slateSoft: '#F8FAFC',
    shadow: '0 10px 30px rgba(15, 23, 42, 0.06)',
    shadowSm: '0 4px 14px rgba(15, 23, 42, 0.05)',
    radius: '18px',
    radiusMd: '14px',
};

// ── Webhook topics displayed in the settings card ───────────────────────

const WEBHOOKS = [
    { topic: 'orders/create', label: 'Order Created', color: C.green, bg: C.greenSoft },
    { topic: 'orders/updated', label: 'Order Updated', color: C.primary, bg: C.primarySoft },
    { topic: 'orders/paid', label: 'Payment Completed', color: C.green, bg: C.greenSoft },
    { topic: 'orders/fulfilled', label: 'Order Fulfilled', color: C.teal, bg: C.tealSoft },
    { topic: 'orders/cancelled', label: 'Order Cancelled', color: C.red, bg: C.redSoft },
    { topic: 'orders/delete', label: 'Order Deleted', color: C.textMuted, bg: C.slateSoft },
    { topic: 'app/uninstalled', label: 'App Uninstalled', color: C.textMuted, bg: C.slateSoft },
];

// ── Helper sub-components ───────────────────────────────────────────────

function GlassHero({ shop, onRefresh, loading }) {
    const syncStatus = shop?.order_sync_status ?? 'not_started';

    return (
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
                        App configuration
                    </div>
                    <div style={{ fontSize: '25px', fontWeight: 850, marginTop: '6px' }}>
                        Store & integration settings
                    </div>
                    <div
                        style={{
                            fontSize: '13px',
                            opacity: 0.84,
                            marginTop: '7px',
                            maxWidth: '640px',
                            lineHeight: 1.55,
                        }}
                    >
                        Review your connected Shopify store, order sync state, registered webhooks, and storefront extension details.
                    </div>

                    <div style={{ marginTop: '14px', display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                        <span style={heroChipStyle}>{shop?.shopify_domain ?? shop?.name ?? 'Store not loaded'}</span>
                        <span style={heroChipStyle}>Sync: {String(syncStatus).replace(/_/g, ' ')}</span>
                    </div>
                </div>

                <Button onClick={onRefresh} loading={loading}>
                    Refresh Settings
                </Button>
            </div>
        </div>
    );
}

const heroChipStyle = {
    display: 'inline-flex',
    alignItems: 'center',
    borderRadius: '999px',
    padding: '7px 11px',
    background: 'rgba(255,255,255,0.16)',
    color: '#fff',
    fontSize: '12px',
    fontWeight: 750,
    border: '1px solid rgba(255,255,255,0.18)',
    textTransform: 'capitalize',
};

function PremiumCard({ title, subtitle, children, action }) {
    return (
        <section
            style={{
                background: C.surface,
                border: `1px solid ${C.border}`,
                borderRadius: C.radius,
                boxShadow: C.shadow,
                overflow: 'hidden',
            }}
        >
            {(title || subtitle || action) && (
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
                        {title && (
                            <Text as="h2" variant="headingMd">
                                {title}
                            </Text>
                        )}
                        {subtitle && (
                            <div style={{ marginTop: '4px', color: C.textMuted, fontSize: '13px' }}>
                                {subtitle}
                            </div>
                        )}
                    </div>
                    {action}
                </div>
            )}
            {children}
        </section>
    );
}

function InfoRow({ label, children, last }) {
    return (
        <div
            style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                padding: '13px 20px',
                borderBottom: last ? 'none' : `1px solid ${C.borderSoft}`,
                gap: '12px',
            }}
        >
            <span style={{ fontSize: '12.5px', color: C.textMuted, fontWeight: 750, flexShrink: 0 }}>
                {label}
            </span>
            <span style={{ fontSize: '13px', color: C.text, fontWeight: 650, textAlign: 'right', wordBreak: 'break-all' }}>
                {children}
            </span>
        </div>
    );
}

function HealthCard({ label, value, tone = 'neutral', icon = '●' }) {
    const toneMap = {
        success: { bg: C.greenSoft, color: C.green },
        warning: { bg: C.amberSoft, color: C.amber },
        danger: { bg: C.redSoft, color: C.red },
        neutral: { bg: C.primarySoft, color: C.primary },
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
                <div style={{ fontSize: '18px', color: C.text, fontWeight: 850, marginTop: '4px' }}>
                    {value}
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
                {icon}
            </div>
        </div>
    );
}

function WebhookTopic({ topic, label, color, bg }) {
    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: '12px',
                padding: '12px 14px',
                borderRadius: C.radiusMd,
                background: bg,
                border: `1px solid ${color}22`,
                minHeight: '62px',
            }}
        >
            <div
                style={{
                    width: '34px',
                    height: '34px',
                    borderRadius: '12px',
                    background: '#fff',
                    color,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontWeight: 900,
                    boxShadow: '0 2px 8px rgba(15, 23, 42, 0.06)',
                }}
            >
                ✓
            </div>
            <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: '13px', color: C.text, fontWeight: 800 }}>{label}</div>
                <code
                    style={{
                        display: 'block',
                        marginTop: '3px',
                        fontSize: '12px',
                        color,
                        fontWeight: 700,
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                    }}
                >
                    {topic}
                </code>
            </div>
        </div>
    );
}

function ExtensionStep({ number, title, text }) {
    return (
        <div style={{ display: 'flex', gap: '12px', alignItems: 'flex-start' }}>
            <div
                style={{
                    width: '28px',
                    height: '28px',
                    borderRadius: '9px',
                    background: C.primarySoft,
                    color: C.primary,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontWeight: 850,
                    fontSize: '12px',
                    flexShrink: 0,
                }}
            >
                {number}
            </div>
            <div>
                <div style={{ fontSize: '13px', fontWeight: 800, color: C.text }}>{title}</div>
                <div style={{ fontSize: '12.5px', color: C.textMuted, marginTop: '3px', lineHeight: 1.55 }}>{text}</div>
            </div>
        </div>
    );
}

// ── Page component ──────────────────────────────────────────────────────

export default function Settings() {
    const client = createAuthenticatedClient();

    const [shop, setShop] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);

    const fetchShop = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await api.getShop(client);
            setShop(res.data.data);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load shop info.');
        } finally {
            setLoading(false);
        }
    }, []);

    const refreshShop = useCallback(async () => {
        setRefreshing(true);
        setError(null);
        try {
            const res = await api.getShop(client);
            setShop(res.data.data);
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to load shop info.');
        } finally {
            setRefreshing(false);
        }
    }, []);

    useEffect(() => {
        fetchShop();
    }, [fetchShop]);

    const pageContent = () => {
        if (loading) return <LoadingState label="Loading settings…" />;
        if (error) return <ErrorState message={error} onRetry={fetchShop} />;

        const syncStatus = shop?.order_sync_status ?? 'not_started';
        const hasSynced = !!shop?.order_sync;
        const syncedAt = shop?.order_synced_at
            ? new Date(shop.order_synced_at).toLocaleString()
            : 'Never synced';

        return (
            <BlockStack gap="500">
                <GlassHero shop={shop} onRefresh={refreshShop} loading={refreshing} />

                {/* Health overview */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(190px, 1fr))',
                        gap: '14px',
                    }}
                >
                    <HealthCard
                        label="Connection"
                        value={shop?.shopify_domain || shop?.name ? 'Connected' : 'Not Connected'}
                        tone={shop?.shopify_domain || shop?.name ? 'success' : 'danger'}
                        icon={shop?.shopify_domain || shop?.name ? '✓' : '!'}
                    />
                    <HealthCard
                        label="Initial Sync"
                        value={hasSynced ? 'Completed' : 'Pending'}
                        tone={hasSynced ? 'success' : 'warning'}
                        icon={hasSynced ? '✓' : '↻'}
                    />
                    <HealthCard
                        label="Sync Status"
                        value={String(syncStatus).replace(/_/g, ' ')}
                        tone={syncStatus === 'failed' ? 'danger' : syncStatus === 'completed' ? 'success' : 'warning'}
                        icon={syncStatus === 'failed' ? '!' : syncStatus === 'completed' ? '✓' : '●'}
                    />
                    <HealthCard
                        label="Webhooks"
                        value={`${WEBHOOKS.length} Topics`}
                        tone="neutral"
                        icon="↯"
                    />
                </div>

                {/* Two-column: Store Info + Sync Status */}
                <div
                    style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fit, minmax(310px, 1fr))',
                        gap: '16px',
                    }}
                >
                    <PremiumCard
                        title="Store Information"
                        subtitle="Connected Shopify store details"
                        action={<Badge tone="success">Active</Badge>}
                    >
                        <InfoRow label="Domain">{shop?.shopify_domain ?? shop?.name ?? '—'}</InfoRow>
                        <InfoRow label="Store name">{shop?.name ?? '—'}</InfoRow>
                        <InfoRow label="Email" last>{shop?.email ?? '—'}</InfoRow>
                    </PremiumCard>

                    <PremiumCard
                        title="Order Sync Status"
                        subtitle="Current synchronization state"
                        action={<StatusBadge status={syncStatus} />}
                    >
                        <InfoRow label="Status">
                            <StatusBadge status={syncStatus} />
                        </InfoRow>
                        <InfoRow label="Initial sync done">
                            <span
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: '7px',
                                    borderRadius: '999px',
                                    padding: '5px 10px',
                                    background: hasSynced ? C.greenSoft : C.amberSoft,
                                    color: hasSynced ? C.green : C.amber,
                                    fontSize: '12px',
                                    fontWeight: 800,
                                }}
                            >
                                {hasSynced ? 'Yes' : 'No'}
                            </span>
                        </InfoRow>
                        <InfoRow label="Last synced at" last>
                            {syncedAt}
                        </InfoRow>
                    </PremiumCard>
                </div>

                {/* Registered Webhooks */}
                <PremiumCard
                    title="Registered Webhooks"
                    subtitle="Shopify topics expected to deliver events to this app"
                    action={<Badge tone="info">{WEBHOOKS.length} topics</Badge>}
                >
                    <div
                        style={{
                            padding: '18px 20px',
                            display: 'grid',
                            gridTemplateColumns: 'repeat(auto-fit, minmax(230px, 1fr))',
                            gap: '12px',
                        }}
                    >
                        {WEBHOOKS.map((webhook) => (
                            <WebhookTopic key={webhook.topic} {...webhook} />
                        ))}
                    </div>
                </PremiumCard>

                {/* Technical note */}
                <div
                    style={{
                        background: C.primarySoft,
                        border: `1px solid ${C.primary}1F`,
                        borderRadius: C.radius,
                        padding: '16px 18px',
                        color: C.primaryDark,
                        fontSize: '13px',
                        lineHeight: 1.6,
                        boxShadow: C.shadowSm,
                    }}
                >
                    <strong>Note:</strong> If webhook topics or app URLs are changed, reinstall or re-register webhooks from Shopify so the live store uses the latest endpoints.
                </div>
            </BlockStack>
        );
    };

    return (
        <AppLayout>
            <Page title="Settings" subtitle="App configuration and integration status">
                {pageContent()}
            </Page>
        </AppLayout>
    );
}
