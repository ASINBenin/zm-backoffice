<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Laminas\Diactoros\Response;
use Monarc\BackOffice\Service\SubmissionService;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

/**
 * Rapports soumis par les entités : liste (filtrable) et téléchargement d'une version.
 */
class ApiEntitySubmissionsAdminController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private SubmissionService $submissionService)
    {
    }

    public function getList()
    {
        $clientId = $this->params()->fromQuery('clientId');
        $campaignId = $this->params()->fromQuery('campaignId');

        return $this->getPreparedJsonResponse(['submissions' => $this->submissionService->getList(
            $clientId === null ? null : (int)$clientId,
            $campaignId === null ? null : (int)$campaignId
        )]);
    }

    public function get($id)
    {
        $file = $this->submissionService->getFile((int)$id);

        return new Response(fopen($file['path'], 'rb'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string)filesize($file['path']),
            'Content-Disposition' => 'attachment; filename="' . $file['downloadName'] . '"',
        ]);
    }
}
