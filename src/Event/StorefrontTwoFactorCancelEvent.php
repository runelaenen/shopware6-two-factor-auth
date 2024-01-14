<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StorefrontTwoFactorCancelEvent
{
    public function __construct(private readonly SalesChannelContext $salesChannelContext)
    {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
