<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lets a customer skip the second factor on a browser that already passed it. The cookie is signed with the
 * customer's password hash, so changing the password ends the trust on every device.
 */
readonly class TrustedDeviceService
{
    public const COOKIE_NAME = 'tinect-2fa-trusted';

    public function __construct(
        private Connection $connection,
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function createCookie(string $customerId, int $days): Cookie
    {
        $expiresAt = time() + $days * 86400;

        return Cookie::create(
            name: self::COOKIE_NAME,
            value: $expiresAt . '.' . $this->sign($customerId, $expiresAt),
            expire: $expiresAt,
            path: '/',
            secure: true,
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    public function isTrusted(Request $request, string $customerId): bool
    {
        $parts = explode('.', $request->cookies->getString(self::COOKIE_NAME), 2);
        if (\count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }

        $expiresAt = (int) $parts[0];
        if ($expiresAt < time()) {
            return false;
        }

        return hash_equals($this->sign($customerId, $expiresAt), $parts[1]);
    }

    private function sign(string $customerId, int $expiresAt): string
    {
        $passwordHash = '';
        if (Uuid::isValid($customerId)) {
            $passwordHash = (string) $this->connection->fetchOne(
                'SELECT `password` FROM `customer` WHERE `id` = :id',
                ['id' => Uuid::fromHexToBytes($customerId)]
            );
        }

        return hash_hmac(
            'sha256',
            'tinect-2fa-trusted|' . $customerId . '|' . $expiresAt . '|' . $passwordHash,
            $this->secret
        );
    }
}
