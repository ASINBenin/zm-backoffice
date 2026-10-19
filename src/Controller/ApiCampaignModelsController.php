<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\BackOffice\InputFormatter\Campaign\GetCampaignModelsInputFormatter;
use Monarc\BackOffice\Service\CampaignService;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

class ApiCampaignModelsController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private CampaignService $campaignService,
        private GetCampaignModelsInputFormatter $getCampaignModelsInputFormatter
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getCampaignModelsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->campaignService->getSelectableModelsCount($params),
            'models' => $this->campaignService->getSelectableModels($params),
        ]);
    }
}
