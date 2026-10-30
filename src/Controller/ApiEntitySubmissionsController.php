<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Service\EntitySubmissionService;
use Monarc\BackOffice\Validator\InputValidator\EntitySubmission\PostEntitySubmissionInputValidator;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

/**
 * Rapports de l'entité qui appelle (identifiée par son jeton) : envoi d'une version et historique.
 */
class ApiEntitySubmissionsController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private EntitySubmissionService $entitySubmissionService,
        private PostEntitySubmissionInputValidator $postEntitySubmissionInputValidator
    ) {
    }

    public function getList()
    {
        /** @var Client $client */
        $client = $this->getRequest()->getAttribute('client');
        $campaignId = $this->params()->fromQuery('campaignId');

        return $this->getPreparedJsonResponse(
            $this->entitySubmissionService->getSubmissions($client, $campaignId === null ? null : (int)$campaignId)
        );
    }

    public function create($data)
    {
        /** @var Client $client */
        $client = $this->getRequest()->getAttribute('client');
        $this->validatePostParams($this->postEntitySubmissionInputValidator, $data);

        return $this->getPreparedJsonResponse($this->entitySubmissionService->submit(
            $client,
            $this->postEntitySubmissionInputValidator->getValidData(),
            $this->params()->fromFiles('file') ?? []
        ));
    }
}
