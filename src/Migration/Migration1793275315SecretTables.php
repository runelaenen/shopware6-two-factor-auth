<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Moves the secrets out of the `rl_2fa_secret` custom field, where the Store API and Admin API returned them.
 * The custom field is removed in update(), not updateDestructive(), because leaving it would keep them exposed.
 */
class Migration1793275315SecretTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793275315;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `rl_2fa_customer_secret` (
                `customer_id` BINARY(16) NOT NULL,
                `secret` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`customer_id`),
                CONSTRAINT `fk.rl_2fa_customer_secret.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `rl_2fa_user_secret` (
                `user_id` BINARY(16) NOT NULL,
                `secret` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`user_id`),
                CONSTRAINT `fk.rl_2fa_user_secret.user_id` FOREIGN KEY (`user_id`)
                    REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->moveCustomField($connection, 'customer', 'rl_2fa_customer_secret', 'customer_id');
        $this->moveCustomField($connection, 'user', 'rl_2fa_user_secret', 'user_id');
    }

    private function moveCustomField(Connection $connection, string $source, string $target, string $column): void
    {
        $connection->executeStatement(\sprintf('
            INSERT IGNORE INTO `%s` (`%s`, `secret`, `created_at`)
            SELECT `id`, JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, \'$.rl_2fa_secret\')), NOW(3)
            FROM `%s`
            WHERE JSON_TYPE(JSON_EXTRACT(`custom_fields`, \'$.rl_2fa_secret\')) = \'STRING\'
                AND JSON_UNQUOTE(JSON_EXTRACT(`custom_fields`, \'$.rl_2fa_secret\')) <> \'\'
        ', $target, $column, $source));

        $connection->executeStatement(\sprintf('
            UPDATE `%s`
            SET `custom_fields` = JSON_REMOVE(`custom_fields`, \'$.rl_2fa_secret\')
            WHERE JSON_CONTAINS_PATH(`custom_fields`, \'one\', \'$.rl_2fa_secret\')
        ', $source));
    }
}
