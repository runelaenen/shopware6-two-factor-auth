import template from './sw-profile-index.html.twig';

const { Component } = Shopware;

if (Component.getComponentRegistry().has('sw-profile-index')) {
    Component.override('sw-profile-index', {
        template,
    });
}
