<?php

class StatusPage
{
    public function __construct(private Database $db) {}

    public function getData(): array
    {
        $services = $this->db->getServices();
        $result   = [];

        foreach ($services as $service) {
            $latest   = $this->db->getLatestCheck($service->id);
            $daily    = $this->fillDays($this->db->getDailyUptime($service->id, 90));
            $uptime30 = $this->db->getUptimePercent($service->id, 30);
            $uptime90 = $this->db->getUptimePercent($service->id, 90);

            $result[] = [
                'service'   => $service,
                'latest'    => $latest,
                'is_up'     => $latest !== null && $latest->status_code >= 200 && $latest->status_code < 400,
                'days'      => $daily,
                'uptime30'  => $uptime30,
                'uptime90'  => $uptime90,
            ];
        }

        return $result;
    }

    public function overallStatus(array $data): string
    {
        if (empty($data)) return 'operational';

        $down = array_filter($data, fn($s) => !$s['is_up']);

        return match (true) {
            count($down) === 0            => 'operational',
            count($down) < count($data)   => 'partial',
            default                       => 'major',
        };
    }

    private function fillDays(array $rows, int $days = 90): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->date] = $row;
        }

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));

            if (isset($indexed[$date])) {
                $pct    = (float) $indexed[$date]->uptime_pct;
                $status = $pct >= 99 ? 'up' : ($pct > 0 ? 'partial' : 'down');
            } else {
                $pct    = null;
                $status = 'no-data';
            }

            $result[] = [
                'date' => $date,
                'status' => $status,
                'uptime_pct' => $pct
            ];
        }

        return $result;
    }
}
