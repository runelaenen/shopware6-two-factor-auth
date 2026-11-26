<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

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

    /**
     * @throws IncompatibleWithGoogleAuthenticatorException|InvalidCharactersException|SecretKeyTooShortException
     */
    public function getTimestep(string $secret, string $code): ?int
    {
        // With an old timestamp given, google2fa returns the matching time step instead of true.
        $timestep = $this->google2fa->verifyKeyNewer($secret, $code, 0);

        return \is_int($timestep) ? $timestep : null;
    }
}
