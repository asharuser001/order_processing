/**
 * StatusBadge — Premium soft-pill badge for status indicators.
 * Maps raw Shopify status strings to semantic color variants automatically.
 */
import React from 'react';
import { T } from '../../design/tokens';

// Raw status key → [colorVariant, displayLabel]
const MAP = {
    // Financial
    paid:             ['success', 'Paid'],
    pending:          ['warning', 'Pending'],
    partially_paid:   ['info',    'Partially Paid'],
    refunded:         ['purple',  'Refunded'],
    voided:           ['neutral', 'Voided'],
    authorized:       ['info',    'Authorized'],

    // Fulfillment
    fulfilled:        ['success', 'Fulfilled'],
    partial:          ['info',    'Partial'],
    unfulfilled:      ['warning', 'Unfulfilled'],
    restocked:        ['neutral', 'Restocked'],

    // System / webhook statuses
    success:          ['success', 'Success'],
    failed:           ['danger',  'Failed'],
    processing:       ['info',    'Processing'],
    running:          ['cyan',    'Running'],
    not_started:      ['neutral', 'Not Started'],
    completed:        ['success', 'Completed'],

    // Event source
    webhook:          ['teal',    'Via Webhook'],
    sync:             ['info',    'Via Sync'],
    webhook_retry:    ['warning', 'Via Retry'],
    retry:            ['warning', 'Via Retry'],

    // Stage labels (from OrderStageService)
    'Completed':                   ['success', 'Completed'],
    'Ready to Fulfill':            ['teal',    'Ready to Fulfill'],
    'In Fulfillment':              ['info',    'In Fulfillment'],
    'Awaiting Payment':            ['warning', 'Awaiting Payment'],
    'Awaiting Balance':            ['warning', 'Awaiting Balance'],
    'Cancelled':                   ['danger',  'Cancelled'],
    'Refunded':                    ['purple',  'Refunded'],
    'Payment Delayed':             ['danger',  'Payment Delayed'],
    'Fulfillment Delayed':         ['danger',  'Fulfillment Delayed'],
    'Partially Fulfilled Delayed': ['orange',  'Part. Fulfilled Delayed'],
    'Processing':                  ['neutral', 'Processing'],
};

/**
 * @param {string}  status   Raw status key (e.g. 'paid', 'fulfilled', 'Completed')
 * @param {string}  label    Override display label
 * @param {string}  variant  Force a specific color variant (skips auto-mapping)
 * @param {string}  size     'sm' | 'md'
 */
export default function StatusBadge({ status, label, variant: forced, size = 'sm' }) {
    if (!status && !forced) {
        return <span style={{ color: T.textMuted, fontSize: '12px' }}>—</span>;
    }

    const [variantKey, autoLabel] = MAP[status] ?? ['neutral', null];
    const colors  = T.badge[forced ?? variantKey] ?? T.badge.neutral;
    const display = label ?? autoLabel ?? (status ? status.replace(/_/g, ' ') : '');
    const sm      = size === 'sm';

    return (
        <span style={{
            display:       'inline-flex',
            alignItems:    'center',
            gap:           '5px',
            padding:       sm ? '2px 8px 2px 7px' : '3px 11px 3px 9px',
            borderRadius:  T.radiusPill,
            fontSize:      sm ? '11.5px' : '12.5px',
            fontWeight:    '600',
            letterSpacing: '0.02em',
            lineHeight:    '1.6',
            color:         colors.text,
            background:    colors.bg,
            border:        `1px solid ${colors.ring}`,
            whiteSpace:    'nowrap',
            userSelect:    'none',
        }}>
            <span style={{
                width:        sm ? '5px' : '6px',
                height:       sm ? '5px' : '6px',
                borderRadius: '50%',
                background:   colors.text,
                opacity:      0.5,
                flexShrink:   0,
            }} />
            {display}
        </span>
    );
}
