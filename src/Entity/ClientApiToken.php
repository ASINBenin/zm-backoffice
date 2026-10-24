<?php declare(strict_types=1);

namespace Monarc\BackOffice\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton d'une entité auprès de l'API du BO.
 *
 * @ORM\Table(name="client_api_tokens")
 * @ORM\Entity
 */
class ClientApiToken
{
    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @ORM\OneToOne(targetEntity="Client")
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=false)
     */
    protected Client $client;

    /**
     * @ORM\Column(name="token_hash", type="string", length=64, options={"fixed": true})
     */
    protected string $tokenHash;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected DateTime $createdAt;

    /**
     * @ORM\Column(name="last_used_at", type="datetime", nullable=true)
     */
    protected ?DateTime $lastUsedAt = null;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Remplace le jeton : l'ancien n'est plus accepté.
     */
    public function setTokenHash(string $tokenHash): self
    {
        $this->tokenHash = $tokenHash;
        $this->createdAt = new DateTime();
        $this->lastUsedAt = null;

        return $this;
    }

    public function markUsed(): self
    {
        $this->lastUsedAt = new DateTime();

        return $this;
    }
}
