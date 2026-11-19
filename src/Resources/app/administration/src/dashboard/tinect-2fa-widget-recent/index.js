import template from './tinect-2fa-widget-recent.html.twig';
import './tinect-2fa-widget-recent.scss';

/**
 * The customers that most recently set up the app or chose e-mail codes.
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
            activations: [],
            isLoading: true,
            hasError: false,
        };
    },

    computed: {
        salesChannelId() {
            return this.settings.salesChannelId ?? null;
        },

        limit() {
            return this.settings.limit ?? 10;
        },

        params() {
            return {
                salesChannelId: this.salesChannelId,
                limit: this.limit,
            };
        },

        columns() {
            return [
                {
                    property: 'customer',
                    label: this.$t('tinect-2fa.dashboard.recent.columnCustomer'),
                    primary: true,
                },
                {
                    property: 'method',
                    label: this.$t('tinect-2fa.dashboard.recent.columnMethod'),
                },
                {
                    property: 'createdAt',
                    label: this.$t(
                        'tinect-2fa.dashboard.recent.columnCreatedAt'
                    ),
                },
            ];
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    watch: {
        params: {
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
                const result = await this.tinect2faService.getDashboard(
                    'recent',
                    this.params
                );

                this.activations = result.activations;
            } catch {
                this.activations = [];
                this.hasError = true;
            } finally {
                this.isLoading = false;
            }
        },

        methodLabel(method) {
            return this.$t(`tinect-2fa.dashboard.method.${method}`);
        },

        // Database values are stored in UTC without timezone suffix.
        formatDate(value) {
            return value ? this.dateFilter(`${value.replace(' ', 'T')}Z`) : '-';
        },
    },
};
