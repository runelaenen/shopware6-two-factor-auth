<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

readonly class StorefrontTwoFactorAuthEvent
{
    public function __construct(private SalesChannelContext $salesChannelContext) {}

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
