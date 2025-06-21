<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use PragmaRX\Google2FA\Google2FA;

readonly class TimebasedOneTimePasswordService implements TimebasedOneTimePasswordServiceInterface
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * @throws IncompatibleWithGoogleAuthenticatorException|InvalidCharactersException|SecretKeyTooShortException
     */
    public function createSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(string $company, string $holder, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($company, $holder, $secret);
    }

    /**
     * @throws IncompatibleWithGoogleAuthenticatorException|InvalidCharactersException|SecretKeyTooShortException
     */
    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }
}
