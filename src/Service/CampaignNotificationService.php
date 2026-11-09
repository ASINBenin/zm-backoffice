<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use Monarc\BackOffice\Entity\Campaign;
use DateTimeImmutable;
use Monarc\BackOffice\Entity\CampaignNotification;
use Monarc\BackOffice\Entity\CampaignParticipant;
use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Entity\EntitySubmission;
use Monarc\BackOffice\Table\CampaignNotificationTable;
use Monarc\BackOffice\Table\CampaignTable;
use Monarc\Core\Entity\UserRole;
use Monarc\Core\Service\ConfigService;
use Monarc\Core\Service\MailService;
use Monarc\Core\Table\UserTable;
use Throwable;

/**
 * Courriels envoyés aux entités et aux administrateurs ASIN à chaque étape d'une campagne.
 * Un échec d'envoi ne remet jamais en cause l'action qui l'a déclenché : il est seulement consigné.
 */
class CampaignNotificationService
{
    private const APPRECIATION_LABELS = [
        EntitySubmission::APPRECIATION_LEVELS[0] => 'Insuffisant',
        EntitySubmission::APPRECIATION_LEVELS[1] => 'À améliorer',
        EntitySubmission::APPRECIATION_LEVELS[2] => 'Satisfaisant',
        EntitySubmission::APPRECIATION_LEVELS[3] => 'Très satisfaisant',
    ];

    public function __construct(
        private MailService $mailService,
        private ConfigService $configService,
        private UserTable $userTable,
        private CampaignTable $campaignTable,
        private CampaignNotificationTable $campaignNotificationTable
    ) {
    }

    public function notifyPublished(Campaign $campaign): void
    {
        $this->notifyInvited($campaign, $campaign->getParticipants()->toArray());
    }

    /**
     * Invitation, à la publication ou pour une entité ajoutée ensuite : chaque entité la reçoit à l'adresse de contact
     * de sa fiche et à celle de son premier utilisateur.
     *
     * @param CampaignParticipant[] $participants
     */
    public function notifyInvited(Campaign $campaign, array $participants): void
    {
        foreach ($participants as $participant) {
            $this->send(
                $this->getEntityAddresses($participant->getClient()),
                sprintf('[MONARC] Invitation à la campagne « %s »', $campaign->getLabel()),
                sprintf(
                    '<p>Votre entité, %s, est invitée à la campagne « %s ». La participation est obligatoire.</p>%s'
                    . '<p>Début : %s. <strong>Date de démarrage attendue : %s</strong>. Fin : %s.</p>%s'
                    . '<p>Pour participer, ouvrez le menu « Campagnes » de votre MONARC.</p>%s',
                    $this->escape($participant->getClient()->getName()),
                    $this->escape($campaign->getLabel()),
                    (string)$campaign->getDescription() === ''
                        ? ''
                        : '<p>' . $this->escape($campaign->getDescription()) . '</p>',
                    $campaign->getStartDate()->format('d/m/Y'),
                    $campaign->getResponseDeadline()->format('d/m/Y'),
                    $campaign->getEndDate()->format('d/m/Y'),
                    $campaign->isSignatureRequired() ? '<p>Le rapport devra être signé.</p>' : '',
                    $this->getEntityCampaignsLink($participant->getClient(), $campaign)
                )
            );
        }
    }

    public function notifyParticipation(Campaign $campaign, Client $client): void
    {
        $this->send(
            $this->getAdministratorAddresses(),
            sprintf('[MONARC] %s participe à la campagne « %s »', $client->getName(), $campaign->getLabel()),
            sprintf(
                '<p>L\'entité %s participe à la campagne « %s » : son analyse a été créée.</p>%s',
                $this->escape($client->getName()),
                $this->escape($campaign->getLabel()),
                $this->getBackOfficeCampaignLink($campaign)
            )
        );
    }

