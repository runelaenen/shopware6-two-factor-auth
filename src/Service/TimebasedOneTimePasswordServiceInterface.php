<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

interface TimebasedOneTimePasswordServiceInterface
{
    public function createSecret(): string;

    public function getQrCodeUrl(string $company, string $holder, string $secret): string;

    public function verifyCode(string $secret, string $code): bool;
}
