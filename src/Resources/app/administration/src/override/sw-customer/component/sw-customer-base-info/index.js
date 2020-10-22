import template from './sw-customer-base-info.twig';

const { Component } = Shopware;

if (Component.getComponentRegistry().has('sw-customer-base-info')) {
    Component.override('sw-customer-base-info', {
        template,

        computed: {
            twoFactorAuthenticationActive() {
                if (!this.customer.customFields) {
                    return false;
                }

                if (this.customer.customFields.rl_2fa_secret) {
                    return true;
                }

                return false;
            }
        },

        methods: {
            disable2FA() {
                if (!this.customer.customFields) {
                    this.$set(this.customer, 'customFields', {});
                }

                this.customer.customFields.rl_2fa_secret = '';
            }
        }
    });
}
