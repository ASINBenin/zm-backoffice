<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use Monarc\BackOffice\Entity\EntitySubmission;
use Monarc\BackOffice\Table\EntitySubmissionTable;
use Monarc\Core\Entity\User;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Service\ConnectedUserService;

/**
 * Rapports soumis par les entités, vus par les administrateurs du BO.
 */
class SubmissionService
{
    private User $connectedUser;

    public function __construct(
        private EntitySubmissionTable $entitySubmissionTable,
        private EntitySubmissionService $entitySubmissionService,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
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
            $result[] = $this->entitySubmissionService->getPreparedSubmissionData($submission) + [
                'clientId' => $submission->getClient()->getId(),
                'clientName' => $submission->getClient()->getName(),
                'campaignLabel' => $campaign?->getLabel(),
                'appreciatedBy' => $submission->getAppreciatedBy(),
            ];
        }

        return $result;
    }

    /**
     * Donne ou corrige l'appréciation d'une version : sans effet sur la campagne, qu'elle soit ouverte ou clôturée.
     *
     * @param array{level: string, comment?: string|null} $data
     */
    public function appreciate(int $id, array $data): void
    {
        $submission = $this->getSubmission($id)
            ->appreciate($data['level'], $data['comment'] ?? null, $this->connectedUser->getEmail());
        $this->entitySubmissionTable->save($submission);
    }

    /**
     * @return array{path: string, downloadName: string}
     */
    public function getFile(int $id): array
    {
        $submission = $this->getSubmission($id);
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

    private function getSubmission(int $id): EntitySubmission
    {
        /** @var EntitySubmission|null $submission */
        $submission = $this->entitySubmissionTable->findById($id, false);
        if ($submission === null) {
            throw new Exception('Ce rapport n\'existe pas.', 404);
        }

        return $submission;
    }
}
