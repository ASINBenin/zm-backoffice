<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use DateTimeImmutable;
use DateTimeInterface;
use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\Entity\CampaignParticipant;
use Monarc\BackOffice\Entity\EntitySubmission;
use Monarc\BackOffice\Table\CampaignTable;
use Monarc\BackOffice\Table\EntitySubmissionTable;

/**
 * Chiffres de suivi des campagnes, calculés à partir des participations et des rapports reçus.
 */
class CampaignStatisticsService
{
    public function __construct(
        private CampaignTable $campaignTable,
        private EntitySubmissionTable $entitySubmissionTable
    ) {
    }

    /**
     * Vue d'ensemble de toutes les campagnes.
     */
    public function getOverview(): array
    {
        $today = new DateTimeImmutable('today');
        $campaignsByStatus = [
            Campaign::STATUS_DRAFT => 0,
            Campaign::STATUS_PUBLISHED => 0,
            Campaign::STATUS_CLOSED => 0,
        ];
        $lateEntitiesCount = 0;
        /** @var Campaign $campaign */
        foreach ($this->campaignTable->findAll() as $campaign) {
            $campaignsByStatus[$campaign->getStatus()]++;
            if ($campaign->isPublished() && $today > $campaign->getResponseDeadline()) {
                foreach ($campaign->getParticipants() as $participant) {
                    $lateEntitiesCount += (int)!$participant->isParticipating();
                }
            }
        }

        $pendingAppreciationsCount = 0;
        foreach ($this->getLatestSubmissions($this->entitySubmissionTable->findFiltered(null, null)) as $submission) {
            $pendingAppreciationsCount += (int)($submission->getAppreciationLevel() === null);
        }

        return [
            'campaigns' => $campaignsByStatus,
            'lateEntities' => $lateEntitiesCount,
            'pendingAppreciations' => $pendingAppreciationsCount,
        ];
    }

    /**
     * Avancement des entités, rapports, appréciations et délais d'une campagne.
     */
    public function getCampaignStatistics(int $campaignId): array
    {
        /** @var Campaign $campaign */
        $campaign = $this->campaignTable->findById($campaignId);
        $submissions = $this->entitySubmissionTable->findFiltered(null, $campaignId);
        $today = new DateTimeImmutable('today');

        $firstSubmissionDates = [];
        foreach ($submissions as $submission) {
            $clientId = $submission->getClient()->getId();
            $date = $this->toDate($submission->getCreatedAt());
            if (!isset($firstSubmissionDates[$clientId]) || $date < $firstSubmissionDates[$clientId]) {
                $firstSubmissionDates[$clientId] = $date;
            }
        }

        $progress = ['toStart' => 0, 'late' => 0, 'inProgress' => 0, 'reported' => 0];
        $startDates = [];
        $lateStartsCount = 0;
        /** @var CampaignParticipant $participant */
        foreach ($campaign->getParticipants() as $participant) {
            $clientId = $participant->getClient()->getId();
            if (isset($firstSubmissionDates[$clientId])) {
                $progress['reported']++;
            } elseif ($participant->isParticipating()) {
                $progress['inProgress']++;
            } else {
                $progress[$today > $campaign->getResponseDeadline() ? 'late' : 'toStart']++;
            }
            if ($participant->isParticipating() && $participant->getUpdatedAt() !== null) {
                // La participation est la seule modification d'une invitation : sa date est celle du démarrage
                $startDates[] = $this->toDate($participant->getUpdatedAt());
                $lateStartsCount += (int)($this->toDate($participant->getUpdatedAt()) > $campaign->getResponseDeadline());
            }
        }

        $appreciations = array_fill_keys(EntitySubmission::APPRECIATION_LEVELS, 0) + ['none' => 0];
        foreach ($this->getLatestSubmissions($submissions) as $submission) {
            $appreciations[$submission->getAppreciationLevel() ?? 'none']++;
        }

        $lateReportsCount = 0;
        foreach ($firstSubmissionDates as $date) {
            $lateReportsCount += (int)($date > $campaign->getEndDate());
        }

        return [
            'entitiesCount' => $campaign->getParticipants()->count(),
            'progress' => $progress,
            'reports' => [
                'versionsCount' => \count($submissions),
                'signedCount' => \count(array_filter($submissions, static fn ($s) => $s->isSigned())),
            ],
            'appreciations' => $appreciations,
            'delays' => [
                'lateStarts' => $lateStartsCount,
                'lateReports' => $lateReportsCount,
            ],
            'timeline' => $this->getTimeline($campaign, $startDates, array_values($firstSubmissionDates)),
        ];
    }

    /**
     * Cumul par jour des entités qui ont démarré et de celles qui ont envoyé un rapport, du début de la campagne à
     * aujourd'hui (ou à sa fin si elle est passée, prolongée jusqu'au dernier rapport reçu).
     *
     * @param DateTimeImmutable[] $startDates
     * @param DateTimeImmutable[] $reportDates
     */
    private function getTimeline(Campaign $campaign, array $startDates, array $reportDates): array
    {
        $from = $campaign->getStartDate();
        $to = min(new DateTimeImmutable('today'), max([$campaign->getEndDate(), ...$startDates, ...$reportDates]));
        $timeline = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $timeline[] = [
                'date' => $day->format('Y-m-d'),
                'started' => \count(array_filter($startDates, static fn ($date) => $date <= $day)),
                'reported' => \count(array_filter($reportDates, static fn ($date) => $date <= $day)),
            ];
        }

        return $timeline;
    }

    /**
     * Dernière version de chaque analyse envoyée.
     *
     * @param EntitySubmission[] $submissions
     *
     * @return EntitySubmission[]
     */
    private function getLatestSubmissions(array $submissions): array
    {
        $latest = [];
        foreach ($submissions as $submission) {
            $key = $submission->getClient()->getId() . '-' . ($submission->getCampaign()?->getId() ?? 0) . '-'
                . $submission->getSourceAnrId();
            if (!isset($latest[$key]) || $submission->getVersion() > $latest[$key]->getVersion()) {
                $latest[$key] = $submission;
            }
        }

        return array_values($latest);
    }

    private function toDate(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($dateTime)->setTime(0, 0);
    }
}
