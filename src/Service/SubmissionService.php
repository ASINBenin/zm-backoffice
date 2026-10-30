<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use DateTimeInterface;
use Monarc\BackOffice\Entity\EntitySubmission;
use Monarc\BackOffice\Table\EntitySubmissionTable;
use Monarc\Core\Exception\Exception;

/**
 * Rapports soumis par les entités, vus par les administrateurs du BO.
 */
class SubmissionService
{
    public function __construct(
        private EntitySubmissionTable $entitySubmissionTable,
        private EntitySubmissionService $entitySubmissionService
    ) {
    }

    /**
     * @return array[] Versions soumises, les plus récentes d'abord, filtrées par entité et/ou par campagne.
     */
    public function getList(?int $clientId, ?int $campaignId): array
    {
        $result = [];
        /** @var EntitySubmission $submission */
        foreach ($this->entitySubmissionTable->findFiltered($clientId, $campaignId) as $submission) {
            $campaign = $submission->getCampaign();
            $result[] = [
                'id' => $submission->getId(),
                'clientId' => $submission->getClient()->getId(),
                'clientName' => $submission->getClient()->getName(),
                'campaignId' => $campaign?->getId(),
                'campaignLabel' => $campaign?->getLabel(),
                'analysisLabel' => $submission->getAnalysisLabel(),
                'version' => $submission->getVersion(),
                'fileSize' => $submission->getFileSize(),
                'sha256' => $submission->getSha256(),
                'isSigned' => $submission->isSigned(),
                'submittedBy' => $submission->getCreator(),
                'submittedAt' => $submission->getCreatedAt()->format(DateTimeInterface::ATOM),
            ];
        }

        return $result;
    }

    /**
     * @return array{path: string, downloadName: string}
     */
    public function getFile(int $id): array
    {
        /** @var EntitySubmission|null $submission */
        $submission = $this->entitySubmissionTable->findById($id, false);
        if ($submission === null) {
            throw new Exception('Ce rapport n\'existe pas.', 404);
        }
        $path = $this->entitySubmissionService->getClientDirectory($submission->getClient())
            . '/' . $submission->getStoredName();
        if (!is_file($path)) {
            throw new Exception('Le fichier de ce rapport est introuvable sur le serveur.', 412);
        }

        return [
            'path' => $path,
            'downloadName' => sprintf(
                'rapport-%s-v%d.pdf',
                trim(preg_replace('/[^A-Za-z0-9]+/', '-', $submission->getAnalysisLabel()), '-') ?: 'analyse',
                $submission->getVersion()
            ),
        ];
    }
}
