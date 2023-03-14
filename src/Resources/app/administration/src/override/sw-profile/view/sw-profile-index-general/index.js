import template from './sw-profile-index-general.html.twig';

const { Component } = Shopware;

if (Component.getComponentRegistry().has('sw-profile-index-general')) {
    Component.override('sw-profile-index-general', {
        template,
    });
}
