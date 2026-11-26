<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks login codes of the authenticator app. Every code is accepted only once: its time step is stored, and only
 * newer steps pass afterwards, so a code seen by someone else is worthless once it was used.
 */
readonly class TotpVerifier
{
    public function __construct(
        #[Autowire(service: TimebasedOneTimePasswordService::class)]
        private TimebasedOneTimePasswordServiceInterface $totpService,
        private SecretStorage $secretStorage,
    ) {
    }

    public function verifyCustomer(string $customerId, string $code): bool
    {
        $timestep = $this->getTimestep($this->secretStorage->getCustomerSecret($customerId), $code);

        return $timestep !== null && $this->secretStorage->useCustomerTimestep($customerId, $timestep);
    }

    public function verifyUser(string $userId, string $code): bool
    {
        $timestep = $this->getTimestep($this->secretStorage->getUserSecret($userId), $code);

        return $timestep !== null && $this->secretStorage->useUserTimestep($userId, $timestep);
    }

    /**
     * For setting up a new secret: the returned step is stored with it, so the code of the setup can't log in.
     */
    public function getTimestep(?string $secret, string $code): ?int
    {
        if ($secret === null || $secret === '' || $code === '') {
            return null;
        }

        try {
            return $this->totpService->getTimestep($secret, $code);
        } catch (\Exception) {
            return null;
        }
    }
}
