<?php declare(strict_types=1);

namespace Monarc\BackOffice\Table;

use Doctrine\ORM\EntityManager;
use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Entity\ClientApiToken;
use Monarc\Core\Table\AbstractTable;

class ClientApiTokenTable extends AbstractTable
{
    public function __construct(EntityManager $entityManager, string $entityName = ClientApiToken::class)
    {
        parent::__construct($entityManager, $entityName);
    }

    public function findOneByClient(Client $client): ?ClientApiToken
    {
        return $this->getRepository()->findOneBy(['client' => $client]);
    }

    public function findOneByTokenHash(string $tokenHash): ?ClientApiToken
    {
        return $this->getRepository()->findOneBy(['tokenHash' => $tokenHash]);
    }
}
