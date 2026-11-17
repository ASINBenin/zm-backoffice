<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use DateTimeImmutable;
use Exception;
use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Provider\StatsApiProvider;
use Monarc\BackOffice\Table\ClientTable;
use Monarc\Core\Entity\Referential;
use Monarc\Core\Service\ConnectedUserService;
use Monarc\Core\Table\ReferentialTable;
use Monarc\Core\Table\ThreatTable;
use Monarc\Core\Table\VulnerabilityTable;

/**
 * Comparaison des entités à partir des dernières statistiques de toutes leurs analyses
 * (risques, conformité, menaces, vulnérabilités).
 * Chaque analyse couvre un périmètre distinct : une entité est l'ensemble de ses analyses.
 */
class InterEntityStatisticsService
{
    private const RISK_LEVELS = ['Low risks' => 'low', 'Medium risks' => 'medium', 'High risks' => 'high'];

    private const RISK_TYPES = ['informational', 'operational'];

    // Période => [nombre d'intervalles, longueur, unité] : une douzaine de points au plus au-delà d'un mois
    // pour garder le graphique lisible et la lecture rapide
    private const EVOLUTION_PERIODS = [
        '1m' => [30, 1, 'day'],
        '3m' => [12, 1, 'week'],
        '6m' => [12, 2, 'week'],
        '12m' => [12, 1, 'month'],
    ];

    private int $language;

    private ?array $entityNames = null;

    public function __construct(
        private StatsApiProvider $statsApiProvider,
        private ClientTable $clientTable,
        private ReferentialTable $referentialTable,
        private ThreatTable $threatTable,
        private VulnerabilityTable $vulnerabilityTable,
        ConnectedUserService $connectedUserService
    ) {
        $this->language = $connectedUserService->getConnectedUser()->getLanguage();
    }

    public function getRisks(): array
    {
        return $this->aggregateRisks($this->mapRiskStatsByAnr($this->statsApiProvider->getLastStats('risk')));
    }

    public function getCompliance(): array
    {
        $riskStatsByAnr = $this->mapRiskStatsByAnr($this->statsApiProvider->getLastStats('risk'));

        return $this->aggregateCompliance($this->statsApiProvider->getLastStats('compliance'), $riskStatsByAnr);
    }

    /**
     * Évolution sur la période, un point par intervalle : part des risques élevés (actuels, résiduels)
     * et conformité par référentiel. Une date sans collecte laisse un point vide.
     */
    public function getEvolution(string $period): array
    {
        if (!isset(self::EVOLUTION_PERIODS[$period])) {
            throw new Exception('La période demandée n\'est pas prise en charge.', 412);
        }
        [$intervals, $length, $unit] = self::EVOLUTION_PERIODS[$period];
        $dates = [];
        $today = new DateTimeImmutable();
        for ($step = $intervals; $step >= 0; $step--) {
            $dates[] = $today->modify('-' . $step * $length . ' ' . $unit)->format('Y-m-d');
        }

        $riskStatsByDate = $this->getStatsByDate('risk', $dates, $unit === 'day');
        $complianceStatsByDate = $this->getStatsByDate('compliance', $dates, $unit === 'day');
        $entities = [];
        $referentials = [];
        foreach ($dates as $index => $date) {
            $riskStatsByAnr = $this->mapRiskStatsByAnr($riskStatsByDate[$date] ?? []);
            foreach ($this->aggregateRisks($riskStatsByAnr) as $row) {
                $entities[$row['entity']]['highCurrent'][$index] = $this->getHighRisksShare($row['current']);
                $entities[$row['entity']]['highResidual'][$index] = $this->getHighRisksShare($row['residual']);
            }
            $complianceStats = $complianceStatsByDate[$date] ?? [];
            foreach ($this->aggregateCompliance($complianceStats, $riskStatsByAnr) as $referential) {
                $referentials[$referential['uuid']] = [
                    'uuid' => $referential['uuid'],
                    'label' => $referential['label'],
                ];
                foreach ($referential['rows'] as $row) {
                    $entities[$row['entity']]['compliance'][$referential['uuid']][$index] = $row['current'];
                }
            }
        }

        // Séries complètes : une valeur, ou null sans collecte, par date
        $series = [];
        foreach ($entities as $entity => $values) {
            $series[] = [
                'entity' => $entity,
                'highCurrent' => $this->toSeries($values['highCurrent'] ?? [], count($dates)),
                'highResidual' => $this->toSeries($values['highResidual'] ?? [], count($dates)),
                'compliance' => array_map(
                    fn(array $points) => $this->toSeries($points, count($dates)),
                    $values['compliance'] ?? []
                ),
            ];
        }
        $referentials = array_values($referentials);
        usort($referentials, static fn(array $a, array $b) => strcasecmp($a['label'], $b['label']));

        return ['dates' => $dates, 'referentials' => $referentials, 'entities' => $this->sortByEntity($series)];
    }

