/**
 * Design tokens — Order Processing Timeline Viewer
 * Single source of truth for all visual constants.
 */

export const T = {
    // ── Backgrounds ──────────────────────────────────────────────────
    bg:         '#f4f6f8',
    surface:    '#ffffff',
    border:     '#e3e5e8',
    borderSoft: '#f0f2f4',

    // ── Text ─────────────────────────────────────────────────────────
    text:      '#1a1f2e',
    textSub:   '#6b7280',
    textMuted: '#9ca3af',

    // ── Accent ───────────────────────────────────────────────────────
    accent:     '#4f46e5',
    accentHov:  '#4338ca',
    accentBg:   '#eef2ff',
    accentText: '#3730a3',

    // ── Status badge palette ─────────────────────────────────────────
    badge: {
        success: { text: '#065f46', bg: '#ecfdf5', ring: '#a7f3d0' },
        warning: { text: '#92400e', bg: '#fffbeb', ring: '#fde68a' },
        danger:  { text: '#991b1b', bg: '#fef2f2', ring: '#fecaca' },
        info:    { text: '#1e40af', bg: '#eff6ff', ring: '#bfdbfe' },
        neutral: { text: '#374151', bg: '#f9fafb', ring: '#e5e7eb' },
        purple:  { text: '#5b21b6', bg: '#f5f3ff', ring: '#ddd6fe' },
        orange:  { text: '#9a3412', bg: '#fff7ed', ring: '#fed7aa' },
        teal:    { text: '#134e4a', bg: '#f0fdfa', ring: '#99f6e4' },
        cyan:    { text: '#155e75', bg: '#ecfeff', ring: '#a5f3fc' },
        rose:    { text: '#9f1239', bg: '#fff1f2', ring: '#fecdd3' },
    },

    // ── Shadows ───────────────────────────────────────────────────────
    shadow:    '0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04)',
    shadowMd:  '0 4px 12px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.04)',
    shadowLg:  '0 10px 25px rgba(0,0,0,0.07), 0 4px 8px rgba(0,0,0,0.03)',
    shadowHov: '0 6px 20px rgba(0,0,0,0.10), 0 2px 6px rgba(0,0,0,0.05)',

    // ── Border radius ─────────────────────────────────────────────────
    radius:     '10px',
    radiusSm:   '6px',
    radiusMd:   '8px',
    radiusLg:   '14px',
    radiusPill: '999px',
};

// Left-border accent colors for stat cards
export const statAccents = {
    blue:   '#3b82f6',
    green:  '#10b981',
    amber:  '#f59e0b',
    red:    '#ef4444',
    purple: '#8b5cf6',
    indigo: '#6366f1',
    teal:   '#14b8a6',
    slate:  '#94a3b8',
};

// Sync status display config
export const syncStatus = {
    not_started: { variant: 'neutral', label: 'Not Synced'  },
    running:     { variant: 'cyan',    label: 'Syncing…'    },
    completed:   { variant: 'success', label: 'Up to Date'  },
    failed:      { variant: 'danger',  label: 'Sync Failed' },
};
