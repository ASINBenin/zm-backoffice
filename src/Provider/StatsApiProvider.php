<?php declare(strict_types=1);

namespace Monarc\BackOffice\Provider;

use Exception;
use Laminas\Http\Client;
use Laminas\Http\Request;
use Throwable;

/**
 * Lecture des statistiques de toutes les entités dans le service de statistiques, avec le jeton administrateur.
 */
class StatsApiProvider
{
    private const TIMEOUT = 30;

    private string $baseUrl;

    private string $adminToken;

    private string $appVersion;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['statsApi']['baseUrl'] ?? '', '/');
        $this->adminToken = $config['statsApi']['adminToken'] ?? '';
        $this->appVersion = $config['appVersion'] ?? '';
    }

    /**
     * Dernières statistiques d'un type pour chaque analyse.
     */
    public function getLastStats(string $type): array
    {
        return $this->fetchStats(['type' => $type, 'get_last' => true]);
    }

    /**
     * Statistiques d'un type collectées entre deux dates incluses (AAAA-MM-JJ), sans limite de nombre.
     */
    public function getStatsBetween(string $type, string $dateFrom, string $dateTo): array
    {
        return $this->fetchStats(['type' => $type, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'limit' => 0]);
    }

    private function fetchStats(array $query): array
    {
        if ($this->baseUrl === '' || $this->adminToken === '') {
            throw new Exception('Le service de statistiques n\'est pas configuré.', 412);
        }

        // Le service lit ses paramètres dans le corps JSON, y compris en GET
        $client = new Client($this->baseUrl . '/api/v1/stats/', ['timeout' => self::TIMEOUT]);
        $client->setMethod(Request::METHOD_GET)
            ->setHeaders([
                'X-API-KEY' => $this->adminToken,
                'User-Agent' => 'MONARC/' . $this->appVersion,
                'Content-Type' => 'application/json',
            ])
            ->setRawBody(json_encode($query));

        try {
            $response = $client->send();
        } catch (Throwable) {
            throw new Exception('Le service de statistiques est injoignable.', 412);
        }
        if (!$response->isOk()) {
            throw new Exception('Le service de statistiques a refusé la demande.', 412);
        }

        return json_decode($response->getBody(), true)['data'] ?? [];
    }
}
