/**
 * LoadingState.jsx — Full-page loading spinner.
 * Used while API requests are in-flight.
 */

import React from 'react';
import { Frame, Loading, Spinner, BlockStack, Text } from '@shopify/polaris';

export default function LoadingState({ label = 'Loading...' }) {
    return (
        <div style={{ padding: '60px', textAlign: 'center' }}>
            <BlockStack gap="4" align="center">
                <Spinner accessibilityLabel={label} size="large" />
                <Text variant="bodyMd" color="subdued">{label}</Text>
            </BlockStack>
        </div>
    );
}
