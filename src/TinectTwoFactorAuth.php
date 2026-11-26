<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Tinect\TwoFactorAuth\Service\EmailCodeService;
use Tinect\TwoFactorAuth\Service\SecretStorage;

class TinectTwoFactorAuth extends Plugin
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
        $connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', SecretStorage::CUSTOMER_EMAIL_TABLE));
        $connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', EmailCodeService::CHALLENGE_TABLE));

        $connection->executeStatement(
            'DELETE `mail_template` FROM `mail_template`
            INNER JOIN `mail_template_type` ON `mail_template_type`.`id` = `mail_template`.`mail_template_type_id`
            WHERE `mail_template_type`.`technical_name` = :name',
            ['name' => EmailCodeService::MAIL_TEMPLATE_TYPE]
        );
        $connection->delete('mail_template_type', ['technical_name' => EmailCodeService::MAIL_TEMPLATE_TYPE]);
    }
}
