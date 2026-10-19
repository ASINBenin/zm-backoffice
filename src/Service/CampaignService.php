<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\Expr\Comparison;
use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\Entity\CampaignParticipant;
use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Table\CampaignTable;
use Monarc\BackOffice\Table\ClientTable;
use Monarc\Core\Entity\Model;
use Monarc\Core\Entity\User;
use Monarc\Core\Exception\Exception;
use Monarc\Core\InputFormatter\FormattedInputParams;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Table\ModelTable;

class CampaignService
{
    private User $connectedUser;

    public function __construct(
        private CampaignTable $campaignTable,
        private ClientTable $clientTable,
        private ModelTable $modelTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->connectedUser = $connectedUserService->getConnectedUser();
    }

    /**
     * Campagnes d'une page de la liste.
     */
    public function getList(FormattedInputParams $params): array
    {
        $result = [];
        /** @var Campaign $campaign */
        foreach ($this->campaignTable->findByParams($params) as $campaign) {
            $result[] = $this->getPreparedCampaignData($campaign);
        }

        return $result;
    }

    /**
     * Nombre total de campagnes pour la pagination.
     */
    public function getCount(FormattedInputParams $params): int
    {
        return $this->campaignTable->countByParams($params);
    }

    /**
     * Détail d'une campagne.
     */
    public function getCampaignData(int $id): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->campaignTable->findById($id);

