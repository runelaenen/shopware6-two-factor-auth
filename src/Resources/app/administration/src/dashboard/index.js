/**
 * Widgets for the optional FroshAdminDashboard plugin.
 *
 * Plugin bundles are loaded in parallel, so `Shopware.FroshDashboard` may not exist yet when this file runs.
 * The widgets are therefore registered right before the dashboard grid is created. Without FroshAdminDashboard
 * the override is never resolved and nothing happens.
 */

const GROUP_ID = 'tinect-2fa';

const salesChannelSetting = {
    name: 'salesChannelId',
    type: 'entity',
    entity: 'sales_channel',
    label: 'tinect-2fa.dashboard.salesChannel',
    helpText: 'tinect-2fa.dashboard.salesChannelHelp',
    default: null,
};

let registered = false;

function registerWidgets() {
    const dashboard = Shopware.FroshDashboard;

    if (registered || !dashboard) {
        return;
    }

    registered = true;

    dashboard.registerGroup({
        id: GROUP_ID,
        label: 'tinect-2fa.dashboard.group',
        position: 41,
    });

    dashboard.registerWidget({
        id: 'tinect-2fa-widget-summary',
        label: 'tinect-2fa.dashboard.summary.label',
        description: 'tinect-2fa.dashboard.summary.description',
        icon: 'regular-shield',
        component: 'tinect-2fa-widget-summary',
        defaultSize: 'medium',
        supportedSizes: ['small', 'medium', 'large', 'full'],
        group: GROUP_ID,
        acl: ['customer.viewer'],
        settings: [salesChannelSetting],
    });

    dashboard.registerWidget({
        id: 'tinect-2fa-widget-activated',
        label: 'tinect-2fa.dashboard.activated.label',
        description: 'tinect-2fa.dashboard.activated.description',
        icon: 'regular-chart-line',
        component: 'tinect-2fa-widget-activated',
        defaultSize: 'medium',
        supportedSizes: ['medium', 'large', 'full'],
        group: GROUP_ID,
        acl: ['customer.viewer'],
        settings: [salesChannelSetting],
    });

    dashboard.registerWidget({
        id: 'tinect-2fa-widget-recent',
        label: 'tinect-2fa.dashboard.recent.label',
        description: 'tinect-2fa.dashboard.recent.description',
        icon: 'regular-list',
        component: 'tinect-2fa-widget-recent',
        defaultSize: 'large',
        supportedSizes: ['medium', 'large', 'full'],
        group: GROUP_ID,
        acl: ['customer.viewer'],
        settings: [
            salesChannelSetting,
            {
                name: 'limit',
                type: 'select',
                label: 'tinect-2fa.dashboard.recent.limit',
                default: 10,
                options: [
                    { value: 5, label: '5' },
                    { value: 10, label: '10' },
                    { value: 20, label: '20' },
                    { value: 50, label: '50' },
                ],
            },
        ],
    });
}

Shopware.Component.register(
    'tinect-2fa-widget-summary',
    () => import('./tinect-2fa-widget-summary')
);
Shopware.Component.register(
    'tinect-2fa-widget-activated',
    () => import('./tinect-2fa-widget-activated')
);
Shopware.Component.register(
    'tinect-2fa-widget-recent',
    () => import('./tinect-2fa-widget-recent')
);

Shopware.Component.override('frosh-dashboard-grid', {
    beforeCreate() {
        registerWidgets();
    },
});
