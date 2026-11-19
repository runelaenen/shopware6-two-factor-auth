<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Subscriber;

use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tinect\TwoFactorAuth\Service\ConfigurationService;
use Tinect\TwoFactorAuth\Service\CustomerMethodResolver;

#[AutoconfigureTag(name: 'kernel.event_subscriber')]
readonly class AccountProfilePageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CustomerMethodResolver $methodResolver,
        private ConfigurationService $configurationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AccountProfilePageLoadedEvent::class => 'onProfilePageLoaded',
        ];
    }

    public function onProfilePageLoaded(AccountProfilePageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $customer = $context->getCustomer();
        $method = $customer === null ? null : $this->methodResolver->resolve($customer->getId(), $context->getSalesChannelId());

        $event->getPage()->addArrayExtension('tinect2fa', [
            'enabled' => $method !== null,
            'method' => $method,
            'forced' => $this->configurationService->isStorefrontForced($context->getSalesChannelId()),
        ]);
    }
}
