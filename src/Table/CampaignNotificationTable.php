<?php declare(strict_types=1);

namespace Monarc\BackOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\BackOffice\Entity\Campaign;
use Monarc\BackOffice\Entity\CampaignNotification;
use Monarc\Core\Table\AbstractTable;

class CampaignNotificationTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = CampaignNotification::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function isSent(Campaign $campaign, string $type): bool
    {
        return $this->getRepository()->findOneBy(['campaign' => $campaign, 'type' => $type]) !== null;
    }

    public function removeSent(Campaign $campaign, string $type): void
    {
        $this->getRepository()->createQueryBuilder('n')
            ->delete()
            ->where('n.campaign = :campaign')
            ->andWhere('n.type = :type')
            ->setParameter('campaign', $campaign)
            ->setParameter('type', $type)
            ->getQuery()
            ->execute();
    }
}
