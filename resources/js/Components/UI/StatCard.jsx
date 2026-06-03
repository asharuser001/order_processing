/**
 * StatCard — Premium KPI metric card for the dashboard.
 * Features a colored left-border accent and large number display.
 */
import React from 'react';
import { T, statAccents } from '../../design/tokens';

const fmt = (v) => (v == null ? '—' : Number(v).toLocaleString());

/**
 * @param {string}  title   Card label (uppercase small text)
 * @param {*}       value   Numeric value
 * @param {string}  accent  Color key from statAccents (blue|green|amber|red|purple|indigo|teal|slate)
 * @param {boolean} alert   If true AND value > 0, renders the number in the accent color
 */
export default function StatCard({ title, value, accent = 'slate', alert = false }) {
    const color   = statAccents[accent] ?? statAccents.slate;
    const isAlert = alert && Number(value) > 0;

    return (
        <div style={{
            background:   T.surface,
            borderRadius: T.radius,
            border:       `1px solid ${T.border}`,
            borderLeft:   `3px solid ${isAlert ? color : color + '70'}`,
            boxShadow:    T.shadow,
            padding:      '16px 18px',
            position:     'relative',
            overflow:     'hidden',
        }}>
            {/* Subtle radial glow for alert-state cards */}
            {isAlert && (
                <div style={{
                    position:      'absolute',
                    inset:         0,
                    background:    `radial-gradient(ellipse at 80% 20%, ${color}12, transparent 60%)`,
                    pointerEvents: 'none',
                }} />
            )}
            <div style={{
                fontSize:      '10.5px',
                fontWeight:    '700',
                letterSpacing: '0.07em',
                textTransform: 'uppercase',
                color:         T.textMuted,
                marginBottom:  '10px',
            }}>
                {title}
            </div>
            <div style={{
                fontSize:      '28px',
                fontWeight:    '700',
                color:         isAlert ? color : T.text,
                lineHeight:    '1',
                letterSpacing: '-0.02em',
            }}>
                {fmt(value)}
            </div>
        </div>
    );
}
