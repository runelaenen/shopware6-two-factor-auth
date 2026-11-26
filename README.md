# Two Factor Authentication for Shopware 6
[![Latest Stable Version](https://poser.pugx.org/tinect/shopware6-two-factor-auth/v)](//packagist.org/packages/tinect/shopware6-two-factor-auth)
[![Total Downloads](https://poser.pugx.org/tinect/shopware6-two-factor-auth/downloads)](//packagist.org/packages/tinect/shopware6-two-factor-auth)
[![License](https://poser.pugx.org/tinect/shopware6-two-factor-auth/license)](//packagist.org/packages/tinect/shopware6-two-factor-auth)

![Two Factor Authentication for Shopware 6](https://user-images.githubusercontent.com/3930922/90954708-f8394c80-e476-11ea-940d-4733d4ce2588.png)

> **Fork notice**
> This is a maintained fork of [runelaenen/shopware6-two-factor-auth](https://github.com/runelaenen/shopware6-two-factor-auth),
> originally created and published by [Rune Laenen](https://github.com/runelaenen) and [Kraftware](https://www.kraftware.be).
> The original repository seems no longer maintained, so this fork continues development under the
> package name `tinect/shopware6-two-factor-auth`.

Add extra security to your Shopware 6 shop by enabling Two Factor Authentication.

Adds an extra prompt to admin- or customer-accounts in your Shopware 6 website.

## Features
 - 'Google Authenticator' provider
 - Codes by e-mail for storefront customers
 - Enforce 2FA for storefront customers (falls back to codes by e-mail)
 - Trusted devices: customers can skip the second factor on a browser for a configurable number of days
 - Storefront customer 2FA
 - Dashboard widgets for [Frosh Admin Dashboard](https://github.com/FriendsOfShopware/FroshAdminDashboard) (optional,
   group "Two-factor authentication", requires the `customer.viewer` privilege), each filterable by sales channel:
   - 2FA usage: protected customers and their share of all customers who can log in (active, no guests, double opt-in
     confirmed), split into app, chosen and enforced e-mail codes, plus administration users with an app
   - 2FA activations: chart of apps set up and e-mail codes chosen per day for the last 7 to 360 days
   - Recently activated 2FA: the latest activations with their customers and method
 - Admin user 2FA
 - Local QR code generation
 - Fully localized:
   - English
   - German
   - French
   - Dutch
   - Polish
 
## Providers
 - Authenticator apps (TOTP), for example Google Authenticator, Microsoft Authenticator, Authy, Bitwarden, ...
 - Codes by e-mail (storefront customers only)

## Installation guide

This plugin can only be installed using Composer.

```
# Install plugin using composer
composer require tinect/shopware6-two-factor-auth

# Refresh plugins & install & activate plugin
bin/console plugin:refresh
bin/console plugin:install --activate TinectTwoFactorAuth

# Build javascript files
bin/build-js.sh
```

## Codes by e-mail

Customers can choose codes by e-mail instead of an authenticator app in their profile. With "Enforce 2FA for customers"
turned on, every customer without an app gets a code by e-mail on login. The mail uses the template type
"Two-factor authentication: login code" (`tinect_2fa_email_code`), which can be edited in the administration
under Settings > Email templates. It gets the variables `customer`, `code`, `expiresInMinutes` and `salesChannel`.

A code is valid for 10 minutes and for 5 attempts. Mails per customer are throttled with Shopware's `reset_password`
rate limiter. Accounts created through registration are logged in without a code.

Customers can tick "Trust this device" on the verification page to skip the second factor on that browser for the
number of days set in the plugin configuration (0 turns the option off). Changing the password ends the trust on all
devices.

The customer detail page in the administration shows the active method. Admins with `customer:update` can remove the
app and the e-mail choice there, for example after a customer lost their phone. With enforcing turned on, the customer
then gets codes by e-mail.

Enforcing only applies to the storefront. Store API logins ask for the authenticator app code, or for an e-mail code
if the customer chose codes by e-mail.

Every app code is accepted only once, also within its 30 second window.

## Store API

Customers with 2FA enabled have to send their one-time password with the login request as `tinect2faOtp`:

```
POST /store-api/account/login
{"email": "customer@example.com", "password": "...", "tinect2faOtp": "123456"}
```

Without it the request fails with a `401` and the error code `TINECT_2FA__OTP_REQUIRED`, so the frontend knows to ask for
the code and send the login again. A wrong code returns `TINECT_2FA__OTP_INVALID`.

Customers who chose codes by e-mail log in in two steps. The first login, without a code, sends the code by e-mail and
fails with a `401`, the error code `TINECT_2FA__EMAIL_CODE_REQUIRED` and a challenge in `meta.parameters.challenge`.
Send the login again with the code and that challenge:

```
POST /store-api/account/login
{"email": "customer@example.com", "password": "...", "tinect2faOtp": "123456", "tinect2faChallenge": "..."}
```

A challenge is valid for 10 minutes and for 5 attempts. A login without code or challenge sends a new code and
replaces the old challenge; sending too many codes returns core's `CHECKOUT__CUSTOMER_AUTH_THROTTLED`. If the mail
can't be sent, the login fails with `503` and `TINECT_2FA__EMAIL_CODE_NOT_SENT`.

## Updating from 8.0 or older

Secrets used to be stored in the `rl_2fa_secret` custom field of customers and users, where the Store API and
Admin API returned them. The update moves them to their own tables and removes the custom field. Enabled accounts
keep working, but downgrading after the update turns 2FA off for those accounts.

## Development
Keep in mind that 2FA authentication will not work in the development Administration watcher mode.

## Migrating from `runelaenen/shopware6-two-factor-auth`
The plugin technical name changed from `RuneLaenenTwoFactorAuth` to `TinectTwoFactorAuth`.
Existing 2FA secrets (the `rl_2fa_secret` custom field, or the `rl_2fa_customer_secret` /
`rl_2fa_user_secret` tables of newer versions) are kept as-is, so users do not have to
re-register their authenticator apps. The plugin configuration
(`RuneLaenenTwoFactorAuth.config.*` vs. `TinectTwoFactorAuth.config.*`) is *not* migrated
and has to be set again after switching.

Everything else that carried the `rl` prefix is now prefixed with `tinect`. Adjust your
project if you rely on any of these:

| Before | After |
|---|---|
| snippet keys `rl-2fa.*` | `tinect-2fa.*` |
| routes `frontend.rl2fa.*`, `widgets.rl-2fa.*` | `frontend.tinect2fa.*`, `widgets.tinect-2fa.*` |
| URLs `/rl-2fa/...` | `/tinect-2fa/...` |
| admin API `_action/rl-2fa` | `_action/tinect-2fa` |
| OAuth token parameters `rl_2fa_otp`, `rl_2fa_ticket` | `tinect_2fa_otp`, `tinect_2fa_ticket` |
| Store API login parameter `rl2faOtp` | `tinect2faOtp` |
| error codes / session keys `RL_2FA_*` | `TINECT_2FA_*` |
| CSS `.rl-2fa-*`, `.rl2fa-*` | `.tinect-2fa-*`, `.tinect2fa-*` |
| storefront plugin `Rl2faVerificationPlugin`, `data-rl2fa-verification-plugin` | `Tinect2faVerificationPlugin`, `data-tinect2fa-verification-plugin` |
| admin component `rl-user-otp`, service `rl2faService`, Twig filter `rl2fa_main_js` | `tinect-user-otp`, `tinect2faService`, `tinect2fa_main_js` |
| custom field `rl_2fa_secret`, tables `rl_2fa_customer_secret`, `rl_2fa_user_secret` | **unchanged** |

```
composer remove runelaenen/shopware6-two-factor-auth
composer require tinect/shopware6-two-factor-auth
bin/console plugin:refresh
bin/console plugin:install --activate TinectTwoFactorAuth
```

## Credits
Original author: [Rune Laenen](https://github.com/runelaenen) / [Kraftware](https://www.kraftware.be) — MIT licensed.
Thanks to all [contributors](https://github.com/tinect/shopware6-two-factor-auth/graphs/contributors) of the original project.
