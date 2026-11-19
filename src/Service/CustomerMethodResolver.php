<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

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
        private Connection $connection,
    ) {
    }

    /**
     * For callers without a sales channel context, like the administration: uses the customer's own sales channel.
     *
     * @return self::METHOD_*|null
     */
    public function resolveForCustomer(string $customerId): ?string
    {
        if (!Uuid::isValid($customerId)) {
            return null;
        }

        $salesChannelId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`sales_channel_id`)) FROM `customer` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($customerId)]
        );

        if (!\is_string($salesChannelId)) {
            return null;
        }

        return $this->resolve($customerId, $salesChannelId);
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
