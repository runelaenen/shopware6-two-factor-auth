<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Secrets live in their own tables instead of a DAL entity or custom field, so no API, search filter
 * or template can ever read them.
 */
readonly class SecretStorage
{
    public const CUSTOMER_TABLE = 'rl_2fa_customer_secret';
    public const USER_TABLE = 'rl_2fa_user_secret';
    public const CUSTOMER_EMAIL_TABLE = 'rl_2fa_customer_email';

    public function __construct(private Connection $connection)
    {
    }

    public function getCustomerSecret(string $customerId): ?string
    {
        return $this->get(self::CUSTOMER_TABLE, 'customer_id', $customerId);
    }

    public function setCustomerSecret(string $customerId, string $secret, ?int $usedTimestep = null): void
    {
        $this->set(self::CUSTOMER_TABLE, 'customer_id', $customerId, $secret, $usedTimestep);
    }

    /**
     * Marks the time step of a customer's code as used. False if it, or a newer one, was used before.
     */
    public function useCustomerTimestep(string $customerId, int $timestep): bool
    {
        return $this->useTimestep(self::CUSTOMER_TABLE, 'customer_id', $customerId, $timestep);
    }

    public function removeCustomerSecret(string $customerId): void
    {
        $this->connection->delete(self::CUSTOMER_TABLE, ['customer_id' => Uuid::fromHexToBytes($customerId)]);
    }

    public function hasCustomerEmail(string $customerId): bool
    {
        if (!Uuid::isValid($customerId)) {
            return false;
        }

        return (bool) $this->connection->fetchOne(
            \sprintf('SELECT 1 FROM `%s` WHERE `customer_id` = :id', self::CUSTOMER_EMAIL_TABLE),
            ['id' => Uuid::fromHexToBytes($customerId)]
        );
    }

    public function enableCustomerEmail(string $customerId): void
    {
        $this->connection->executeStatement(
            \sprintf('INSERT IGNORE INTO `%s` (`customer_id`, `created_at`) VALUES (:id, :createdAt)', self::CUSTOMER_EMAIL_TABLE),
            [
                'id' => Uuid::fromHexToBytes($customerId),
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function removeCustomerEmail(string $customerId): void
    {
        $this->connection->delete(self::CUSTOMER_EMAIL_TABLE, ['customer_id' => Uuid::fromHexToBytes($customerId)]);
    }

    public function getUserSecret(string $userId): ?string
    {
        return $this->get(self::USER_TABLE, 'user_id', $userId);
    }

    public function setUserSecret(string $userId, string $secret, ?int $usedTimestep = null): void
    {
        $this->set(self::USER_TABLE, 'user_id', $userId, $secret, $usedTimestep);
    }

    /**
     * Marks the time step of a user's code as used. False if it, or a newer one, was used before.
     */
    public function useUserTimestep(string $userId, int $timestep): bool
    {
        return $this->useTimestep(self::USER_TABLE, 'user_id', $userId, $timestep);
    }

    public function removeUserSecret(string $userId): void
    {
        $this->connection->delete(self::USER_TABLE, ['user_id' => Uuid::fromHexToBytes($userId)]);
    }

    private function get(string $table, string $column, string $id): ?string
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        $secret = $this->connection->fetchOne(
            \sprintf('SELECT `secret` FROM `%s` WHERE `%s` = :id', $table, $column),
            ['id' => Uuid::fromHexToBytes($id)]
        );

        return \is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function set(string $table, string $column, string $id, string $secret, ?int $usedTimestep): void
    {
        $this->connection->executeStatement(
            \sprintf(
                'INSERT INTO `%s` (`%s`, `secret`, `last_timestep`, `created_at`) VALUES (:id, :secret, :lastTimestep, :createdAt)
                ON DUPLICATE KEY UPDATE `secret` = :secret, `last_timestep` = :lastTimestep, `created_at` = :createdAt',
                $table,
                $column
            ),
            [
                'id' => Uuid::fromHexToBytes($id),
                'secret' => $secret,
                'lastTimestep' => $usedTimestep,
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    /**
     * A single conditional UPDATE, so two requests with the same code can't both pass.
     */
    private function useTimestep(string $table, string $column, string $id, int $timestep): bool
    {
        if (!Uuid::isValid($id)) {
            return false;
        }

        return $this->connection->executeStatement(
            \sprintf(
                'UPDATE `%s` SET `last_timestep` = :timestep
                WHERE `%s` = :id AND (`last_timestep` IS NULL OR `last_timestep` < :timestep)',
                $table,
                $column
            ),
            ['id' => Uuid::fromHexToBytes($id), 'timestep' => $timestep]
        ) === 1;
    }
}
