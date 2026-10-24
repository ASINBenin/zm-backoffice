<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Monarc\BackOffice\Entity\Client;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

/**
 * Entité qui appelle : permet au FO de vérifier son jeton.
 */
class ApiEntityMeController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function getList()
    {
        /** @var Client $client */
        $client = $this->getRequest()->getAttribute('client');

        return $this->getPreparedJsonResponse(['id' => $client->getId(), 'name' => $client->getName()]);
    }
}
