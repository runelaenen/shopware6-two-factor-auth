<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Subscriber;

use RuneLaenen\TwoFactorAuth\Service\SecretStorage;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AutoconfigureTag(name: 'kernel.event_subscriber')]
readonly class AccountProfilePageSubscriber implements EventSubscriberInterface
{
    public function __construct(private SecretStorage $secretStorage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountProfilePageLoadedEvent::class => 'onProfilePageLoaded',
        ];
    }

    public function onProfilePageLoaded(AccountProfilePageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();

        $event->getPage()->addArrayExtension('rl2fa', [
            'enabled' => $customer !== null && $this->secretStorage->getCustomerSecret($customer->getId()) !== null,
        ]);
    }
}
