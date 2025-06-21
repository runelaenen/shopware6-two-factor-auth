<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Subscriber;

use Exception;
use League\OAuth2\Server\Exception\OAuthServerException;
use RuneLaenen\TwoFactorAuth\Helper\ContextHelper;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AutoconfigureTag(name: 'kernel.event_subscriber')]
readonly class ApiOauthTokenSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'user.repository')]
        private EntityRepository $userRepository,
        #[Autowire(service: 'RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordService')]
        private TimebasedOneTimePasswordServiceInterface $oneTimePasswordService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    /**
     * @throws OAuthServerException
     */
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'api.oauth.token') {
            return;
        }

        if ($request->request->get('scope') === 'user-verified'
            || $event->getResponse()->getStatusCode() !== 200) {
            return;
        }

        $username = $request->request->get('username');

        $user = $this->userRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('username', $username)),
            ContextHelper::createDefaultContext()
        )->first();

        if (!$user instanceof UserEntity
            || empty($user->getCustomFields()['rl_2fa_secret'])
        ) {
            return;
        }

        $otp = $request->request->get('rl_2fa_otp');
        if ($otp && $this->checkOtp($user->getCustomFields()['rl_2fa_secret'], $otp)) {
            return;
        }

        throw new OAuthServerException('This user needs an extra OTP', 1010, 'request-otp', 401, 'request-otp');
    }

    /**
     * @returns true if OTP is correct
     *
     * @throws OAuthServerException when the OTP is incorrect
     */
    private function checkOtp($secret, $code): bool
    {
        try {
            if (!$this->oneTimePasswordService->verifyCode($secret, $code)) {
                throw new Exception();
            }
        } catch (Exception $exception) {
            throw new OAuthServerException('Wrong OTP', 1011, 'wrong-otp', 401, null, null, $exception);
        }

        return true;
    }
}
