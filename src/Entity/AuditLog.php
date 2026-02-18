<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Audit log for access and actions (Issue #4 – access audit and logging requirements).
 * Records: who, what, when, result (patient data, file uploads, etc.).
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_audit_log_created_at')]
#[ORM\Index(columns: ['user_id'], name: 'idx_audit_log_user_id')]
#[ORM\Index(columns: ['resource_type', 'resource_id'], name: 'idx_audit_log_resource')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $action = '';

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $resourceType = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $resourceId = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $success = true;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $resultCode = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    public function setResourceType(?string $resourceType): static
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function setResourceId(?string $resourceId): static
    {
        $this->resourceId = $resourceId;
        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): static
    {
        $this->ip = $ip;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): static
    {
        $this->success = $success;
        return $this;
    }

    public function getResultCode(): ?string
    {
        return $this->resultCode;
    }

    public function setResultCode(?string $resultCode): static
    {
        $this->resultCode = $resultCode;
        return $this;
    }
}
