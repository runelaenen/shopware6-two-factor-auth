<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

/**
 * Decides which second factor a storefront customer has to pass.
 */
readonly class CustomerMethodResolver
{
    public const METHOD_TOTP = 'totp';
    public const METHOD_EMAIL = 'email';

    public function __construct(
        private SecretStorage $secretStorage,
        private ConfigurationService $configurationService,
    ) {
    }

    /**
     * @return self::METHOD_*|null
     */
    public function resolve(string $customerId, string $salesChannelId): ?string
    {
        // The app keeps working even with storefront 2FA turned off, so nobody loses a factor they set up.
        if ($this->secretStorage->getCustomerSecret($customerId) !== null) {
            return self::METHOD_TOTP;
        }

        if (!$this->configurationService->isStorefrontEnabled($salesChannelId)) {
            return null;
        }

        if ($this->secretStorage->hasCustomerEmail($customerId)
            || $this->configurationService->isStorefrontForced($salesChannelId)
        ) {
            return self::METHOD_EMAIL;
        }

        return null;
    }
}
