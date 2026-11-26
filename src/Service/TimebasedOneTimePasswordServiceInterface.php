<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

interface TimebasedOneTimePasswordServiceInterface
{
    public function createSecret(): string;

    public function getQrCodeUrl(string $company, string $holder, string $secret): string;

    public function verifyCode(string $secret, string $code): bool;

    /**
     * Returns the time step the code belongs to, or null if it is not valid. Callers store the step and accept only
     * newer ones afterwards, so a code can't be used twice.
     */
    public function getTimestep(string $secret, string $code): ?int;
}
