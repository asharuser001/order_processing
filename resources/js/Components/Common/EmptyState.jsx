/**
 * EmptyState.jsx — Shown when a data table has no rows.
 * Accepts a heading, description, action button, and optional image.
 */

import React from 'react';
import { EmptyState as PolarisEmptyState } from '@shopify/polaris';

export default function EmptyState({
    heading = 'No data found',
    description = 'There is nothing to show here yet.',
    actionLabel,
    onAction,
    image = 'https://cdn.shopify.com/s/files/1/0757/9955/files/empty-state.svg',
}) {
    const action = actionLabel && onAction
        ? { content: actionLabel, onAction }
        : undefined;

    return (
        <PolarisEmptyState
            heading={heading}
            image={image}
            action={action}
        >
            <p>{description}</p>
        </PolarisEmptyState>
    );
}
