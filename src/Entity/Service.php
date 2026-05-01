<?php

class Service
{
    public function __construct(
        private readonly int    $id,
        private readonly string $name,
        private readonly string $url,
        private readonly int    $expectedStatus,
        private readonly string $createdAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: $row['name'],
            url: $row['url'],
            expectedStatus: (int) ($row['expected_status'] ?? 200),
            createdAt: $row['created_at'] ?? '',
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getExpectedStatus(): int
    {
        return $this->expectedStatus;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}
