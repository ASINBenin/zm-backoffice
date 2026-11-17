<?php declare(strict_types=1);

namespace Monarc\BackOffice\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Monarc\BackOffice\Service\InterEntityStatisticsService;
use Monarc\Core\Controller\Handler\ControllerRequestResponseHandlerTrait;

class ApiInterEntityStatisticsController extends AbstractRestfulController
{
    use ControllerRequestResponseHandlerTrait;

    public function __construct(private InterEntityStatisticsService $interEntityStatisticsService)
    {
    }

    public function getList()
    {
        // Le type est contraint par la route, la période est vérifiée par le service
        $period = (string)$this->params()->fromQuery('period', '3m');
        $data = match ($this->params()->fromRoute('type')) {
            'risks' => $this->interEntityStatisticsService->getRisks(),
            'compliance' => $this->interEntityStatisticsService->getCompliance(),
            'threats' => $this->interEntityStatisticsService->getThreats(),
            'vulnerabilities' => $this->interEntityStatisticsService->getVulnerabilities(),
            'evolution' => $this->interEntityStatisticsService->getEvolution($period),
        };

        return $this->getPreparedJsonResponse($data);
    }
}
