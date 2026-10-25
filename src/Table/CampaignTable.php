<?php declare(strict_types=1);

namespace Monarc\BackOffice\Table;

use DateTime;
use Doctrine\ORM\EntityManager;
use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\Entity\Client;
use Monarc\Core\Table\AbstractTable;

class CampaignTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = Campaign::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findOneByModelId(int $modelId): ?Campaign
    {
        return $this->getRepository()->findOneBy(['modelId' => $modelId]);
    }

    /**
     * @return int[]
     */
    public function findUsedModelIds(): array
    {
        return array_map('intval', $this->getRepository()->createQueryBuilder('c')
            ->select('c.modelId')
            ->getQuery()
            ->getSingleColumnResult());
    }

    /**
     * Campagnes publiées ou clôturées où l'entité est invitée, celles modifiées depuis $since si renseigné.
     *
     * @return Campaign[]
     */
    public function findVisibleToClient(Client $client, ?DateTime $since): array
    {
        $queryBuilder = $this->getRepository()->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->where('p.client = :client')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', [Campaign::STATUS_PUBLISHED, Campaign::STATUS_CLOSED])
            ->orderBy('c.startDate', 'DESC')
            ->addOrderBy('c.id', 'DESC');
        if ($since !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX('c.updatedAt >= :since', 'p.updatedAt >= :since'))
                ->setParameter('since', $since);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
