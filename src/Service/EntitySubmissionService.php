<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use DateTimeInterface;
use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Entity\EntitySubmission;
use Monarc\BackOffice\Table\CampaignTable;
use Monarc\BackOffice\Table\EntitySubmissionTable;
use Monarc\Core\Exception\Exception;
use Monarc\Core\Helper\FileUploadHelperTrait;
use Throwable;

/**
 * Rapports soumis par une entité, qui s'identifie par son jeton : il n'y a aucun utilisateur connecté.
 */
class EntitySubmissionService
{
    use FileUploadHelperTrait;

    public const MAX_FILE_SIZE = 50 * 1024 * 1024;

    public function __construct(
        private EntitySubmissionTable $entitySubmissionTable,
        private CampaignTable $campaignTable
    ) {
    }

    /**
     * Enregistre le rapport PDF comme nouvelle version de l'analyse, dans la campagne ou hors campagne.
     * Les versions précédentes sont conservées.
     *
     * @param array{campaignId?: int, anrId: int, analysisLabel: string, isSigned?: bool, submittedBy: string} $data
     * @param array $file Fichier reçu (structure de $_FILES).
     *
     * @return array{submission: array}
     */
    public function submit(Client $client, array $data, array $file): array
    {
        $campaign = empty($data['campaignId']) ? null : $this->getOpenCampaign($client, (int)$data['campaignId']);
        $this->validateFile($file);

        $directory = $this->getClientDirectory($client);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new Exception(sprintf('The directory "%s" was not created.', $directory));
        }
        $storedName = bin2hex(random_bytes(16)) . '.pdf';
        $sha256 = hash_file('sha256', $file['tmp_name']);
        $filePath = $this->moveTmpFile($file, $directory, $storedName);

        try {
            $submission = new EntitySubmission(
                $client,
                $campaign,
                (int)$data['anrId'],
                $data['analysisLabel'],
                $this->entitySubmissionTable->getLastVersion($client, $campaign, (int)$data['anrId']) + 1,
                $storedName,
                (int)$file['size'],
                $sha256,
                (bool)($data['isSigned'] ?? false),
                $data['submittedBy']
            );
            $this->entitySubmissionTable->save($submission);
        } catch (Throwable $e) {
            unlink($filePath);

            throw $e;
        }

        return ['submission' => $this->getPreparedSubmissionData($submission)];
    }

    /**
     * Versions soumises par l'entité, les plus récentes d'abord ; $campaignId ne garde que celles d'une campagne.
     *
     * @return array{submissions: array[]}
     */
    public function getSubmissions(Client $client, ?int $campaignId): array
    {
        $submissions = [];
        foreach ($this->entitySubmissionTable->findFiltered($client->getId(), $campaignId) as $submission) {
            $submissions[] = $this->getPreparedSubmissionData($submission);
        }

        return ['submissions' => $submissions];
    }

    /**
     * Dossier des fichiers de l'entité, dans le volume de données du BO.
     */
    public function getClientDirectory(Client $client): string
    {
        return DATA_PATH . '/submissions/' . $client->getId();
    }

    /**
     * Campagne où l'entité participe, tant que le BO ne l'a pas clôturée.
     */
    private function getOpenCampaign(Client $client, int $campaignId): Campaign
    {
        /** @var Campaign|null $campaign */
        $campaign = $this->campaignTable->findById($campaignId, false);
        $participant = $campaign?->getParticipantOf($client);
        if ($participant === null || $campaign->isDraft()) {
            throw new Exception('Cette campagne n\'existe pas pour cette entité.', 412);
        }
        if ($campaign->isClosed()) {
            throw new Exception('La campagne est clôturée : aucune version ne peut plus être soumise.', 412);
        }
        if (!$participant->isParticipating()) {
            throw new Exception('L\'entité doit participer à la campagne avant de soumettre un rapport.', 412);
        }

        return $campaign;
    }

    /**
     * Le contenu est vérifié : le nom ou le type annoncé par l'entité ne prouvent pas que le fichier est un PDF.
     */
    private function validateFile(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
            || ($error === UPLOAD_ERR_OK && $file['size'] > self::MAX_FILE_SIZE)
        ) {
            throw new Exception(
                sprintf('Le rapport dépasse la taille maximale de %d Mo.', self::MAX_FILE_SIZE / 1024 / 1024),
                412
            );
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception('Le rapport n\'a pas été reçu : fichier manquant ou transfert interrompu.', 412);
        }
        if (file_get_contents($file['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            throw new Exception('Le rapport doit être un fichier PDF.', 412);
        }
    }

    private function getPreparedSubmissionData(EntitySubmission $submission): array
    {
        return [
            'id' => $submission->getId(),
            'campaignId' => $submission->getCampaign()?->getId(),
            'anrId' => $submission->getSourceAnrId(),
            'analysisLabel' => $submission->getAnalysisLabel(),
            'version' => $submission->getVersion(),
            'fileSize' => $submission->getFileSize(),
            'sha256' => $submission->getSha256(),
            'isSigned' => $submission->isSigned(),
            'submittedBy' => $submission->getCreator(),
            'submittedAt' => $submission->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
