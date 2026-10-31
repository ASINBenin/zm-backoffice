<?php declare(strict_types=1);

namespace Monarc\BackOffice\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Courriel planifié d'une campagne déjà envoyé.
 *
 * @ORM\Table(name="campaign_notifications", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_campaign_notification", columns={"campaign_id", "type"})
 * })
 * @ORM\Entity
 */
class CampaignNotification
{
    public const TYPE_INVITATIONS_EXPIRED = 'invitations_expired';

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Campaign")
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", nullable=false, onDelete="CASCADE")
     */
    protected Campaign $campaign;

    /**
     * @ORM\Column(name="type", type="string", length=30)
     */
    protected string $type;

    /**
     * @ORM\Column(name="sent_at", type="datetime")
     */
    protected DateTime $sentAt;

    public function __construct(Campaign $campaign, string $type)
    {
        $this->campaign = $campaign;
        $this->type = $type;
        $this->sentAt = new DateTime();
    }
}
