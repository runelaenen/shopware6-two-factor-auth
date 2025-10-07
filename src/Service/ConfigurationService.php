<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

readonly class ConfigurationService
{
    public const CONFIGURATION_KEY = 'RuneLaenenTwoFactorAuth.config.';

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

    public function getStorefrontCompany(?string $salesChannelId = null): string
    {
        return $this->systemConfig->getString(self::CONFIGURATION_KEY . 'storefrontCompany', $salesChannelId);
    }
}
