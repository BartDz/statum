<?php

class DaySummary
{
    public function __construct(
        private readonly string $date,
        private readonly int    $total,
        private readonly int    $upCount,
        private readonly float  $uptimePct,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            date: $row['date'],
            total: (int) $row['total'],
            upCount: (int) $row['up_count'],
            uptimePct: (float) $row['uptime_pct'],
        );
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getUpCount(): int
    {
        return $this->upCount;
    }

    public function getUptimePct(): float
    {
        return $this->uptimePct;
    }
}
