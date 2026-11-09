<?php declare(strict_types=1);

namespace Monarc\BackOffice\Table;

use DateTime;
use DateTimeImmutable;
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

    /**
     * Campagne publiée ou clôturée qui utilise le modèle.
     */
    public function findOneNotDraftByModelId(int $modelId): ?Campaign
    {
        return $this->getRepository()->createQueryBuilder('c')
            ->where('c.modelId = :modelId')
            ->andWhere('c.status <> :draft')
            ->setParameter('modelId', $modelId)
            ->setParameter('draft', Campaign::STATUS_DRAFT)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Campagnes publiées dont la date limite de réponse est dépassée.
     *
     * @return Campaign[]
     */
    public function findPublishedWithDeadlineBefore(DateTimeImmutable $today): array
    {
        return $this->getRepository()->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.responseDeadline < :today')
            ->setParameter('status', Campaign::STATUS_PUBLISHED)
            ->setParameter('today', $today, 'date_immutable')
            ->getQuery()
            ->getResult();
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
            // Une entité invitée après la publication n'a qu'une date de création
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                'c.updatedAt >= :since',
                'p.createdAt >= :since',
                'p.updatedAt >= :since'
            ))
                ->setParameter('since', $since);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
