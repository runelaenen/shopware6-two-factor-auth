<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth;

use Shopware\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthException extends HttpException
{
    public const OTP_REQUIRED = 'TINECT_2FA__OTP_REQUIRED';
    public const OTP_INVALID = 'TINECT_2FA__OTP_INVALID';
    public const EMAIL_CODE_REQUIRED = 'TINECT_2FA__EMAIL_CODE_REQUIRED';
    public const EMAIL_CODE_NOT_SENT = 'TINECT_2FA__EMAIL_CODE_NOT_SENT';

    public static function otpRequired(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::OTP_REQUIRED,
            'This account has two-factor authentication enabled. Send the one-time password as "tinect2faOtp".'
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

    public static function emailCodeRequired(string $challenge, int $expiresInSeconds): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::EMAIL_CODE_REQUIRED,
            'A login code was sent by e-mail. Send the login again with the code as "tinect2faOtp" and the challenge as "tinect2faChallenge".',
            ['challenge' => $challenge, 'expiresIn' => $expiresInSeconds]
        );
    }

    public static function emailCodeNotSent(): self
    {
        return new self(
            Response::HTTP_SERVICE_UNAVAILABLE,
            self::EMAIL_CODE_NOT_SENT,
            'The login code could not be sent by e-mail. Try again later.'
        );
    }
}
