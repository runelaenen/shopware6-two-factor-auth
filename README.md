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

## Development
Keep in mind that 2FA authentication will not work in the development Administration watcher mode.
