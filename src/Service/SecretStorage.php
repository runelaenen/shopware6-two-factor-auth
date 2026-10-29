<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

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

    public function __construct(private Connection $connection)
    {
    }

    public function getCustomerSecret(string $customerId): ?string
    {
        return $this->get(self::CUSTOMER_TABLE, 'customer_id', $customerId);
    }

    public function setCustomerSecret(string $customerId, string $secret): void
    {
        $this->set(self::CUSTOMER_TABLE, 'customer_id', $customerId, $secret);
    }

    public function removeCustomerSecret(string $customerId): void
    {
        $this->connection->delete(self::CUSTOMER_TABLE, ['customer_id' => Uuid::fromHexToBytes($customerId)]);
    }

    public function getUserSecret(string $userId): ?string
    {
        return $this->get(self::USER_TABLE, 'user_id', $userId);
    }

    public function setUserSecret(string $userId, string $secret): void
    {
        $this->set(self::USER_TABLE, 'user_id', $userId, $secret);
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

    private function set(string $table, string $column, string $id, string $secret): void
    {
        $this->connection->executeStatement(
            \sprintf(
                'INSERT INTO `%s` (`%s`, `secret`, `created_at`) VALUES (:id, :secret, :createdAt)
                ON DUPLICATE KEY UPDATE `secret` = :secret, `created_at` = :createdAt',
                $table,
                $column
            ),
            [
                'id' => Uuid::fromHexToBytes($id),
                'secret' => $secret,
                'createdAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
