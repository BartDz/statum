<?php

class Check
{
    public function __construct(
        private readonly int    $id,
        private readonly int    $serviceId,
        private readonly int    $statusCode,
        private readonly int    $latencyMs,
        private readonly string $timestamp,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            serviceId: (int) $row['service_id'],
            statusCode: (int) $row['status_code'],
            latencyMs: (int) $row['latency_ms'],
            timestamp: $row['timestamp'],
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getServiceId(): int
    {
        return $this->serviceId;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getLatencyMs(): int
    {
        return $this->latencyMs;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function isUp(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 400;
    }
}
