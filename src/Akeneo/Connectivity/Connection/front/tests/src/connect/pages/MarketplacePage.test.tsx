import React from 'react';
import '@testing-library/jest-dom/extend-expect';
import {useFeatureFlags} from '@src/shared/feature-flags';
import {historyMock, renderWithProviders} from '../../../test-utils';
import {screen, waitFor} from '@testing-library/react';
import {MarketplacePage} from '@src/connect/pages/MarketplacePage';
import {Marketplace} from '@src/connect/components/Marketplace';
import userEvent from '@testing-library/user-event';

jest.mock('@src/shared/feature-flags/use-feature-flags', () => ({
    useFeatureFlags: jest.fn().mockImplementation(() => ({
        isEnabled: () => true,
    })),
}));
jest.mock('@src/connect/hooks/use-fetch-extensions', () => ({
    useFetchExtensions: jest.fn().mockImplementation(() => () => Promise.resolve([])),
}));
jest.mock('@src/connect/hooks/use-fetch-apps', () => ({
    useFetchApps: jest.fn().mockImplementation(() => () => Promise.resolve([])),
}));
jest.mock('@src/connect/components/Marketplace', () => ({
    Marketplace: jest.fn().mockImplementation(() => null),
}));

test('It redirect when the "create a test app" button is clicked', async () => {
    (useFeatureFlags as jest.Mock).mockImplementation(() => ({
        isEnabled: () => true,
    }));

    renderWithProviders(<MarketplacePage />);

    await waitFor(() => expect(Marketplace).toHaveBeenCalled());
    userEvent.click(
        screen.getByText('akeneo_connectivity.connection.connect.marketplace.test_apps.create_a_custom_app')
    );

    expect(historyMock.history.location.pathname).toBe('/akeneo_connectivity_connection_connect_custom_apps_create');
});
