/**
 * ErrorState.jsx — Shown when an API request fails.
 * Displays a retry button and the error message.
 */

import React from 'react';
import { Banner, Button, BlockStack } from '@shopify/polaris';

export default function ErrorState({
    message = 'Something went wrong. Please try again.',
    onRetry,
}) {
    return (
        <div style={{ padding: '20px' }}>
            <Banner
                title="Request failed"
                status="critical"
                action={onRetry ? { content: 'Try again', onAction: onRetry } : undefined}
            >
                <p>{message}</p>
            </Banner>
        </div>
    );
}
