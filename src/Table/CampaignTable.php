<?php declare(strict_types=1);

namespace Monarc\BackOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\BackOffice\Entity\Campaign;
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
}
