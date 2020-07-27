<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StorefrontTwoFactorAuthEvent
{
    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function __construct(SalesChannelContext $salesChannelContext)
    {
        $this->salesChannelContext = $salesChannelContext;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
