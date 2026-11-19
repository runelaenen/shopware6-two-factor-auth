<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

readonly class ConfigurationService
{
    public const CONFIGURATION_KEY = 'TinectTwoFactorAuth.config.';

    public function __construct(private SystemConfigService $systemConfig)
    {
    }

    public function getAdministrationCompany(?string $salesChannelId = null): string
    {
        return $this->systemConfig->getString(self::CONFIGURATION_KEY . 'administrationCompany', $salesChannelId);
    }

    public function isStorefrontEnabled(?string $salesChannelId = null): bool
    {
        return $this->systemConfig->getBool(self::CONFIGURATION_KEY . 'storefrontEnabled', $salesChannelId);
    }

    public function isStorefrontForced(?string $salesChannelId = null): bool
    {
        return $this->systemConfig->getBool(self::CONFIGURATION_KEY . 'storefrontForce', $salesChannelId);
    }

    public function getStorefrontTrustedDeviceDays(?string $salesChannelId = null): int
    {
        return max(0, $this->systemConfig->getInt(self::CONFIGURATION_KEY . 'storefrontTrustedDeviceDays', $salesChannelId));
    }

    public function getStorefrontCompany(?string $salesChannelId = null): string
    {
        return $this->systemConfig->getString(self::CONFIGURATION_KEY . 'storefrontCompany', $salesChannelId);
    }
}
