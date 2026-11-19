import template from './tinect-2fa-widget-activated.html.twig';
import './tinect-2fa-widget-activated.scss';

const RANGES = {
    '7Days': 7,
    '30Days': 30,
    '90Days': 90,
    '180Days': 180,
    '360Days': 360,
};

const DEFAULT_RANGE = '30Days';

/**
 * Customers that set up the app or chose e-mail codes per day, stacked by method, for a selectable range.
 */
export default {
    template,

    inject: ['acl', 'tinect2faService'],

    emits: ['update-settings'],

    props: {
        settings: {
            type: Object,
            required: false,
            default: () => ({}),
        },
    },

    data() {
        return {
            days: [],
            selectedRange: Object.hasOwn(RANGES, this.settings.range)
                ? this.settings.range
                : DEFAULT_RANGE,
            fromDate: null,
            isLoading: true,
        };
    },

    computed: {
        salesChannelId() {
            return this.settings.salesChannelId ?? null;
        },

        availableRanges() {
            return Object.keys(RANGES);
        },

        defaultRangeIndex() {
            return Math.max(
                0,
                this.availableRanges.indexOf(this.selectedRange)
            );
        },

        totals() {
            return this.days.reduce(
                (sum, day) => ({
                    totp: sum.totp + day.totp,
                    email: sum.email + day.email,
                }),
                { totp: 0, email: 0 }
            );
        },

        formattedTotal() {
            return new Intl.NumberFormat().format(
                this.totals.totp + this.totals.email
            );
        },

        formattedBreakdown() {
            const format = new Intl.NumberFormat();

            return this.$t('tinect-2fa.dashboard.activated.breakdown', {
                totp: format.format(this.totals.totp),
                email: format.format(this.totals.email),
            });
        },

        chartSeries() {
            return [
                {
                    name: this.$t('tinect-2fa.dashboard.activated.seriesTotp'),
                    data: this.points('totp'),
                },
                {
                    name: this.$t('tinect-2fa.dashboard.activated.seriesEmail'),
                    data: this.points('email'),
                },
            ];
        },

        chartOptions() {
            return {
                chart: {
                    stacked: true,
                },
                dataLabels: {
                    enabled: false,
                },
                legend: {
                    show: true,
                    position: 'bottom',
                },
                xaxis: {
                    type: 'datetime',
                    min: this.fromDate?.getTime(),
                    labels: {
                        datetimeUTC: false,
                    },
                },
                yaxis: {
                    min: 0,
                    forceNiceScale: true,
                    labels: {
                        formatter: (value) => Math.round(value).toString(),
                    },
                },
            };
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

            const days = RANGES[this.selectedRange];
            const fromDate = new Date();
            fromDate.setHours(0, 0, 0, 0);
            fromDate.setDate(fromDate.getDate() - days);

            this.isLoading = true;
            this.fromDate = fromDate;

            try {
                const result = await this.tinect2faService.getDashboard(
                    'activated-per-day',
                    {
                        days,
                        salesChannelId: this.salesChannelId,
                        timeZone: this.timeZone(),
                    }
                );

                this.days = result.days;
            } catch {
                this.days = [];
            } finally {
                this.isLoading = false;
            }
        },

        points(method) {
            return this.days.map((day) => ({
                x: Date.parse(`${day.date}T00:00:00`),
                y: day[method],
            }));
        },

        timeZone() {
            return (
                Shopware.Store.get('session').currentUser?.timeZone ??
                Intl.DateTimeFormat().resolvedOptions().timeZone
            );
        },

        onRangeUpdate(range) {
            if (!Object.hasOwn(RANGES, range)) {
                return;
            }

            this.selectedRange = range;
            this.$emit('update-settings', { range });
            this.load();
        },
    },
};
