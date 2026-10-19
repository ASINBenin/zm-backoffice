<?php declare(strict_types=1);

namespace Monarc\BackOffice\Entity;

use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;
use Monarc\Core\Entity\Traits\UpdateEntityTrait;

/**
 * Campagne d'analyse de risques lancée par l'ASIN auprès des entités.
 *
 * @ORM\Table(name="campaigns")
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class Campaign
{
    use CreateEntityTrait;
    use UpdateEntityTrait;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CLOSED = 'closed';

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @ORM\Column(name="label", type="string", length=255)
     */
    protected string $label;

    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected ?string $description = null;

    /**
     * Modèle de la base commune (pas de relation Doctrine : autre base de données).
     *
     * @ORM\Column(name="model_id", type="integer")
     */
    protected int $modelId;

    /**
     * @ORM\Column(name="start_date", type="date_immutable")
     */
    protected DateTimeImmutable $startDate;

    /**
     * @ORM\Column(name="response_deadline", type="date_immutable")
     */
    protected DateTimeImmutable $responseDeadline;

    /**
     * @ORM\Column(name="end_date", type="date_immutable")
     */
    protected DateTimeImmutable $endDate;

    /**
     * @ORM\Column(name="is_signature_required", type="boolean")
     */
    protected bool $isSignatureRequired = false;

    /**
     * @ORM\Column(name="status", type="string", length=20)
     */
    protected string $status = self::STATUS_DRAFT;

    /**
     * @ORM\Column(name="published_at", type="datetime", nullable=true)
     */
    protected ?DateTime $publishedAt = null;

    /**
     * @ORM\Column(name="closed_at", type="datetime", nullable=true)
     */
    protected ?DateTime $closedAt = null;

    /**
     * @var Collection|CampaignParticipant[]
     *
     * @ORM\OneToMany(targetEntity="CampaignParticipant", mappedBy="campaign", cascade={"persist"}, orphanRemoval=true)
     */
    protected $participants;

    public function __construct()
    {
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getModelId(): int
    {
        return $this->modelId;
    }

    public function setModelId(int $modelId): self
    {
        $this->modelId = $modelId;

        return $this;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getResponseDeadline(): DateTimeImmutable
    {
        return $this->responseDeadline;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setDates(
        DateTimeImmutable $startDate,
        DateTimeImmutable $responseDeadline,
        DateTimeImmutable $endDate
    ): self {
        $this->startDate = $startDate;
        $this->responseDeadline = $responseDeadline;
        $this->endDate = $endDate;

        return $this;
    }

    public function isSignatureRequired(): bool
    {
        return $this->isSignatureRequired;
    }

    public function setIsSignatureRequired(bool $isSignatureRequired): self
    {
        $this->isSignatureRequired = $isSignatureRequired;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function publish(): self
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->publishedAt = new DateTime();

        return $this;
    }

    public function close(): self
    {
        $this->status = self::STATUS_CLOSED;
        $this->closedAt = new DateTime();

        return $this;
    }

    public function getPublishedAt(): ?DateTime
    {
        return $this->publishedAt;
    }

    public function getClosedAt(): ?DateTime
    {
        return $this->closedAt;
    }

    /**
     * @return Collection|CampaignParticipant[]
     */
    public function getParticipants()
    {
        return $this->participants;
    }

    public function addParticipant(CampaignParticipant $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
        }

        return $this;
    }

    public function removeParticipant(CampaignParticipant $participant): self
    {
        $this->participants->removeElement($participant);

        return $this;
    }
}
