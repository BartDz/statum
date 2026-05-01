<?php

class StatusPage
{
    public function __construct(private Database $db) {}

    public function getData(): array
    {
        $services = $this->db->getServices();
        $result   = [];

        foreach ($services as $service) {
            $latest   = $this->db->getLatestCheck($service->getId());
            $daily    = $this->fillDays($this->db->getDailyUptime($service->getId(), 90));
            $uptime30 = $this->db->getUptimePercent($service->getId(), 30);
            $uptime90 = $this->db->getUptimePercent($service->getId(), 90);

            $result[] = [
                'service'  => $service,
                'latest'   => $latest,
                'is_up'    => $latest !== null && $latest->isUp(),
                'days'     => $daily,
                'uptime30' => $uptime30,
                'uptime90' => $uptime90,
            ];
        }

        return $result;
    }

    public function overallStatus(array $data): string
    {
        if (empty($data)) return 'operational';

        $down = array_filter($data, fn($s) => !$s['is_up']);

        return match (true) {
            count($down) === 0          => 'operational',
            count($down) < count($data) => 'partial',
            default                     => 'major',
        };
    }

    /** @param DaySummary[] $rows */
    private function fillDays(array $rows, int $days = 90): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->getDate()] = $row;
        }

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));

            if (isset($indexed[$date])) {
                $pct    = $indexed[$date]->getUptimePct();
                $status = $pct >= 99 ? 'up' : ($pct > 0 ? 'partial' : 'down');
            } else {
                $pct    = null;
                $status = 'no-data';
            }

            $result[] = ['date' => $date, 'status' => $status, 'uptime_pct' => $pct];
        }

        return $result;
    }
}