        return $this->getPreparedCampaignData($campaign);
    }

    /**
     * Entités pouvant être invitées à une campagne.
     */
    public function getSelectableClients(FormattedInputParams $params): array
    {
        return array_map(static fn (Client $client) => [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'proxyAlias' => $client->getProxyAlias(),
        ], $this->clientTable->findByParams($params));
    }

    /**
     * Nombre total d'entités invitables pour la pagination.
     */
    public function getSelectableClientsCount(FormattedInputParams $params): int
    {
        return $this->clientTable->countByParams($params);
    }

    /**
     * Modèles pouvant servir à une nouvelle campagne.
     */
    public function getSelectableModels(FormattedInputParams $params): array
    {
        $language = $this->connectedUser->getLanguage();

        return array_map(static fn (Model $model) => [
            'id' => $model->getId(),
            'label' => $model->getLabel($language),
        ], $this->modelTable->findByParams($this->withSelectableModelsFilter($params)));
    }

    /**
     * Nombre total de modèles choisissables pour la pagination.
     */
    public function getSelectableModelsCount(FormattedInputParams $params): int
    {
        return $this->modelTable->countByParams($this->withSelectableModelsFilter($params));
    }

    /**
     * Crée une campagne en brouillon.
     */
    public function create(array $data): Campaign
    {
        $campaign = (new Campaign())->setCreator($this->connectedUser->getEmail());
        $this->fillCampaign($campaign, $data);

        $this->campaignTable->save($campaign);

        return $campaign;
    }

    /**
     * Modifie un brouillon.
     */
    public function update(int $id, array $data): void
    {
        $campaign = $this->getDraftCampaign($id);
        $this->fillCampaign($campaign, $data);
        $campaign->setUpdater($this->connectedUser->getEmail());

        $this->campaignTable->save($campaign);
    }

    /**
     * Supprime un brouillon.
     */
    public function delete(int $id): void
    {
        $this->campaignTable->remove($this->getDraftCampaign($id));
    }

    /**
     * Publie un brouillon et fige les échelles du modèle : toutes les entités évaluent sur les mêmes échelles.
     */
    public function publish(int $id): void
    {
        $campaign = $this->getDraftCampaign($id);
        if ($campaign->getParticipants()->isEmpty()) {
            throw new Exception('Invitez au moins une entité avant de publier la campagne.', 412);
        }
        if ($campaign->getResponseDeadline() < new DateTimeImmutable('today')) {
            throw new Exception('La date limite de réponse est dépassée.', 412);
        }

        $model = $this->validateModel($campaign->getModelId(), $campaign);
        $model->setAreScalesUpdatable(false)->setUpdater($this->connectedUser->getEmail());
        $this->modelTable->save($model);

        $campaign->publish()->setUpdater($this->connectedUser->getEmail());
        $this->campaignTable->save($campaign);
    }

    /**
     * Clôture définitivement une campagne publiée.
     */
    public function close(int $id): void
    {
        /** @var Campaign $campaign */
        $campaign = $this->campaignTable->findById($id);
        if (!$campaign->isPublished()) {
            throw new Exception('Seule une campagne publiée peut être clôturée.', 412);
        }

        $campaign->close()->setUpdater($this->connectedUser->getEmail());
        $this->campaignTable->save($campaign);
    }

    /**
     * Les entités travaillent sur le modèle d'une campagne publiée ou clôturée : il ne doit plus changer.
     */
    public function assertModelIsEditable(int $modelId): void
    {
        $campaign = $this->campaignTable->findOneByModelId($modelId);
        if ($campaign !== null && !$campaign->isDraft()) {
            throw new Exception(
                sprintf('Ce modèle est figé : il est utilisé par la campagne « %s ».', $campaign->getLabel()),
                412
            );
        }
    }

    /**
     * Campagne en brouillon, seule modifiable.
     */
    private function getDraftCampaign(int $id): Campaign
    {
        /** @var Campaign $campaign */
        $campaign = $this->campaignTable->findById($id);
        if (!$campaign->isDraft()) {
            throw new Exception('Seule une campagne en brouillon peut être modifiée, supprimée ou publiée.', 412);
        }

        return $campaign;
    }

    /**
     * Applique les données saisies après contrôle des dates et du modèle.
     */
    private function fillCampaign(Campaign $campaign, array $data): void
    {
        $startDate = $this->toDate($data['startDate']);
        $responseDeadline = $this->toDate($data['responseDeadline']);
        $endDate = $this->toDate($data['endDate']);
        if ($startDate > $responseDeadline || $responseDeadline > $endDate) {
            throw new Exception(
                'Les dates doivent respecter : début ≤ date limite de réponse ≤ fin.',
                412
            );
        }

        $this->validateModel((int)$data['modelId'], $campaign);

        $campaign->setLabel($data['label'])
            ->setDescription($data['description'] ?? null)
            ->setModelId((int)$data['modelId'])
            ->setDates($startDate, $responseDeadline, $endDate)
            ->setIsSignatureRequired((bool)($data['isSignatureRequired'] ?? false));

        $this->setParticipants($campaign, $data['clientIds'] ?? []);
    }

    /**
     * Modèle actif, spécifique et libre (ou déjà celui de la campagne).
     */
    private function validateModel(int $modelId, Campaign $campaign): Model
    {
        /** @var Model|null $model */
        $model = $this->modelTable->findById($modelId, false);
        if ($model === null || $model->getAnr() === null || $model->getStatus() !== Model::STATUS_ACTIVE) {
            throw new Exception('Le modèle choisi n\'existe pas.', 412);
        }
        if ($model->isGeneric()) {
            throw new Exception(
                'Le modèle d\'une campagne doit être spécifique : un modèle générique est visible par toutes les entités.',
                412
            );
        }

        $usedBy = $this->campaignTable->findOneByModelId($modelId);
        if ($usedBy !== null && $usedBy !== $campaign) {
            throw new Exception(
                sprintf('Ce modèle est déjà utilisé par la campagne « %s ».', $usedBy->getLabel()),
                412
            );
        }

        return $model;
    }

    /**
     * Ajoute et retire les entités invitées selon la liste saisie.
     *
     * @param int[] $clientIds
     */
    private function setParticipants(Campaign $campaign, array $clientIds): void
    {
        if (\count($clientIds) !== \count(array_unique($clientIds))) {
            throw new Exception('Une même entité ne peut être invitée qu\'une seule fois.', 412);
        }

        /** @var Client[] $clients */
        $clients = empty($clientIds) ? [] : $this->clientTable->findByIds($clientIds);
        if (\count($clients) !== \count($clientIds)) {
            throw new Exception('Une des entités choisies n\'existe pas.', 412);
        }

        $kept = [];
        foreach ($campaign->getParticipants()->toArray() as $participant) {
            if (\in_array($participant->getClient()->getId(), $clientIds, true)) {
                $kept[] = $participant->getClient()->getId();
            } else {
                $campaign->removeParticipant($participant);
            }
        }
        foreach ($clients as $client) {
            if (!\in_array($client->getId(), $kept, true)) {
                (new CampaignParticipant($campaign, $client))->setCreator($this->connectedUser->getEmail());
            }
        }
    }

    /**
     * Filtre des modèles choisissables : mêmes règles que validateModel().
     */
    private function withSelectableModelsFilter(FormattedInputParams $params): FormattedInputParams
    {
        $params->setFilterFor('status', ['value' => Model::STATUS_ACTIVE])
            ->setFilterFor('isGeneric', ['value' => 0])
            ->setFilterFor('anr.id', ['value' => 0, 'operator' => Comparison::GT]);

        $usedModelIds = $this->campaignTable->findUsedModelIds();
        if (!empty($usedModelIds)) {
            $params->setFilterFor('id', ['value' => $usedModelIds, 'operator' => Comparison::NIN]);
        }

        return $params;
    }

    /**
     * Date AAAA-MM-JJ à minuit.
     */
    private function toDate(string $value): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    }

    /**
     * Données d'une campagne renvoyées par l'API.
     */
    private function getPreparedCampaignData(Campaign $campaign): array
    {
        /** @var Model|null $model */
        $model = $this->modelTable->findById($campaign->getModelId(), false);

        return [
            'id' => $campaign->getId(),
            'label' => $campaign->getLabel(),
            'description' => $campaign->getDescription(),
            'status' => $campaign->getStatus(),
            'model' => [
                'id' => $campaign->getModelId(),
                'label' => $model?->getLabel($this->connectedUser->getLanguage()) ?? '',
            ],
            'startDate' => $campaign->getStartDate()->format('Y-m-d'),
            'responseDeadline' => $campaign->getResponseDeadline()->format('Y-m-d'),
            'endDate' => $campaign->getEndDate()->format('Y-m-d'),
            'isSignatureRequired' => $campaign->isSignatureRequired(),
            'publishedAt' => $campaign->getPublishedAt()?->format(DateTimeInterface::ATOM),
            'closedAt' => $campaign->getClosedAt()?->format(DateTimeInterface::ATOM),
            'participants' => array_map(static fn (CampaignParticipant $participant) => [
                'id' => $participant->getId(),
                'status' => $participant->getStatus(),
                'client' => [
                    'id' => $participant->getClient()->getId(),
                    'name' => $participant->getClient()->getName(),
                ],
            ], $campaign->getParticipants()->toArray()),
            'creator' => $campaign->getCreator(),
        ];
    }
}
