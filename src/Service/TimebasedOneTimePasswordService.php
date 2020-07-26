<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

use PragmaRX\Google2FA\Google2FA;

class TimebasedOneTimePasswordService implements TimebasedOneTimePasswordServiceInterface
{
    /**
     * @var Google2FA
     */
    private $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function createSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getQrCodeUrl(string $company, string $holder, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl($company, $holder, $secret);
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }
}
