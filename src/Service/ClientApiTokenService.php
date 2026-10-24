<?php declare(strict_types=1);

namespace Monarc\BackOffice\Service;

use Monarc\BackOffice\Entity\Client;
use Monarc\BackOffice\Entity\ClientApiToken;
use Monarc\BackOffice\Table\ClientApiTokenTable;

class ClientApiTokenService
{
    public function __construct(private ClientApiTokenTable $clientApiTokenTable)
    {
    }

    /**
     * Nouveau jeton de l'entité, renvoyé une seule fois : le BO n'en garde que l'empreinte.
     */
    public function issue(Client $client): string
    {
        $token = bin2hex(random_bytes(32));
        $apiToken = $this->clientApiTokenTable->findOneByClient($client) ?? new ClientApiToken($client);
        $this->clientApiTokenTable->save($apiToken->setTokenHash(hash('sha256', $token)));

        return $token;
    }

    /**
     * Entité à qui appartient le jeton, ou null si le jeton est inconnu.
     */
    public function findClientByToken(string $token): ?Client
    {
        $apiToken = $this->clientApiTokenTable->findOneByTokenHash(hash('sha256', $token));
        if ($apiToken === null) {
            return null;
        }
        $this->clientApiTokenTable->save($apiToken->markUsed());

        return $apiToken->getClient();
    }
}
