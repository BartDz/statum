<?php

class Incident
{
    public function __construct(
        private readonly int     $id,
        private readonly string  $title,
        private readonly ?string $description,
        private readonly ?int    $serviceId,
        private readonly string  $startTime,
        private readonly ?string $endTime,
        private readonly string  $status,
        private readonly ?string $serviceName = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            title: $row['title'],
            description: $row['description'] ?? null,
            serviceId: isset($row['service_id']) && $row['service_id'] !== null
                ? (int) $row['service_id']
                : null,
            startTime: $row['start_time'],
            endTime: $row['end_time'] ?? null,
            status: $row['status'],
            serviceName: $row['service_name'] ?? null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getServiceId(): ?int
    {
        return $this->serviceId;
    }

    public function getStartTime(): string
    {
        return $this->startTime;
    }

    public function getEndTime(): ?string
    {
        return $this->endTime;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function isOpen(): bool
    {
        return $this->endTime === null;
    }
}
