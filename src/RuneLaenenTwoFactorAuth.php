<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth;

use Doctrine\DBAL\Connection;
use RuneLaenen\TwoFactorAuth\Service\SecretStorage;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class RuneLaenenTwoFactorAuth extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $connection = $this->container?->get(Connection::class);
        if (!$connection instanceof Connection) {
            return;
        }

        $connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', SecretStorage::CUSTOMER_TABLE));
        $connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', SecretStorage::USER_TABLE));
    }
}
