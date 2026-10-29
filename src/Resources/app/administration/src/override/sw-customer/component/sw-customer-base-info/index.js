import template from './sw-customer-base-info.twig';

export default {
    template,

    inject: ['rl2faService'],

    data() {
        return {
            twoFactorAuthenticationActive: false,
        };
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            this.twoFactorAuthenticationActive = false;
            if (!this.customer?.id) {
                return;
            }

            this.rl2faService
                .getCustomerStatus(this.customer.id)
                .then((response) => {
                    this.twoFactorAuthenticationActive = response.enabled === true;
                })
                .catch(() => {});
        },

        disable2FA() {
            this.rl2faService.removeCustomerSecret(this.customer.id).then(() => {
                this.twoFactorAuthenticationActive = false;
            });
        },
    },
};
