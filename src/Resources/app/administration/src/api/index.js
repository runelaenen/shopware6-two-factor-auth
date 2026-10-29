import Rl2faService from './rl-2fa';

const { Application, Context } = Shopware;

Application.addServiceProvider('rl2faService', (container) => {
    const initContainer = Application.getContainer('init');

    return new Rl2faService(initContainer.httpClient, container.loginService);
});

/**
 * Core asks for the password again (OAuth scope "user-verified") before sensitive changes, without an OTP.
 * The server only accepts that for 2FA users when it comes with a ticket from an already authenticated session.
 * loginService is created before plugins load, so the instance is patched instead of decorated.
 * verifyUserToken is replaced too, because core calls its own verifyUserByUsername directly.
 */
const loginService = Shopware.Service('loginService');

loginService.verifyUserByUsername = (username, password) => {
    return Shopware.Service('rl2faService')
        .getUserVerificationTicket()
        .catch(() => '')
        .then((ticket) => {
            return Application.getContainer('init').httpClient.post(
                '/oauth/token',
                {
                    grant_type: 'password',
                    client_id: 'administration',
                    scope: 'user-verified',
                    username,
                    password,
                    rl_2fa_ticket: ticket,
                },
                { baseURL: Context.api.apiPath }
            );
        })
        .then((response) => {
            return {
                access: response.data.access_token,
                expiry: response.data.expires_in,
                refresh: response.data.refresh_token,
            };
        });
};

loginService.verifyUserToken = (password) => {
    const username = Shopware.Store.get('session').currentUser?.username ?? '';

    return loginService.verifyUserByUsername(username, password).then(({ access }) => {
        if (typeof access === 'string') {
            return access;
        }

        throw new Error('access Token should be of type String');
    });
};
