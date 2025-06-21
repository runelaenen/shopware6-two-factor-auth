const { ApiService } = Shopware.Classes;

export default class Rl2fa extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/rl-2fa') {
        super(httpClient, loginService, apiEndpoint);
    }

    getSecret(holder) {
        const apiRoute = `${this.getApiBasePath()}/generate-secret`;

        return this.httpClient.get(
            apiRoute,
            {params: { holder }, headers: this.getBasicHeaders()}
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    validateSecret(secret, code) {
        const apiRoute = `${this.getApiBasePath()}/validate-secret`;

        return this.httpClient.post(
            apiRoute,
            {secret, code},
            {headers: this.getBasicHeaders()}
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
};
