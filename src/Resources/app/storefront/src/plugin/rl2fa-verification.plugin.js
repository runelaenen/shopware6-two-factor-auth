import DomAccess from 'src/helper/dom-access.helper';
import HttpClient from 'src/service/http-client.service';
import ElementLoadingIndicatorUtil from 'src/utility/loading-indicator/element-loading-indicator.util';

export default class Rl2faVerificationPlugin extends window.PluginBaseClass {
    static options = {
        /** Selector for the submit button of the verification step */
        buttonSelector: '.account-profile-2fa-setup-verify',

        /** Selector for the code input field of the verification step */
        codeInputSelector: '[name=otpVerification]',

        /** Selector for the secret input field of the verification step */
        secretInputSelector: '[name=otpSecret]',

        /** Selector for the error message wrapper of the verification step */
        errorMessageWrapperSelector: '.rl2fa-setup-verification-message',

        /** Selector for the error message content of the verification step */
        errorMessageSelector: '.rl2fa-setup-verification-message .alert-content',

        /** Class to toggle visibility (display: none) */
        invisibleClass: 'd-none',

        /** Url to verify and save the account secret */
        verificationUrl: ''
    };

    init() {
        this._httpClient = new HttpClient();
        this._submitButton = DomAccess.querySelector(this.el, this.options.buttonSelector);

        this._codeInput = DomAccess.querySelector(this.el, this.options.codeInputSelector);
        this._secretInput = DomAccess.querySelector(this.el, this.options.secretInputSelector);

        this._errorMessageWrapper = DomAccess.querySelector(this.el, this.options.errorMessageWrapperSelector);
        this._errorMessage = DomAccess.querySelector(this.el, this.options.errorMessageSelector);

        this.initListeners();
    }

    initListeners() {
        this._submitButton.addEventListener('click', this.onSubmitButtonClick.bind(this));
    }

    onSubmitButtonClick() {
        ElementLoadingIndicatorUtil.create(this.el);
        this._errorMessageWrapper.classList.add(this.options.invisibleClass);

        this._httpClient.post(this.options.verificationUrl, JSON.stringify({
            code: this._codeInput.value,
            secret: this._secretInput.value,
        }), (response) => {
            const data = JSON.parse(response);

            if (data.status === 'OK') {
                window.location.reload();
                return;
            }

            ElementLoadingIndicatorUtil.remove(this.el);

            this.showErrorMessage(data.error ? data.error : 'Something went wrong!');
        });
    }

    showErrorMessage(message) {
        this._errorMessageWrapper.classList.remove(this.options.invisibleClass);
        this._errorMessage.innerHTML = message;
    }
}
