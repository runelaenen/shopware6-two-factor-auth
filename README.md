# Two Factor Authentication for Shopware 6
[![Latest Stable Version](https://poser.pugx.org/runelaenen/shopware6-two-factor-auth/v)](//packagist.org/packages/runelaenen/shopware6-two-factor-auth)
[![Total Downloads](https://poser.pugx.org/runelaenen/shopware6-two-factor-auth/downloads)](//packagist.org/packages/runelaenen/shopware6-two-factor-auth)
[![License](https://poser.pugx.org/runelaenen/shopware6-two-factor-auth/license)](//packagist.org/packages/runelaenen/shopware6-two-factor-auth)

![Two Factor Authentication for Shopware 6](https://user-images.githubusercontent.com/3930922/90954708-f8394c80-e476-11ea-940d-4733d4ce2588.png)

Add extra security to your Shopware 6 shop by enabling Two Factor Authentication.

Adds an extra prompt to admin- or customer-accounts in your Shopware 6 website.

## Features
 - 'Google Authenticator' provider
 - Storefront customer 2FA
 - Admin user 2FA
 - Local QR code generation
 - Fully localized:
   - English
   - German
   - French
   - Dutch
   - Polish
 
## Providers
At the moment only Google Authenticator (compatible) apps are supported. 
For example Google Authenticator, Authy, LastPass, Bitwarden, ...

## Installation guide

This plugin can only be installed using Composer.

```
# Install plugin using composer
composer require runelaenen/shopware6-two-factor-auth

# Refresh plugins & install & activate plugin
bin/console plugin:refresh
bin/console plugin:install --activate RuneLaenenTwoFactorAuth

# Build javascript files
bin/build-js.sh
```

## Store API

Customers with 2FA enabled have to send their one-time password with the login request as `rl2faOtp`:

```
POST /store-api/account/login
{"email": "customer@example.com", "password": "...", "rl2faOtp": "123456"}
```

Without it the request fails with a `401` and the error code `RL_2FA__OTP_REQUIRED`, so the frontend knows to ask for
the code and send the login again. A wrong code returns `RL_2FA__OTP_INVALID`.

## Updating from 7.0 or older

Secrets used to be stored in the `rl_2fa_secret` custom field of customers and users, where the Store API and
Admin API returned them. The update moves them to their own tables and removes the custom field. Enabled accounts
keep working, but downgrading after the update turns 2FA off for those accounts.

## Development
Keep in mind that 2FA authentication will not work in the development Administration watcher mode.
