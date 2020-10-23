import template from './sw-users-permissions-user-detail.html.twig';

const { Component } = Shopware;

if (Component.getComponentRegistry().has('sw-users-permissions-user-detail')) {
    Component.override('sw-users-permissions-user-detail', {
        template,
    });
}
