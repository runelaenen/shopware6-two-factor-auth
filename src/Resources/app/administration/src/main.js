import enGB from './snippet/en-GB.json';
import nlNL from './snippet/nl-NL.json';

import './override/sw-login/view/sw-login-login';
import './override/sw-settings-user/page/sw-settings-user-detail';
import './override/sw-customer/component/sw-customer-base-info';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('nl-NL', nlNL);
