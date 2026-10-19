<?php declare(strict_types=1);

namespace Monarc\BackOffice\Entity;

use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * Entité (client) invitée à une campagne.
 *
 * @ORM\Table(name="campaign_participants", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_campaign_client", columns={"campaign_id", "client_id"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class CampaignParticipant
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const STATUS_INVITED = 'invited';

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Campaign", inversedBy="participants")
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", nullable=false)
     */
    protected Campaign $campaign;

    /**
     * @ORM\ManyToOne(targetEntity="Client")
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=false)
     */
    protected Client $client;

    /**
     * @ORM\Column(name="status", type="string", length=20)
     */
    protected string $status = self::STATUS_INVITED;

    public function __construct(Campaign $campaign, Client $client)
    {
        $this->campaign = $campaign;
        $this->client = $client;
        $campaign->addParticipant($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
