<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\BackOffice\Service\CampaignStatisticsService;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

class ApiCampaignStatisticsController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private CampaignStatisticsService $campaignStatisticsService)
    {
    }

    public function getList()
    {
        return $this->getPreparedJsonResponse($this->campaignStatisticsService->getOverview());
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->campaignStatisticsService->getCampaignStatistics((int)$id));
    }
}
