<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Controller;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Tinect\TwoFactorAuth\Service\DashboardStatistics;

/**
 * Data for the 2FA widgets of the FroshAdminDashboard plugin.
 */
#[AutoconfigureTag(name: 'controller.service_arguments')]
#[Route(path: '/api/_action/tinect-2fa/dashboard', defaults: ['_routeScope' => ['api']])]
class DashboardController extends AbstractController
{
    private const MAX_RECENT_LIMIT = 50;

    private const MAX_HISTORY_DAYS = 366;

    public function __construct(
        private readonly DashboardStatistics $statistics,
    ) {
    }

    #[Route(
        path: '/summary',
        name: 'api.action.tinect-2fa.dashboard.summary',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: [Request::METHOD_GET],
    )]
    public function summary(Request $request): JsonResponse
    {
        return new JsonResponse($this->statistics->getSummary($this->salesChannelId($request)));
    }

    #[Route(
        path: '/activated-per-day',
        name: 'api.action.tinect-2fa.dashboard.activated-per-day',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: [Request::METHOD_GET],
    )]
    public function activatedPerDay(Request $request): JsonResponse
    {
        $days = min(self::MAX_HISTORY_DAYS, max(1, $request->query->getInt('days', 30)));

        return new JsonResponse([
            'days' => $this->statistics->getActivatedPerDay($days, $this->timeZone($request), $this->salesChannelId($request)),
        ]);
    }

    #[Route(
        path: '/recent',
        name: 'api.action.tinect-2fa.dashboard.recent',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['customer:read']],
        methods: [Request::METHOD_GET],
    )]
    public function recent(Request $request): JsonResponse
    {
        $limit = min(self::MAX_RECENT_LIMIT, max(1, $request->query->getInt('limit', 10)));

        return new JsonResponse([
            'activations' => $this->statistics->getRecentActivations($limit, $this->salesChannelId($request)),
        ]);
    }

    private function salesChannelId(Request $request): ?string
    {
        $salesChannelId = $request->query->getString('salesChannelId');

        return Uuid::isValid($salesChannelId) ? strtolower($salesChannelId) : null;
    }

    private function timeZone(Request $request): \DateTimeZone
    {
        $timeZone = $request->query->getString('timeZone');

        if (\in_array($timeZone, \DateTimeZone::listIdentifiers(), true)) {
            return new \DateTimeZone($timeZone);
        }

        return new \DateTimeZone('UTC');
    }
}
