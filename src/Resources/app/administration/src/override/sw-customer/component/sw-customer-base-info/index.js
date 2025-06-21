import template from './sw-customer-base-info.twig';

export default {
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
};
