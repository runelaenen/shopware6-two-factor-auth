import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';
import frFR from './snippet/fr-FR.json';
import nlNL from './snippet/nl-NL.json';

import './api/index';
import './component/rl-user-otp';
import './override/sw-login/view/sw-login-login';
import './override/sw-profile/page/sw-profile-index';
import './override/sw-profile/view/sw-profile-index-general';
import './override/sw-users-permissions/page/sw-users-permissions-user-detail';
import './override/sw-customer/component/sw-customer-base-info';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('fr-FR', frFR);
Shopware.Locale.extend('nl-NL', nlNL);
