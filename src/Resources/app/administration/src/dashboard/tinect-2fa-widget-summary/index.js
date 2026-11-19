import template from './tinect-2fa-widget-summary.html.twig';
import './tinect-2fa-widget-summary.scss';

/**
 * Key figures: protected customers (and their share) by method, plus administration users with an app.
 */
export default {
    template,

    inject: ['acl', 'tinect2faService'],

    props: {
        settings: {
            type: Object,
            required: false,
            default: () => ({}),
        },
    },

    data() {
        return {
            summary: null,
            isLoading: true,
            hasError: false,
        };
    },

    computed: {
        salesChannelId() {
            return this.settings.salesChannelId ?? null;
        },

        protectedCustomers() {
            if (!this.summary) {
                return 0;
            }

            return this.summary.totp + this.summary.email + this.summary.enforced;
        },

        tiles() {
            if (!this.summary) {
                return [];
            }

            return [
                {
                    key: 'protected',
                    value: this.formatNumber(this.protectedCustomers),
                    label: this.$t('tinect-2fa.dashboard.summary.protected'),
                    hint: this.$t('tinect-2fa.dashboard.summary.share', {
                        rate: this.formatPercent(
                            this.share(
                                this.protectedCustomers,
                                this.summary.customers
                            )
                        ),
                        total: this.formatNumber(this.summary.customers),
                    }),
                },
                {
                    key: 'totp',
                    value: this.formatNumber(this.summary.totp),
                    label: this.$t('tinect-2fa.dashboard.summary.totp'),
                },
                {
                    key: 'email',
                    value: this.formatNumber(this.summary.email),
                    label: this.$t('tinect-2fa.dashboard.summary.email'),
                },
                {
                    key: 'enforced',
                    value: this.formatNumber(this.summary.enforced),
                    label: this.$t('tinect-2fa.dashboard.summary.enforced'),
                    hint: this.$t(
                        'tinect-2fa.dashboard.summary.enforcedHint'
                    ),
                },
                {
                    key: 'users',
                    value: this.formatNumber(this.summary.usersWithTotp),
                    label: this.$t('tinect-2fa.dashboard.summary.users'),
                    hint: this.$t('tinect-2fa.dashboard.summary.usersHint', {
                        total: this.formatNumber(this.summary.users),
                    }),
                },
            ];
        },
    },

    watch: {
        salesChannelId: {
            immediate: true,
            handler() {
                this.load();
            },
        },
    },

    methods: {
        async load() {
            if (!this.acl.can('customer.viewer')) {
                this.isLoading = false;
                return;
            }

            this.isLoading = true;
            this.hasError = false;

            try {
                this.summary = await this.tinect2faService.getDashboard(
                    'summary',
                    { salesChannelId: this.salesChannelId }
                );
            } catch {
                this.summary = null;
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },

        share(part, total) {
            return total ? part / total : 0;
        },

        formatNumber(value) {
            return new Intl.NumberFormat().format(value);
        },

        formatPercent(value) {
            return new Intl.NumberFormat(undefined, {
                style: 'percent',
                maximumFractionDigits: 1,
            }).format(value);
        },
    },
};
