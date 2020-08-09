import enGB from './snippet/en-GB.json';
import nlNL from './snippet/nl-NL.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('nl-NL', nlNL);

import './override/sw-login/view/sw-login-login';
import './override/sw-settings-user/page/sw-settings-user-detail';