    public function notifySubmission(EntitySubmission $submission): void
    {
        $this->send(
            $this->getAdministratorAddresses(),
            sprintf('[MONARC] %s a envoyé un rapport', $submission->getClient()->getName()),
            sprintf(
                '<p>L\'entité %s a envoyé la version %d du rapport de l\'analyse « %s » (%s), par %s.</p>'
                . '<p>Le rapport est disponible dans le Back-Office.</p>%s',
                $this->escape($submission->getClient()->getName()),
                $submission->getVersion(),
                $this->escape($submission->getAnalysisLabel()),
                $this->getCampaignText($submission),
                $this->escape($submission->getCreator()),
                $submission->getCampaign() === null
                    ? $this->getLink($this->configService->getHost() . '/#/admin/submissions', 'Ouvrir les rapports')
                    : $this->getBackOfficeCampaignLink($submission->getCampaign())
            )
        );
    }

    /**
     * L'appréciation est envoyée aux adresses connues de l'entité et à l'auteur de la version.
     */
    public function notifyAppreciation(EntitySubmission $submission): void
    {
        $comment = $submission->getAppreciationComment();
        $this->send(
            array_merge($this->getEntityAddresses($submission->getClient()), [$submission->getCreator()]),
            sprintf('[MONARC] Appréciation de votre rapport « %s »', $submission->getAnalysisLabel()),
            sprintf(
                '<p>L\'ASIN a apprécié la version %d du rapport de l\'analyse « %s » (%s).</p>'
                . '<p><strong>Appréciation : %s</strong></p>%s'
                . '<p>Elle est visible dans les rapports de l\'analyse, dans votre MONARC.</p>%s',
                $submission->getVersion(),
                $this->escape($submission->getAnalysisLabel()),
                $this->getCampaignText($submission),
                self::APPRECIATION_LABELS[$submission->getAppreciationLevel()],
                // Commentaire déjà nettoyé à l'enregistrement (HtmlSanitizer)
                $comment ?? '',
                $this->getEntityCampaignsLink($submission->getClient(), $submission->getCampaign())
            )
        );
    }

    /**
     * Nouvelles dates envoyées à toutes les entités invitées. Avec une date de démarrage attendue repoussée, le constat
     * des entités en retard sera refait à la nouvelle échéance.
     */
    public function notifyDatesExtended(Campaign $campaign, bool $isDeadlineExtended): void
    {
        if ($isDeadlineExtended) {
            $this->campaignNotificationTable->removeSent($campaign, CampaignNotification::TYPE_INVITATIONS_EXPIRED);
        }
        foreach ($campaign->getParticipants() as $participant) {
            $this->send(
                $this->getEntityAddresses($participant->getClient()),
                sprintf('[MONARC] Campagne « %s » : dates repoussées', $campaign->getLabel()),
                sprintf(
                    '<p>Les dates de la campagne « %s » ont été repoussées.</p>'
                    . '<p><strong>Date de démarrage attendue : %s</strong>. Fin : %s.</p>%s',
                    $this->escape($campaign->getLabel()),
                    $campaign->getResponseDeadline()->format('d/m/Y'),
                    $campaign->getEndDate()->format('d/m/Y'),
                    $this->getEntityCampaignsLink($participant->getClient(), $campaign)
                )
            );
        }
    }

    public function notifyClosed(Campaign $campaign): void
    {
        foreach ($campaign->getParticipants() as $participant) {
            if ($participant->getStatus() !== CampaignParticipant::STATUS_PARTICIPATING) {
                continue;
            }
            $this->send(
                $this->getEntityAddresses($participant->getClient()),
                sprintf('[MONARC] Campagne « %s » clôturée', $campaign->getLabel()),
                sprintf(
                    '<p>La campagne « %s » est clôturée : l\'analyse n\'est plus modifiable et aucun rapport ne peut '
                    . 'plus être envoyé.</p>',
                    $this->escape($campaign->getLabel())
                )
            );
        }
    }

