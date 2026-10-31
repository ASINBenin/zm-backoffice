<?php declare(strict_types=1);

namespace Monarc\BackOffice\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Monarc\Core\Entity\Traits\CreateEntityTrait;

/**
 * Version du rapport d'une analyse, soumise au BO par une entité, dans une campagne ou hors campagne.
 *
 * @ORM\Table(name="entity_submissions", uniqueConstraints={
 *     @ORM\UniqueConstraint(name="uniq_submission_version", columns={"client_id", "campaign_id", "source_anr_id", "version"})
 * })
 * @ORM\Entity
 * @ORM\HasLifecycleCallbacks()
 */
class EntitySubmission
{
    use CreateEntityTrait;

    public const APPRECIATION_LEVELS = [
        'insufficient',
        'to_improve',
        'satisfactory',
        'very_satisfactory',
    ];

    /**
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    protected ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Client")
     * @ORM\JoinColumn(name="client_id", referencedColumnName="id", nullable=false)
     */
    protected Client $client;

    /**
     * @ORM\ManyToOne(targetEntity="Campaign")
     * @ORM\JoinColumn(name="campaign_id", referencedColumnName="id", nullable=true)
     */
    protected ?Campaign $campaign;

    /**
     * @ORM\Column(name="source_anr_id", type="integer")
     */
    protected int $sourceAnrId;

    /**
     * @ORM\Column(name="analysis_label", type="string", length=255)
     */
    protected string $analysisLabel;

    /**
     * @ORM\Column(name="version", type="integer")
     */
    protected int $version;

    /**
     * Nom du fichier dans le dossier de l'entité : aléatoire, jamais celui reçu.
     *
     * @ORM\Column(name="stored_name", type="string", length=64)
     */
    protected string $storedName;

    /**
     * @ORM\Column(name="file_size", type="integer")
     */
    protected int $fileSize;

    /**
     * @ORM\Column(name="sha256", type="string", length=64, options={"fixed": true})
     */
    protected string $sha256;

    /**
     * @ORM\Column(name="is_signed", type="boolean")
     */
    protected bool $isSigned;

    /**
     * @ORM\Column(name="appreciation_level", type="string", length=20, nullable=true)
     */
    protected ?string $appreciationLevel = null;

    /**
     * @ORM\Column(name="appreciation_comment", type="text", nullable=true)
     */
    protected ?string $appreciationComment = null;

    /**
     * @ORM\Column(name="appreciated_by", type="string", length=255, nullable=true)
     */
    protected ?string $appreciatedBy = null;

    /**
     * @ORM\Column(name="appreciated_at", type="datetime", nullable=true)
     */
    protected ?DateTime $appreciatedAt = null;

    public function __construct(
        Client $client,
        ?Campaign $campaign,
        int $sourceAnrId,
        string $analysisLabel,
        int $version,
        string $storedName,
        int $fileSize,
        string $sha256,
        bool $isSigned,
        string $creator
    ) {
        $this->client = $client;
        $this->campaign = $campaign;
        $this->sourceAnrId = $sourceAnrId;
        $this->analysisLabel = $analysisLabel;
        $this->version = $version;
        $this->storedName = $storedName;
        $this->fileSize = $fileSize;
        $this->sha256 = $sha256;
        $this->isSigned = $isSigned;
        $this->creator = $creator;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getCampaign(): ?Campaign
    {
        return $this->campaign;
    }

    public function getSourceAnrId(): int
    {
        return $this->sourceAnrId;
    }

    public function getAnalysisLabel(): string
    {
        return $this->analysisLabel;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function getSha256(): string
    {
        return $this->sha256;
    }

    public function isSigned(): bool
    {
        return $this->isSigned;
    }

    public function getAppreciationLevel(): ?string
    {
        return $this->appreciationLevel;
    }

    public function getAppreciationComment(): ?string
    {
        return $this->appreciationComment;
    }

    public function getAppreciatedBy(): ?string
    {
        return $this->appreciatedBy;
    }

    public function getAppreciatedAt(): ?DateTime
    {
        return $this->appreciatedAt;
    }

    /**
     * Donne ou corrige l'appréciation de l'ASIN sur cette version.
     */
    public function appreciate(string $level, ?string $comment, string $appreciatedBy): self
    {
        $this->appreciationLevel = $level;
        $this->appreciationComment = $comment === '' ? null : $comment;
        $this->appreciatedBy = $appreciatedBy;
        $this->appreciatedAt = new DateTime();

        return $this;
    }
}
