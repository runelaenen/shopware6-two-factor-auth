import template from './sw-profile-index.html.twig';

const { Component } = Shopware;

Component.override('sw-profile-index', {
    template,
});
