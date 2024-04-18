import template from './sw-customer-base-info.twig';

const { Component } = Shopware;

if (Component.getComponentRegistry().has('sw-customer-base-info')) {
    Component.override('sw-customer-base-info', {
        template,

        computed: {
            twoFactorAuthenticationActive() {
                return !!this.customer.customFields?.rl_2fa_secret;
            },
        },

        methods: {
            disable2FA() {
                if (!this.customer.customFields) {
                    this.$set(this.customer, 'customFields', {});
                }

                this.customer.customFields.rl_2fa_secret = '';
            },
        },
    });
}
