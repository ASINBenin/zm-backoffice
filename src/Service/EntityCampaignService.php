<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Table\CampaignTable;
use Monarc\Core\Exception\Exception;

/**
 * Campagnes vues par une entité, qui s'identifie par son jeton : il n'y a aucun utilisateur connecté.
 */
class EntityCampaignService
{
    public function __construct(private CampaignTable $campaignTable)
    {
    }

    /**
     * Campagnes de l'entité, visibles dès leur publication ; $since (ISO 8601) ne garde que celles modifiées depuis.
     * serverTime est la valeur à renvoyer en $since à la synchronisation suivante.
     */
    public function getCampaigns(Client $client, ?string $since): array
    {
        $campaigns = [];
        foreach ($this->campaignTable->findVisibleToClient($client, $this->parseSince($since)) as $campaign) {
            $campaigns[] = $this->getPreparedCampaignData($campaign, $client);
        }

        return [
            'campaigns' => $campaigns,
            'serverTime' => (new DateTime())->format(DateTimeInterface::ATOM),
        ];
    }

    private function parseSince(?string $since): ?DateTime
    {
        if ($since === null || $since === '') {
            return null;
        }

        $date = DateTime::createFromFormat(DateTimeInterface::ATOM, $since);
        if ($date === false) {
            throw new Exception('La date de synchronisation doit être au format ISO 8601.', 412);
        }

        // Les dates sont enregistrées en base dans le fuseau du serveur
        return $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    /**
     * Données d'une campagne renvoyées à l'entité : ni les autres entités invitées, ni le modèle.
     */
    private function getPreparedCampaignData(Campaign $campaign, Client $client): array
    {
        return [
            'id' => $campaign->getId(),
            'label' => $campaign->getLabel(),
            'description' => $campaign->getDescription(),
            'status' => $campaign->getStatus(),
            'startDate' => $campaign->getStartDate()->format('Y-m-d'),
            'responseDeadline' => $campaign->getResponseDeadline()->format('Y-m-d'),
            'endDate' => $campaign->getEndDate()->format('Y-m-d'),
            'isSignatureRequired' => $campaign->isSignatureRequired(),
            'publishedAt' => $campaign->getPublishedAt()?->format(DateTimeInterface::ATOM),
            'closedAt' => $campaign->getClosedAt()?->format(DateTimeInterface::ATOM),
            'participation' => ['status' => $campaign->getParticipantOf($client)->getStatus()],
        ];
    }
}
