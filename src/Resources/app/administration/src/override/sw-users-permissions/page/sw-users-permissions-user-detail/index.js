import template from './sw-users-permissions-user-detail.html.twig';

const { Component } = Shopware;
/**
 * Starting from 6.4 'sw-users-permissions-user-detail' is the component to override
 */
Component.override('sw-settings-user-detail', {
    template,
});
