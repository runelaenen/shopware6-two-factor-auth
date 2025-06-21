<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Helper;

use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

readonly class ContextHelper
{
    public static function createDefaultContext(?ContextSource $source = null): Context
    {
        $source ??= new SystemSource();

        return new Context($source);
    }
}
