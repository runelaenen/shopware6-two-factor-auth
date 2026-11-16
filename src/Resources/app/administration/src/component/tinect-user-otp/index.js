import template from './tinect-user-otp.html.twig';
import './tinect-user-otp.scss';

/**
 * @component-example
 * <tinect-user-otp :user="user" :isLoading="isLoading"></tinect-user-otp>
 */
export default {
    template,

    inject: ['tinect2faService'],

    props: {
        user: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            isLoading2Fa: false,
            enabled: false,
            generatedSecret: null,
            generatedSecretUrl: null,
            oneTimePassword: '',
            oneTimePasswordError: '',
            pendingAction: null,
        };
    },

    watch: {
        'user.id': {
            immediate: true,
            handler() {
                this.loadStatus();
            },
        },
    },

    methods: {
        loadStatus() {
            this.enabled = false;
            this.generatedSecret = null;
            this.generatedSecretUrl = null;

            if (!this.user?.id) {
                return;
            }

            this.isLoading2Fa = true;

            this.tinect2faService
                .getUserStatus(this.user.id)
                .then((response) => {
                    this.enabled = response.enabled === true;
                })
                .finally(() => {
                    this.isLoading2Fa = false;
                });
        },

        generateSecret() {
            this.isLoading2Fa = true;

            this.tinect2faService
                .getSecret(this.user.username)
                .then((response) => {
                    this.generatedSecret = response.secret;
                    this.generatedSecretUrl = response.qrUrl;
                })
                .finally(() => {
                    this.isLoading2Fa = false;
                });
        },

        validateAndSaveOneTimePassword() {
            this.oneTimePasswordError = '';
            this.pendingAction = 'save';
        },

        disable2FA() {
            this.pendingAction = 'remove';
        },

        onVerified() {
            const action = this.pendingAction;
            this.pendingAction = null;
            this.isLoading2Fa = true;

            const request =
                action === 'save'
                    ? this.tinect2faService.saveUserSecret(
                          this.user.id,
                          this.generatedSecret,
                          this.oneTimePassword
                      )
                    : this.tinect2faService.removeUserSecret(this.user.id);

            request
                .then(() => {
                    this.enabled = action === 'save';
                    this.generatedSecret = null;
                    this.generatedSecretUrl = null;
                    this.oneTimePassword = '';
                })
                .catch((error) => {
                    this.oneTimePasswordError =
                        error?.response?.data?.error ??
                        error?.response?.data?.errors?.[0]?.detail ??
                        error.message;
                })
                .finally(() => {
                    this.isLoading2Fa = false;
                });
        },

        onCloseVerifyModal() {
            this.pendingAction = null;
        },
    },
};
