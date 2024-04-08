import template from './rl-user-otp.html.twig';
import './rl-user-otp.scss';
import Rl2faService from "../../api/rl-2fa";

const {Component} = Shopware;

/**
 * @component-example
 * <rl-user-otp :user="user" :isLoading="isLoading" :onSave="onSave"></rl-user-otp>
 */
Component.register('rl-user-otp', {
    template,

    inject: ['rl2faService'],

    props: {
        user: {
            type: Object,
            required: true,
        },
        isLoading: {
            type: Boolean,
            required: true,
        },
        onSave: {
            type: Function,
            required: true,
        },
    },

    data() {
        return {
            httpClient: null,
            isLoading2Fa: false,
            generatedSecret: null,
            generatedSecretUrl: null,
            oneTimePassword: '',
            oneTimePasswordError: '',
        };
    },

    created() {
        this.syncService = Shopware.Service('syncService');
        this.httpClient = this.syncService.httpClient;
    },

    methods: {
        generateSecret() {
            this.isLoading2Fa = true;

            this.rl2faService.getSecret(this.user.username).then((response) => {
                this.isLoading2Fa = false;
                this.generatedSecret = response.secret;
                this.generatedSecretUrl = response.qrUrl;
            });
        },

        validateAndSaveOneTimePassword() {
            this.isLoading2Fa = true;

            this.rl2faService.validateSecret(this.generatedSecret, this.oneTimePassword).then((response) => {
                this.isLoading2Fa = false;
                if (response.status === 'OK') {
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
        },
    },
});