    /**
     * Risques de toutes les analyses de chaque entité, additionnés par niveau.
     */
    private function aggregateRisks(array $riskStatsByAnr): array
    {
        $entities = [];
        foreach ($riskStatsByAnr as $stats) {
            $entity = $stats['entity'];
            $entities[$entity] ??= [
                'entity' => $entity,
                'analysesCount' => 0,
                'date' => $stats['date'],
                'current' => $this->getEmptyRiskLevels(),
                'residual' => $this->getEmptyRiskLevels(),
            ];
            $entities[$entity]['analysesCount']++;
            $entities[$entity]['date'] = max($entities[$entity]['date'], $stats['date']);
            foreach (['current', 'residual'] as $state) {
                $entities[$entity][$state] = $this->addRiskLevels(
                    $entities[$entity][$state],
                    $stats['data']['risks'][$state]
                );
            }
        }

        return $this->sortByEntity($entities);
    }

    /**
     * Conformité de chaque entité par référentiel commun : moyenne des analyses qui utilisent ce référentiel.
     */
    private function aggregateCompliance(array $complianceStats, array $riskStatsByAnr): array
    {
        $entitiesByAnr = array_column($riskStatsByAnr, 'entity', 'anr');
        $commonReferentials = [];
        /** @var Referential $referential */
        foreach ($this->referentialTable->findAll() as $referential) {
            $commonReferentials[$referential->getUuid()] = $referential;
        }

        $analysesByReferential = [];
        foreach ($complianceStats as $stats) {
            if (!isset($entitiesByAnr[$stats['anr']])) {
                continue;
            }
            foreach ($stats['data'] as $referentialStats) {
                // Les référentiels propres à une entité ne sont pas comparables
                if (!isset($commonReferentials[$referentialStats['referential']])) {
                    continue;
                }
                $analysesByReferential[$referentialStats['referential']][$entitiesByAnr[$stats['anr']]][] =
                    $this->getComplianceRatios($referentialStats) + ['date' => $stats['date']];
            }
        }

        $referentials = [];
        foreach ($analysesByReferential as $uuid => $analysesByEntity) {
            $rows = [];
            foreach ($analysesByEntity as $entity => $analyses) {
                $rows[$entity] = ['entity' => $entity] + $this->getAverageCompliance($analyses);
            }
            $referentials[] = [
                'uuid' => $uuid,
                'label' => $commonReferentials[$uuid]->getLabel($this->language),
                'rows' => $this->sortByEntity($rows),
            ];
        }
        usort($referentials, static fn(array $a, array $b) => strcasecmp($a['label'], $b['label']));

        return $referentials;
    }

    public function getThreats(): array
    {
        return $this->getKnowledgeBaseItems('threat', $this->threatTable->findAll());
    }

    public function getVulnerabilities(): array
    {
        return $this->getKnowledgeBaseItems('vulnerability', $this->vulnerabilityTable->findAll());
    }

    /**
     * Menaces ou vulnérabilités de la base commune présentes dans les analyses de chaque entité :
     * risque maximal et nombre de risques concernés, sur l'ensemble de ses analyses.
     */
    private function getKnowledgeBaseItems(string $type, array $commonItems): array
    {
        $labels = [];
        foreach ($commonItems as $item) {
            $labels[$item->getUuid()] = $item->getLabel($this->language);
        }
        $riskStatsByAnr = $this->mapRiskStatsByAnr($this->statsApiProvider->getLastStats('risk'));
        $entitiesByAnr = array_column($riskStatsByAnr, 'entity', 'anr');

        $items = [];
        foreach ($this->statsApiProvider->getLastStats($type) as $stats) {
            if (!isset($entitiesByAnr[$stats['anr']])) {
                continue;
            }
            $entity = $entitiesByAnr[$stats['anr']];
            foreach ($stats['data'] as $itemStats) {
                // Les éléments créés par une entité ne sont pas comparables
                if (!isset($labels[$itemStats['uuid']]) || (int)$itemStats['count'] === 0) {
                    continue;
                }
                $uuid = $itemStats['uuid'];
                $items[$uuid] ??= ['uuid' => $uuid, 'label' => $labels[$uuid], 'entities' => []];
                $values = $items[$uuid]['entities'][$entity] ?? ['maxRisk' => 0, 'risksCount' => 0, 'averageRate' => 0];
                $risksCount = $values['risksCount'] + (int)$itemStats['count'];
                $items[$uuid]['entities'][$entity] = [
                    'maxRisk' => max($values['maxRisk'], (int)$itemStats['maxRisk']),
                    'risksCount' => $risksCount,
                    // Moyenne des analyses pondérée par leur nombre de risques
                    'averageRate' => round(($values['averageRate'] * $values['risksCount']
                        + (float)$itemStats['averageRate'] * (int)$itemStats['count']) / $risksCount, 2),
                ];
            }
        }

        $items = array_values($items);
        usort($items, static fn(array $a, array $b) => count($b['entities']) <=> count($a['entities'])
            ?: strcasecmp($a['label'], $b['label']));

        return $items;
    }

