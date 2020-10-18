import enGB from './snippet/en-GB.json';
import nlNL from './snippet/nl-NL.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('nl-NL', nlNL);

import './component/rl-user-otp';
import './override/sw-login/view/sw-login-login';
import './override/sw-profile/page/sw-profile-index';
import './override/sw-users-permissions/page/sw-users-permissions-user-detail';
import './override/sw-customer/component/sw-customer-base-info';
