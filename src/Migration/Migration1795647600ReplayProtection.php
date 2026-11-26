<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Remembers the time step of the last accepted app code, so a code can't be used twice, and adds the table for
 * e-mail codes of Store API logins, which have no session to keep them in.
 */
class Migration1795647600ReplayProtection extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795647600;
    }

    public function update(Connection $connection): void
    {
        foreach (['rl_2fa_customer_secret', 'rl_2fa_user_secret'] as $table) {
            if (!$this->hasColumn($connection, $table, 'last_timestep')) {
                $connection->executeStatement(\sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `last_timestep` BIGINT UNSIGNED NULL',
                    $table
                ));
            }
        }

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `rl_2fa_customer_challenge` (
                `id` BINARY(32) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `code_hash` VARCHAR(64) NOT NULL,
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `expires_at` DATETIME(3) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.rl_2fa_customer_challenge.expires_at` (`expires_at`),
                CONSTRAINT `fk.rl_2fa_customer_challenge.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    private function hasColumn(Connection $connection, string $table, string $column): bool
    {
        return $connection->fetchOne(\sprintf('SHOW COLUMNS FROM `%s` LIKE :column', $table), ['column' => $column]) !== false;
    }
}
