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

    public function get(string $key, ?string $salesChannelId = null)
    {
        return $this->systemConfig->get(
            self::CONFIGURATION_KEY . '.config.' . $key,
            $salesChannelId
        );
    }

    public function getAdministrationCompany(?string $salesChannelId = null): string
    {
        return (string) $this->get('administrationCompany', $salesChannelId);
    }

    public function isStorefrontEnabled(?string $salesChannelId = null): bool
    {
        return (bool) $this->get('storefrontEnabled', $salesChannelId);
    }

    public function getStorefrontCompany(?string $salesChannelId = null): string
    {
        return (string) $this->get('storefrontCompany', $salesChannelId);
    }
}
