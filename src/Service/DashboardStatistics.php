<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Read-only aggregations for the widgets of the FroshAdminDashboard plugin.
 *
 * @phpstan-type RecentActivationRow array{method: string, createdAt: string, customerId: string, firstName: string, lastName: string, customerNumber: string, email: string}
 */
readonly class DashboardStatistics
{
    public function __construct(
        private Connection $connection,
        private ConfigurationService $configurationService,
    ) {
    }

    /**
     * Customers are counted by the method that applies to their login, see CustomerMethodResolver:
     * the app wins over e-mail, chosen e-mail codes need storefront 2FA turned on, enforced ones also the enforce option.
     *
     * @return array{customers: int, totp: int, email: int, enforced: int, users: int, usersWithTotp: int}
     */
    public function getSummary(?string $salesChannelId): array
    {
        $salesChannelIds = $this->getSalesChannelIds($salesChannelId);

        $enabled = [];
        $enforced = [];
        foreach ($salesChannelIds as $id) {
            if (!$this->configurationService->isStorefrontEnabled($id)) {
                continue;
            }

            $enabled[] = Uuid::fromHexToBytes($id);
            if ($this->configurationService->isStorefrontForced($id)) {
                $enforced[] = Uuid::fromHexToBytes($id);
            }
        }

        $row = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS `customers`,
                    COUNT(`secret`.`customer_id`) AS `totp`,
                    COUNT(CASE WHEN `secret`.`customer_id` IS NULL AND `email`.`customer_id` IS NOT NULL
                        AND `customer`.`sales_channel_id` IN (:enabled) THEN 1 END) AS `email`,
                    COUNT(CASE WHEN `secret`.`customer_id` IS NULL AND `email`.`customer_id` IS NULL
                        AND `customer`.`sales_channel_id` IN (:enforced) THEN 1 END) AS `enforced`
             FROM `customer`
             LEFT JOIN `rl_2fa_customer_secret` `secret` ON `secret`.`customer_id` = `customer`.`id`
             LEFT JOIN `rl_2fa_customer_email` `email` ON `email`.`customer_id` = `customer`.`id`
             WHERE `customer`.`guest` = 0 AND `customer`.`sales_channel_id` IN (:salesChannelIds)',
            [
                // IN () is invalid SQL, a random id matches nothing.
                'salesChannelIds' => array_map(Uuid::fromHexToBytes(...), $salesChannelIds) ?: [Uuid::randomBytes()],
                'enabled' => $enabled ?: [Uuid::randomBytes()],
                'enforced' => $enforced ?: [Uuid::randomBytes()],
            ],
            [
                'salesChannelIds' => ArrayParameterType::BINARY,
                'enabled' => ArrayParameterType::BINARY,
                'enforced' => ArrayParameterType::BINARY,
            ]
        ) ?: [];

        $users = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS `users`, COUNT(`secret`.`user_id`) AS `usersWithTotp`
             FROM `user`
             LEFT JOIN `rl_2fa_user_secret` `secret` ON `secret`.`user_id` = `user`.`id`
             WHERE `user`.`active` = 1'
        ) ?: [];

        return [
            'customers' => $this->toInt($row['customers'] ?? 0),
            'totp' => $this->toInt($row['totp'] ?? 0),
            'email' => $this->toInt($row['email'] ?? 0),
            'enforced' => $this->toInt($row['enforced'] ?? 0),
            'users' => $this->toInt($users['users'] ?? 0),
            'usersWithTotp' => $this->toInt($users['usersWithTotp'] ?? 0),
        ];
    }

    /**
     * Customers that set up the app or chose e-mail codes, per day in the given time zone, oldest day first.
     * Days without activations are omitted. Setting up the app again counts as a new activation.
     *
     * @return list<array{date: string, totp: int, email: int}>
     */
    public function getActivatedPerDay(int $days, \DateTimeZone $timeZone, ?string $salesChannelId): array
    {
        [$salesChannelCondition, $params] = $this->salesChannelCondition($salesChannelId);
        $params['since'] = (new \DateTimeImmutable(\sprintf('-%d days', $days)))->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        // Database values are UTC. Grouping by hour and shifting each hour into the time zone in PHP
        // keeps the day boundaries correct without depending on the MySQL time zone tables.
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `activation`.`method`, DATE_FORMAT(`activation`.`created_at`, \'%Y-%m-%d %H:00:00\') AS `hour`, COUNT(*) AS `count`
             FROM (
                 SELECT \'totp\' AS `method`, `customer_id`, `created_at` FROM `rl_2fa_customer_secret`
                 UNION ALL
                 SELECT \'email\' AS `method`, `customer_id`, `created_at` FROM `rl_2fa_customer_email`
             ) `activation`
             INNER JOIN `customer` ON `customer`.`id` = `activation`.`customer_id`
             WHERE `activation`.`created_at` >= :since' . $salesChannelCondition . '
             GROUP BY `activation`.`method`, `hour`',
            $params
        );

        $utc = new \DateTimeZone('UTC');
        $perDay = [];

        foreach ($rows as $row) {
            if (!\is_string($row['hour']) || !\in_array($row['method'], ['totp', 'email'], true)) {
                continue;
            }

            $date = (new \DateTimeImmutable($row['hour'], $utc))->setTimezone($timeZone)->format('Y-m-d');
            $perDay[$date] ??= ['totp' => 0, 'email' => 0];
            $perDay[$date][$row['method']] += $this->toInt($row['count']);
        }

        ksort($perDay);

        $result = [];
        foreach ($perDay as $date => $counts) {
            $result[] = ['date' => (string) $date, 'totp' => $counts['totp'], 'email' => $counts['email']];
        }

        return $result;
    }

    /**
     * @return list<RecentActivationRow>
     */
    public function getRecentActivations(int $limit, ?string $salesChannelId): array
    {
        [$salesChannelCondition, $params] = $this->salesChannelCondition($salesChannelId);

        /** @var list<RecentActivationRow> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT `activation`.`method`,
                    `activation`.`created_at` AS `createdAt`,
                    LOWER(HEX(`customer`.`id`)) AS `customerId`,
                    `customer`.`first_name` AS `firstName`,
                    `customer`.`last_name` AS `lastName`,
                    `customer`.`customer_number` AS `customerNumber`,
                    `customer`.`email`
             FROM (
                 SELECT \'totp\' AS `method`, `customer_id`, `created_at` FROM `rl_2fa_customer_secret`
                 UNION ALL
                 SELECT \'email\' AS `method`, `customer_id`, `created_at` FROM `rl_2fa_customer_email`
             ) `activation`
             INNER JOIN `customer` ON `customer`.`id` = `activation`.`customer_id`
             WHERE 1 = 1' . $salesChannelCondition . '
             ORDER BY `activation`.`created_at` DESC
             LIMIT ' . max(1, $limit),
            $params
        );

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function getSalesChannelIds(?string $salesChannelId): array
    {
        if ($salesChannelId !== null) {
            return [$salesChannelId];
        }

        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(`id`)) FROM `sales_channel`');

        return $ids;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function salesChannelCondition(?string $salesChannelId): array
    {
        if ($salesChannelId === null) {
            return ['', []];
        }

        return [
            ' AND `customer`.`sales_channel_id` = :salesChannelId',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId)],
        ];
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
