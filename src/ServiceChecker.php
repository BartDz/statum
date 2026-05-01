<?php

class ServiceChecker
{
    public function check(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'statum/1.0 (+https://github.com/BartDz/statum)',
        ]);

        $start   = microtime(true);
        curl_exec($ch);
        $latency = (int) round((microtime(true) - $start) * 1000);

        $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno   = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            $status  = 0;
            $latency = 10000;
        }

        return ['status_code' => $status, 'latency_ms' => $latency];
    }
}
