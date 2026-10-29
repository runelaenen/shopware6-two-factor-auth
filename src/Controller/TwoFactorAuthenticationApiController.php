<?php

declare(strict_types=1);

namespace RuneLaenen\TwoFactorAuth\Controller;

use RuneLaenen\TwoFactorAuth\Service\ConfigurationService;
use RuneLaenen\TwoFactorAuth\Service\QrCodeService;
use RuneLaenen\TwoFactorAuth\Service\SecretStorage;
use RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordServiceInterface;
use RuneLaenen\TwoFactorAuth\Service\UserVerificationTicketService;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Sso\SsoService;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AutoconfigureTag(name: 'controller.service_arguments')]
#[Route(path: '/api/_action/rl-2fa', defaults: ['_routeScope' => ['api']])]
class TwoFactorAuthenticationApiController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'RuneLaenen\TwoFactorAuth\Service\TimebasedOneTimePasswordService')]
        private readonly TimebasedOneTimePasswordServiceInterface $totpService,
        private readonly ConfigurationService $configurationService,
        private readonly QrCodeService $qrCodeService,
        private readonly UserVerificationTicketService $ticketService,
        private readonly SecretStorage $secretStorage,
        private readonly SsoService $ssoService,
    ) {
    }

    #[Route(path: '/generate-secret', name: 'api.action.rl-2fa.generate-secret', methods: ['GET'])]
    public function generateSecret(Request $request): JsonResponse
    {
        $secret = $this->totpService->createSecret();
        $qrUrl = $this->totpService->getQrCodeUrl(
            $this->configurationService->getAdministrationCompany(),
            $request->query->getString('holder'),
            $secret
        );

        return new JsonResponse([
            'secret' => $secret,
            'qrUrl' => $this->qrCodeService->createDataUri($qrUrl),
        ]);
    }

    #[Route(path: '/user/{userId}/status', name: 'api.action.rl-2fa.user.status', methods: ['GET'])]
    public function userStatus(string $userId, Context $context): JsonResponse
    {
        $this->assertUserAccess($userId, 'user:read', $context);

        return new JsonResponse(['enabled' => $this->secretStorage->getUserSecret($userId) !== null]);
    }

    #[Route(path: '/user/{userId}/secret', name: 'api.action.rl-2fa.user.secret.save', methods: ['POST'])]
    public function saveUserSecret(string $userId, Request $request, Context $context): JsonResponse
    {
        $this->assertUserAccess($userId, 'user:update', $context);
        $this->assertVerifiedScope($request);

        $secret = $request->request->getString('secret');
        $code = $request->request->getString('code');

        if ($secret === '' || $code === '') {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'Secret or code empty',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $verified = $this->totpService->verifyCode($secret, $code);
        } catch (\Exception) {
            $verified = false;
        }

        if (!$verified) {
            return new JsonResponse([
                'status' => 'error',
                'error' => 'Secret and code not correct',
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->secretStorage->setUserSecret($userId, $secret);

        return new JsonResponse(['status' => 'OK']);
    }

    #[Route(path: '/user/{userId}/secret', name: 'api.action.rl-2fa.user.secret.remove', methods: ['DELETE'])]
    public function removeUserSecret(string $userId, Request $request, Context $context): JsonResponse
    {
        $this->assertUserAccess($userId, 'user:update', $context);
        $this->assertVerifiedScope($request);

        $this->secretStorage->removeUserSecret($userId);

        return new JsonResponse(['status' => 'OK']);
    }

    #[Route(
        path: '/customer/{customerId}/status',
        name: 'api.action.rl-2fa.customer.status',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: ['GET'],
    )]
    public function customerStatus(string $customerId): JsonResponse
    {
        return new JsonResponse(['enabled' => $this->secretStorage->getCustomerSecret($customerId) !== null]);
    }

    #[Route(
        path: '/customer/{customerId}/secret',
        name: 'api.action.rl-2fa.customer.secret.remove',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:update']],
        methods: ['DELETE'],
    )]
    public function removeCustomerSecret(string $customerId): JsonResponse
    {
        $this->secretStorage->removeCustomerSecret($customerId);

        return new JsonResponse(['status' => 'OK']);
    }

    #[Route(path: '/user-verification-ticket', name: 'api.action.rl-2fa.user-verification-ticket', methods: ['POST'])]
    public function userVerificationTicket(Context $context): JsonResponse
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null) {
            return new JsonResponse(['status' => 'error'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['ticket' => $this->ticketService->create($source->getUserId())]);
    }

    private function assertUserAccess(string $userId, string $privilege, Context $context): void
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::missingPrivileges([$privilege]);
        }

        if ($source->getUserId() !== $userId && !$source->isAllowed($privilege)) {
            throw ApiException::missingPrivileges([$privilege]);
        }
    }

    /**
     * Same rule as core's user endpoints: the administration has to confirm the password first.
     */
    private function assertVerifiedScope(Request $request): void
    {
        if ($request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID) !== 'administration'
            || $this->ssoService->isSso()
        ) {
            return;
        }

        $scopes = (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, []);
        if (!\in_array(UserVerifiedScope::IDENTIFIER, $scopes, true)) {
            throw ApiException::invalidScopeAccessToken(UserVerifiedScope::IDENTIFIER);
        }
    }
}
