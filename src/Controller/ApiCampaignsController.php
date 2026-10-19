<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\BackOffice\InputFormatter\Campaign\GetCampaignsInputFormatter;
use Monarc\BackOffice\Service\CampaignService;
use Monarc\BackOffice\Validator\InputValidator\Campaign\PostCampaignInputValidator;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;
use Monarc\Core\Exception\Exception;

class ApiCampaignsController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(
        private CampaignService $campaignService,
        private GetCampaignsInputFormatter $getCampaignsInputFormatter,
        private PostCampaignInputValidator $postCampaignInputValidator
    ) {
    }

    public function getList()
    {
        $params = $this->getFormattedInputParams($this->getCampaignsInputFormatter);

        return $this->getPreparedJsonResponse([
            'count' => $this->campaignService->getCount($params),
            'campaigns' => $this->campaignService->getList($params),
        ]);
    }

    public function get($id)
    {
        return $this->getPreparedJsonResponse($this->campaignService->getCampaignData((int)$id));
    }

    public function create($data)
    {
        $this->validatePostParams($this->postCampaignInputValidator, $data);

        $campaign = $this->campaignService->create($this->postCampaignInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse(['id' => $campaign->getId()]);
    }

    public function update($id, $data)
    {
        $this->validatePostParams($this->postCampaignInputValidator, $data);

        $this->campaignService->update((int)$id, $this->postCampaignInputValidator->getValidData());

        return $this->getSuccessfulJsonResponse();
    }

    public function delete($id)
    {
        $this->campaignService->delete((int)$id);

        return $this->getSuccessfulJsonResponse();
    }

    public function publishAction()
    {
        $this->campaignService->publish($this->getPostedCampaignId());

        return $this->getSuccessfulJsonResponse();
    }

    public function closeAction()
    {
        $this->campaignService->close($this->getPostedCampaignId());

        return $this->getSuccessfulJsonResponse();
    }

    private function getPostedCampaignId(): int
    {
        if (!$this->getRequest()->isPost()) {
            throw new Exception('Method not allowed.', 405);
        }

        return (int)$this->params()->fromRoute('id');
    }
}
