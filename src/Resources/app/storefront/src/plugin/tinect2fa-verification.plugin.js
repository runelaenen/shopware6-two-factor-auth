export default class Tinect2faVerificationPlugin
    extends window.PluginBaseClass
{
    static options = {
        /** Selector for the submit button of the verification step */
        buttonSelector: '.account-profile-2fa-setup-verify',

        /** Selector for the code input field of the verification step */
        codeInputSelector: '[name=otpVerification]',

        /** Selector for the password input field, only there when another factor is active */
        passwordInputSelector: '[name=otpSetupPassword]',

        /** Selector for the error message wrapper of the verification step */
        errorMessageWrapperSelector: '.tinect2fa-setup-verification-message',

        /** Selector for the error message content of the verification step */
        errorMessageSelector:
            '.tinect2fa-setup-verification-message .alert-content-container',

        /** Class to toggle visibility (display: none) */
        invisibleClass: 'd-none',

        /** Url to verify and save the account secret */
        verificationUrl: '',
    };

    init() {
        this._submitButton = this.el.querySelector(this.options.buttonSelector);

        this._codeInput = this.el.querySelector(this.options.codeInputSelector);
        this._passwordInput = this.el.querySelector(
            this.options.passwordInputSelector
        );

        this._errorMessageWrapper = this.el.querySelector(
            this.options.errorMessageWrapperSelector
        );
        this._errorMessage = this.el.querySelector(
            this.options.errorMessageSelector
        );

        this.initListeners();
    }

    initListeners() {
        this._submitButton.addEventListener(
            'click',
            this.onSubmitButtonClick.bind(this)
        );
    }

    onSubmitButtonClick() {
        this._errorMessageWrapper.classList.add(this.options.invisibleClass);
        this._submitButton.disabled = true;

        fetch(this.options.verificationUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                code: this._codeInput.value,
                password: this._passwordInput ? this._passwordInput.value : '',
            }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.status === 'OK') {
                    window.location.reload();
                    return;
                }

                this.showErrorMessage(
                    data.error ? data.error : 'Something went wrong!'
                );
            })
            .catch(() => {
                this.showErrorMessage('Something went wrong!');
            })
            .finally(() => {
                this._submitButton.disabled = false;
            });
    }

    showErrorMessage(message) {
        this._errorMessageWrapper.classList.remove(this.options.invisibleClass);
        this._errorMessage.innerHTML = message;
    }
}
