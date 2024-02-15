<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth;

use Shopware\Core\Framework\Plugin;

class RuneLaenenTwoFactorAuth extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }
}