    /**
     * Tâche quotidienne : pour chaque campagne dont la date de démarrage attendue est passée, les administrateurs
     * apprennent quelles entités n'ont pas encore démarré (elles le peuvent jusqu'à la fin). Un seul courriel par
     * campagne, même si la tâche repasse.
     *
     * @return int Nombre de campagnes traitées.
     */
    public function notifyExpiredInvitations(): int
    {
        $campaignsCount = 0;
        foreach ($this->campaignTable->findPublishedWithDeadlineBefore(new DateTimeImmutable('today')) as $campaign) {
            if ($this->campaignNotificationTable->isSent($campaign, CampaignNotification::TYPE_INVITATIONS_EXPIRED)) {
                continue;
            }

            $silentEntities = [];
            foreach ($campaign->getParticipants() as $participant) {
                if (!$participant->isParticipating()) {
                    $silentEntities[] = '<li>' . $this->escape($participant->getClient()->getName()) . '</li>';
                }
            }
            if ($silentEntities !== []) {
                $sentCount = $this->send(
                    $this->getAdministratorAddresses(),
                    sprintf('[MONARC] Campagne « %s » : entités en retard', $campaign->getLabel()),
                    sprintf(
                        '<p>La date de démarrage attendue de la campagne « %s » est passée (%s).</p>'
                        . '<p>Entités qui n\'ont pas encore démarré :</p><ul>%s</ul>%s',
                        $this->escape($campaign->getLabel()),
                        $campaign->getResponseDeadline()->format('d/m/Y'),
                        implode('', $silentEntities),
                        $this->getBackOfficeCampaignLink($campaign)
                    )
                );
                if ($sentCount === 0) {
                    // Aucun courriel parti : la tâche de demain réessaie
                    continue;
                }
            }
            $this->campaignNotificationTable->save(
                new CampaignNotification($campaign, CampaignNotification::TYPE_INVITATIONS_EXPIRED)
            );
            $campaignsCount++;
        }

        return $campaignsCount;
    }

    /**
     * Administrateurs ASIN des campagnes.
     *
     * @return string[]
     */
    private function getAdministratorAddresses(): array
    {
        return $this->userTable->findActiveEmailsByRole(UserRole::CAMP_ADMIN);
    }

    /**
     * Le BO ne connaît de l'entité que l'adresse de contact de sa fiche et celle de son premier utilisateur.
     *
     * @return string[]
     */
    private function getEntityAddresses(Client $client): array
    {
        return [$client->getContactEmail(), $client->getFirstUserEmail()];
    }

    private function getCampaignText(EntitySubmission $submission): string
    {
        return $submission->getCampaign() === null
            ? 'hors campagne'
            : 'campagne « ' . $this->escape($submission->getCampaign()->getLabel()) . ' »';
    }

    /**
     * Page de la campagne dans l'entité, servie sous /<alias>/ de l'adresse publique des entités. L'entité ne connaît
     * pas encore l'identifiant local de la campagne : elle l'ouvre à partir de celui du BO. Sans campagne (rapport
     * hors campagne), le lien mène à l'écran « Campagnes ». Sans adresse publique (installation hors Docker), le
     * courriel garde seulement le texte.
     */
    private function getEntityCampaignsLink(Client $client, ?Campaign $campaign): string
    {
        $entitiesHost = (string)$this->configService->getConfigOption('entitiesPublicHost', '');
        if ($entitiesHost === '') {
            return '';
        }

        return $this->getLink(
            rtrim($entitiesHost, '/') . '/' . rawurlencode($client->getProxyAlias()) . '/#/client/campaigns'
            . ($campaign === null ? '' : '?campaign=' . $campaign->getId()),
            $campaign === null ? 'Ouvrir les campagnes dans MONARC' : 'Ouvrir la campagne dans MONARC'
        );
    }

    private function getBackOfficeCampaignLink(Campaign $campaign): string
    {
        return $this->getLink(
            $this->configService->getHost() . '/#/admin/campaigns/' . $campaign->getId(),
            'Ouvrir la campagne dans le Back-Office'
        );
    }

    private function getLink(string $url, string $label): string
    {
        return sprintf('<p><a href="%s">%s</a></p>', $this->escape($url), $this->escape($label));
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param string[] $addresses
     *
     * @return int Nombre de courriels partis.
     */
    private function send(array $addresses, string $subject, string $message): int
    {
        $sentCount = 0;
        foreach (array_unique(array_filter($addresses)) as $address) {
            try {
                $this->mailService->send($address, $subject, $message, $this->configService->getEmail());
                $sentCount++;
            } catch (Throwable $e) {
                error_log(sprintf('Campaign notification "%s" to %s failed: %s', $subject, $address, $e->getMessage()));
            }
        }

        return $sentCount;
    }
}
