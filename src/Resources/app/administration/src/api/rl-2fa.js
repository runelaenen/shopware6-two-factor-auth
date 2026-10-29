const { ApiService } = Shopware.Classes;

export default class Rl2fa extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/rl-2fa') {
        super(httpClient, loginService, apiEndpoint);
    }

    getSecret(holder) {
        const apiRoute = `${this.getApiBasePath()}/generate-secret`;

        return this.httpClient
            .get(apiRoute, {
                params: { holder },
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getUserStatus(userId) {
        return this.sendRequest('get', `user/${userId}/status`);
    }

    saveUserSecret(userId, secret, code) {
        return this.sendRequest('post', `user/${userId}/secret`, {
            secret,
            code,
        });
    }

    removeUserSecret(userId) {
        return this.sendRequest('delete', `user/${userId}/secret`);
    }

    getCustomerStatus(customerId) {
        return this.sendRequest('get', `customer/${customerId}/status`);
    }

    removeCustomerSecret(customerId) {
        return this.sendRequest('delete', `customer/${customerId}/secret`);
    }

    sendRequest(method, path, data = undefined) {
        return this.httpClient
            .request({
                method,
                url: `${this.getApiBasePath()}/${path}`,
                data,
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getUserVerificationTicket() {
        const apiRoute = `${this.getApiBasePath()}/user-verification-ticket`;

        return this.httpClient
            .post(apiRoute, {}, { headers: this.getBasicHeaders() })
            .then((response) => {
                return ApiService.handleResponse(response).ticket;
            });
    }
}
