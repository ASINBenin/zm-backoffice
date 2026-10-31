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

    /**
     * Invitation : chaque entité invitée la reçoit à l'adresse de contact de sa fiche et à celle de son premier
     * utilisateur.
     */
    public function notifyPublished(Campaign $campaign): void
    {
        foreach ($campaign->getParticipants() as $participant) {
            $this->send(
                $this->getEntityAddresses($participant->getClient()),
                sprintf('[MONARC] Invitation à la campagne « %s »', $campaign->getLabel()),
                sprintf(
                    '<p>Votre entité, %s, est invitée à la campagne « %s ».</p>%s'
                    . '<p>Début : %s. <strong>Date limite de réponse : %s</strong>. Fin : %s.</p>%s'
                    . '<p>Pour participer, ouvrez le menu « Campagnes » de votre MONARC.</p>',
                    $this->escape($participant->getClient()->getName()),
                    $this->escape($campaign->getLabel()),
                    $campaign->getDescription() === null ? '' : '<p>' . $this->escape($campaign->getDescription()) . '</p>',
                    $campaign->getStartDate()->format('d/m/Y'),
                    $campaign->getResponseDeadline()->format('d/m/Y'),
                    $campaign->getEndDate()->format('d/m/Y'),
                    $campaign->isSignatureRequired() ? '<p>Le rapport devra être signé.</p>' : ''
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
                '<p>L\'entité %s participe à la campagne « %s » : son analyse a été créée.</p>',
                $this->escape($client->getName()),
                $this->escape($campaign->getLabel())
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
                . '<p>Le rapport est disponible dans l\'écran « Rapports soumis » du Back-Office.</p>',
                $this->escape($submission->getClient()->getName()),
                $submission->getVersion(),
                $this->escape($submission->getAnalysisLabel()),
                $this->getCampaignText($submission),
                $this->escape($submission->getCreator())
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
                . '<p>Elle est visible dans la fenêtre « Rapports » de votre MONARC.</p>',
                $submission->getVersion(),
                $this->escape($submission->getAnalysisLabel()),
                $this->getCampaignText($submission),
                self::APPRECIATION_LABELS[$submission->getAppreciationLevel()],
                $comment === null ? '' : '<p>' . nl2br($this->escape($comment)) . '</p>'
            )
        );
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
     * Tâche quotidienne : pour chaque campagne dont la date limite de réponse est passée, les administrateurs
     * apprennent quelles entités n'ont pas participé. Un seul courriel par campagne, même si la tâche repasse.
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
                    sprintf('[MONARC] Campagne « %s » : invitations expirées', $campaign->getLabel()),
                    sprintf(
                        '<p>La date limite de réponse de la campagne « %s » est dépassée (%s).</p>'
                        . '<p>Entités qui n\'ont pas participé :</p><ul>%s</ul>',
                        $this->escape($campaign->getLabel()),
                        $campaign->getResponseDeadline()->format('d/m/Y'),
                        implode('', $silentEntities)
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
