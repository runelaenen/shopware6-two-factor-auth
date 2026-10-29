<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthException extends HttpException
{
    public const OTP_REQUIRED = 'RL_2FA__OTP_REQUIRED';
    public const OTP_INVALID = 'RL_2FA__OTP_INVALID';

    public static function otpRequired(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OTP_REQUIRED,
            'This account has two-factor authentication enabled. Send the one-time password as "rl2faOtp".'
        );
    }

    public static function otpInvalid(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OTP_INVALID,
            'The one-time password is not correct.'
        );
    }
}
