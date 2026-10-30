<?php declare(strict_types=1);

namespace Monarc\BackOffice\Table;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Entity\EntitySubmission;
use Monarc\Core\Table\AbstractTable;

class EntitySubmissionTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = EntitySubmission::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    /**
     * Dernier numéro de version envoyé pour cette analyse de l'entité, dans la campagne ou hors campagne ; 0 si aucun.
     */
    public function getLastVersion(Client $client, ?Campaign $campaign, int $sourceAnrId): int
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('s')
            ->select('MAX(s.version)')
            ->where('s.client = :client')
            ->andWhere('s.sourceAnrId = :sourceAnrId')
            ->setParameter('client', $client)
            ->setParameter('sourceAnrId', $sourceAnrId);
        $this->filterByCampaign($queryBuilder, $campaign);

        return (int)$queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * Soumissions les plus récentes d'abord, filtrées par entité et/ou par campagne.
     *
     * @return EntitySubmission[]
     */
    public function findFiltered(?int $clientId, ?int $campaignId): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC');
        if ($clientId !== null) {
            $queryBuilder->andWhere('s.client = :clientId')->setParameter('clientId', $clientId);
        }
        if ($campaignId !== null) {
            $queryBuilder->andWhere('s.campaign = :campaignId')->setParameter('campaignId', $campaignId);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function filterByCampaign(QueryBuilder $queryBuilder, ?Campaign $campaign): void
    {
        if ($campaign === null) {
            $queryBuilder->andWhere('s.campaign IS NULL');

            return;
        }
        $queryBuilder->andWhere('s.campaign = :campaign')->setParameter('campaign', $campaign);
    }
}
