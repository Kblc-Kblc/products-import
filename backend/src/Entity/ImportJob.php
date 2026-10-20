<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
class ImportJob
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_QUEUED;

    #[ORM\Column]
    private int $total = 0;

    #[ORM\Column]
    private int $processed = 0;

    #[ORM\Column]
    private int $created = 0;

    #[ORM\Column]
    private int $updated = 0;

    /** @var list<array{row: int, message: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $errors = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $filename,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function start(int $total): void
    {
        $this->status = self::STATUS_PROCESSING;
        $this->total = $total;
    }

    public function rowDone(?bool $created): void
    {
        ++$this->processed;
        if ($created === true) {
            ++$this->created;
        } elseif ($created === false) {
            ++$this->updated;
        }
    }

    public function addError(int $row, string $message): void
    {
        $this->errors[] = ['row' => $row, 'message' => $message];
    }

    public function finish(): void
    {
        $this->status = self::STATUS_DONE;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function fail(string $message): void
    {
        $this->addError(0, $message);
        $this->status = self::STATUS_FAILED;
        $this->finishedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @return array{id: ?int, status: string, filename: string, total: int, processed: int, created: int, updated: int, errors: list<array{row: int, message: string}>, createdAt: string, finishedAt: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'filename' => $this->filename,
            'total' => $this->total,
            'processed' => $this->processed,
            'created' => $this->created,
            'updated' => $this->updated,
            'errors' => $this->errors,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'finishedAt' => $this->finishedAt?->format(DATE_ATOM),
        ];
    }
}
