<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Service\EntityCampaignService;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

/**
 * Campagnes de l'entité qui appelle (identifiée par son jeton).
 */
class ApiEntityCampaignsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private EntityCampaignService $entityCampaignService)
    {
    }

    public function getList()
    {
        /** @var Client $client */
        $client = $this->getRequest()->getAttribute('client');

        return $this->getPreparedJsonResponse(
            $this->entityCampaignService->getCampaigns($client, $this->params()->fromQuery('since'))
        );
    }
}
