<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Monarc\BackOffice\Service\SubmissionService;
use Monarc\BackOffice\Validator\InputValidator\EntitySubmission\PutAppreciationInputValidator;
use Monarc\Core\Controller\Handler\AbstractRestfulControllerRequestHandler;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

/**
 * Appréciation de l'ASIN sur une version de rapport soumise par une entité.
 */
class ApiEntitySubmissionAppreciationController extends AbstractRestfulControllerRequestHandler
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private SubmissionService $submissionService,
        private PutAppreciationInputValidator $putAppreciationInputValidator
    ) {
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->putAppreciationInputValidator, $data);

        $this->submissionService->appreciate((int)$id, $this->putAppreciationInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }
}
