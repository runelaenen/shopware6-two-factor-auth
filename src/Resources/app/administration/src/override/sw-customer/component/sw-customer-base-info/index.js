import template from './sw-customer-base-info.twig';

export default {
    template,

    inject: ['tinect2faService'],

    data() {
        return {
            twoFactorAuthenticationMethod: null,
            twoFactorAuthenticationForced: false,
        };
    },

    computed: {
        twoFactorAuthenticationLabel() {
            if (this.twoFactorAuthenticationMethod === 'totp') {
                return this.$tc('tinect-2fa.customer.method.totp');
            }

            if (this.twoFactorAuthenticationMethod === 'email') {
                return this.$tc(
                    this.twoFactorAuthenticationForced
                        ? 'tinect-2fa.customer.method.email-forced'
                        : 'tinect-2fa.customer.method.email'
                );
            }

            return this.$tc('tinect-2fa.customer.method.none');
        },

        // Enforced e-mail codes are no choice of the customer, so there is nothing to remove.
        twoFactorAuthenticationRemovable() {
            return (
                this.twoFactorAuthenticationMethod !== null &&
                !this.twoFactorAuthenticationForced
            );
        },
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            this.loadTwoFactorAuthenticationStatus();
        },

        loadTwoFactorAuthenticationStatus() {
            this.twoFactorAuthenticationMethod = null;
            this.twoFactorAuthenticationForced = false;
            if (!this.customer?.id) {
                return;
            }

            this.tinect2faService
                .getCustomerStatus(this.customer.id)
                .then((response) => {
                    this.twoFactorAuthenticationMethod = response.method ?? null;
                    this.twoFactorAuthenticationForced =
                        response.forced === true;
                })
                .catch(() => {});
        },

        disable2FA() {
            this.tinect2faService
                .removeCustomerSecret(this.customer.id)
                .then(() => {
                    this.loadTwoFactorAuthenticationStatus();
                });
        },
    },
};
