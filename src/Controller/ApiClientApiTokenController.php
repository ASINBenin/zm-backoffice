<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\BackOffice\Service\ClientService;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

/**
 * Renouvellement du jeton d'une entité.
 */
class ApiClientApiTokenController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private ClientService $clientService)
    {
    }

    public function create($data)
    {
        $this->clientService->renewApiToken((int)$this->params()->fromRoute('id'));

        return $this->getSuccessfulJsonResponse();
    }
}
