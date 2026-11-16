<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Subscriber;

use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\TwoFactorAuth\Service\SecretStorage;

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

        $event->getPage()->addArrayExtension('tinect2fa', [
            'enabled' => $customer !== null && $this->secretStorage->getCustomerSecret($customer->getId()) !== null,
        ]);
    }
}
