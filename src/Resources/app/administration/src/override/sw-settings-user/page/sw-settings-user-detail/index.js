import template from './sw-settings-user-detail.html.twig';
import './sw-settings-user-detail.scss';

const { Component } = Shopware;

Component.override('sw-settings-user-detail', {
    template,

    data() {
        return {
            httpClient: null,
            isLoading2Fa: false,
            generatedSecret: null,
            generatedSecretUrl: null,
            oneTimePassword: '',
            oneTimePasswordError: ''
        };
    },

    created() {
        this.syncService = Shopware.Service('syncService');
        this.httpClient = this.syncService.httpClient;
    },

    methods: {
        generateSecret() {
            this.isLoading2Fa = true;
            this.httpClient.get('rl-2fa/generate-secret', {
                params: {
                    holder: this.user.username
                }
            })
                .then((response) => {
                    this.isLoading2Fa = false;
                    this.generatedSecret = response.data.secret;
                    this.generatedSecretUrl = response.data.qrUrl;
                });
        },

        validateAndSaveOneTimePassword() {
            this.isLoading2Fa = true;
            this.httpClient.post('rl-2fa/validate-secret', {
                secret: this.generatedSecret,
                code: this.oneTimePassword
            }).then((response) => {
                this.isLoading2Fa = false;
                if (response.data.status === 'OK') {
                    this.saveOneTimePassword();
                }
            }).catch((error) => {
                this.isLoading2Fa = false;
                this.oneTimePasswordError = error.response.data.error;
            });
        },

        saveOneTimePassword() {
            if (!this.user.customFields) {
                this.$set(this.user, 'customFields', {});
            }

            this.user.customFields.rl_2fa_secret = this.generatedSecret;
            this.onSave();
        },

        disable2FA() {
            if (!this.user.customFields) {
                this.$set(this.user, 'customFields', {});
            }

            this.user.customFields.rl_2fa_secret = '';
            this.onSave();
        }
    }
});
