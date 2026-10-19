<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\BackOffice\InputFormatter\Campaign\GetCampaignClientsInputFormatter;
use Monarc\BackOffice\Service\CampaignService;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

/**
 * Entités invitables : le rôle superadmin n'a pas accès à la gestion des clients.
 */
class ApiCampaignClientsController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private CampaignService $campaignService,
        private GetCampaignClientsInputFormatter $getCampaignClientsInputFormatter
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getCampaignClientsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->campaignService->getSelectableClientsCount($params),
            'clients' => $this->campaignService->getSelectableClients($params),
        ]);
    }
}
