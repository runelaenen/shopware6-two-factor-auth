<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The administration asks for the password again (OAuth scope "user-verified") before sensitive changes.
 * That request carries no OTP, so a ticket proves it comes from a session that already passed 2FA.
 */
readonly class UserVerificationTicketService
{
    private const TTL_SECONDS = 60;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function create(string $userId): string
    {
        $expiresAt = time() + self::TTL_SECONDS;

        return $expiresAt . '.' . $this->sign($userId, $expiresAt);
    }

    public function isValid(string $ticket, string $userId): bool
    {
        $parts = explode('.', $ticket, 2);
        if (\count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }

        $expiresAt = (int) $parts[0];
        if ($expiresAt < time()) {
            return false;
        }

        return hash_equals($this->sign($userId, $expiresAt), $parts[1]);
    }

    private function sign(string $userId, int $expiresAt): string
    {
        return hash_hmac('sha256', 'rl-2fa-user-verified|' . $userId . '|' . $expiresAt, $this->secret);
    }
}
