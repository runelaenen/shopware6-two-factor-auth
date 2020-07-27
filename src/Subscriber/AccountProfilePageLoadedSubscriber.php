<?php declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Subscriber;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AccountProfilePageLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @var ConfigurationService
     */
    private $configurationService;

    public function __construct(
        ConfigurationService $configurationService
    ) {
        $this->configurationService = $configurationService;
    }

    public static function getSubscribedEvents()
    {
        return [
            AccountProfilePageLoadedEvent::class => 'onAccountProfilePageLoaded'
        ];
    }

    public function onAccountProfilePageLoaded(AccountProfilePageLoadedEvent $event)
    {

    }
}
