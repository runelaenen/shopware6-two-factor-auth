import template from './sw-login-login.html.twig';

const {Component, Context, Application} = Shopware;

Component.override('sw-login-login', {
    template,

    data() {
        return {
            rememberOtpPassword: '',
            showOtpForm: false,
            otp: '',
        };
    },

    methods: {
        loginUserWithPasswordAndOtp() {
            this.$emit('is-loading');

            return this.loginWithOtp(this.username, this.password, this.otp)
                .then(() => {
                    this.handleLoginSuccess();
                    this.$emit('is-not-loading');
                })
                .catch((response) => {
                    this.password = '';
                    this.otp = '';
                    this.showOtpForm = false;

                    this.handleLoginError(response);
                    this.$emit('is-not-loading');
                });
        },

        loginWithOtp(user, pass, otp) {
            return Application.getContainer('init').httpClient.post('/oauth/token', {
                grant_type: 'password',
                client_id: 'administration',
                scopes: 'write',
                username: user,
                password: pass,
                rl_2fa_otp: otp,
            }, {
                baseURL: Context.api.apiPath,
            }).then((response) => {
                const auth = this.loginService.setBearerAuthentication({
                    access: response.data.access_token,
                    refresh: response.data.refresh_token,
                    expiry: response.data.expires_in,
                });

                window.localStorage.setItem('redirectFromLogin', 'true');

                return auth;
            });
        },

        loginUserWithPassword() {
            this.rememberOtpPassword = this.password;
            this.$super('loginUserWithPassword');
        },

        handleLoginError(error) {
            if (error.response.data.errors[0].detail !== 'request-otp') {
                this.$super('handleLoginError', error);
                return;
            }

            this.password = this.rememberOtpPassword;
            this.showOtpForm = true;
        },
    },
});
