<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigurationService
{
    public const CONFIGURATION_KEY = 'RuneLaenenTwoFactorAuth';

    public function __construct(
        private readonly SystemConfigService $systemConfig
    ) {
    }

    public function getAdministrationCompany(?string $salesChannelId = null): string
    {
        return $this->systemConfig->getString(
            self::CONFIGURATION_KEY . '.config.administrationCompany',
            $salesChannelId
        );
    }

    public function isStorefrontEnabled(?string $salesChannelId = null): bool
    {
        return $this->systemConfig->getBool(
            self::CONFIGURATION_KEY . '.config.storefrontEnabled',
            $salesChannelId
        );
    }

    public function getStorefrontCompany(?string $salesChannelId = null): string
    {
        return $this->systemConfig->getString(
            self::CONFIGURATION_KEY . '.config.storefrontCompany',
            $salesChannelId
        );
    }
}
