import Rl2faService from './rl-2fa';

const { Application } = Shopware;

Application.addServiceProvider('rl2faService', (container) => {
    const initContainer = Application.getContainer('init');

    return new Rl2faService(initContainer.httpClient, container.loginService);
});