    /**
     * Statistiques de risques par analyse, rattachées à l'entité par leur contexte.
     */
    private function mapRiskStatsByAnr(array $riskStats): array
    {
        $entityNames = $this->getEntityNames();
        $statsByAnr = [];
        foreach ($riskStats as $stats) {
            $entity = $stats['data']['context']['entity'] ?? null;
            if ($entity === null || !isset($entityNames[$entity])) {
                continue;
            }
            $statsByAnr[$stats['anr']] = [
                'anr' => $stats['anr'],
                'entity' => $entityNames[$entity],
                'date' => $stats['date'],
                'data' => $stats['data'],
            ];
        }

        return $statsByAnr;
    }

    /**
     * Statistiques groupées par date : toute la période en une lecture quand chaque jour est utilisé,
     * sinon une lecture par date pour ne pas charger les jours intermédiaires.
     */
    private function getStatsByDate(string $type, array $dates, bool $isDaily): array
    {
        if ($isDaily) {
            $statsByDate = [];
            foreach ($this->statsApiProvider->getStatsBetween($type, reset($dates), end($dates)) as $stats) {
                $statsByDate[substr($stats['date'], 0, 10)][] = $stats;
            }

            return $statsByDate;
        }

        $statsByDate = [];
        foreach ($dates as $date) {
            $statsByDate[$date] = $this->statsApiProvider->getStatsBetween($type, $date, $date);
        }

        return $statsByDate;
    }

    private function getEntityNames(): array
    {
        if ($this->entityNames === null) {
            $this->entityNames = [];
            /** @var Client $client */
            foreach ($this->clientTable->findAll() as $client) {
                // Même normalisation que le nom d'entité du déployeur
                $alias = preg_replace('/[^a-z0-9]/', '_', strtolower($client->getProxyAlias()));
                $this->entityNames[$alias] = $client->getName();
            }
        }

        return $this->entityNames;
    }

    private function getHighRisksShare(array $levelsByType): float
    {
        $high = 0;
        $total = 0;
        foreach ($levelsByType as $levels) {
            $high += $levels['high'];
            $total += array_sum($levels);
        }

        return $total === 0 ? 0.0 : round($high * 100 / $total, 1);
    }

    private function toSeries(array $points, int $length): array
    {
        return array_map(static fn(int $index) => $points[$index] ?? null, range(0, $length - 1));
    }

    private function getEmptyRiskLevels(): array
    {
        return array_fill_keys(self::RISK_TYPES, ['low' => 0, 'medium' => 0, 'high' => 0]);
    }

    private function addRiskLevels(array $levels, array $risksByType): array
    {
        foreach (self::RISK_TYPES as $type) {
            foreach ($risksByType[$type] ?? [] as $level) {
                if (isset(self::RISK_LEVELS[$level['level']])) {
                    $levels[$type][self::RISK_LEVELS[$level['level']]] += (int)$level['value'];
                }
            }
        }

        return $levels;
    }

    /**
     * Taux de conformité d'une analyse (0 à 1), global et par catégorie.
     */
    private function getComplianceRatios(array $referentialStats): array
    {
        $targetByCategory = array_column($referentialStats['target'], null, 'label' . $this->language);

        $categories = [];
        $currentControls = [];
        $targetControls = [];
        foreach ($referentialStats['current'] as $category) {
            $label = $category['label' . $this->language];
            $target = $targetByCategory[$label] ?? ['value' => 0, 'controls' => []];
            $categories[$label] = ['current' => (float)$category['value'], 'target' => (float)$target['value']];
            array_push($currentControls, ...array_column($category['controls'], 'value'));
            array_push($targetControls, ...array_column($target['controls'], 'value'));
        }

        return [
            'current' => $this->getAverage($currentControls),
            'target' => $this->getAverage($targetControls),
            'categories' => $categories,
        ];
    }

    /**
     * Moyenne des analyses d'une entité, en pourcentages ; les catégories sont rapprochées par libellé.
     */
    private function getAverageCompliance(array $analyses): array
    {
        $categories = [];
        foreach ($analyses as $analysis) {
            foreach ($analysis['categories'] as $label => $values) {
                $categories[$label]['current'][] = $values['current'];
                $categories[$label]['target'][] = $values['target'];
            }
        }

        return [
            'analysesCount' => count($analyses),
            'date' => max(array_column($analyses, 'date')),
            'current' => $this->toPercentage($this->getAverage(array_column($analyses, 'current'))),
            'target' => $this->toPercentage($this->getAverage(array_column($analyses, 'target'))),
            'categories' => array_map(fn(string $label, array $values) => [
                'label' => $label,
                'current' => $this->toPercentage($this->getAverage($values['current'])),
                'target' => $this->toPercentage($this->getAverage($values['target'])),
            ], array_keys($categories), $categories),
        ];
    }

    private function getAverage(array $values): float
    {
        return $values === [] ? 0.0 : array_sum(array_map('floatval', $values)) / count($values);
    }

    private function toPercentage(float $ratio): float
    {
        return round($ratio * 100, 1);
    }

    private function sortByEntity(array $rows): array
    {
        usort($rows, static fn(array $a, array $b) => strcasecmp($a['entity'], $b['entity']));

        return $rows;
    }
}
