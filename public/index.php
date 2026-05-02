<?php

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/StatusPage.php';

Env::load(__DIR__ . '/../.env');

$dbError  = null;
$siteName = getenv('SITE_NAME') ?: 'Status Page';

try {
    $db = Database::fromEnv();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if ($dbError): ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
<header>
    <div class="header__title">
        <h1><?= htmlspecialchars($siteName) ?></h1>
        <span class="db-badge db-badge--error" title="<?= htmlspecialchars($dbError) ?>">Supabase</span>
    </div>
</header>
<main>
    <div class="banner banner--outage"><span class="banner__dot"></span>Database unavailable</div>
</main>
</body>
</html>
<?php
    exit;
endif;

$page    = new StatusPage($db);
$data    = $page->getData();
$overall = $page->overallStatus($data);

$bannerText = match ($overall) {
    'operational' => 'All systems operational',
    'partial'     => 'Partial outage',
    default       => 'Major outage',
};

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"
            onerror="this.onerror=null;this.src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js'"></script>
</head>
<body>

<header>
    <div class="header__title">
        <h1><?= htmlspecialchars($siteName) ?></h1>
        <span class="db-badge db-badge--supabase">Supabase</span>
    </div>
</header>

<main>
    <div class="banner banner--<?= $overall ?>">
        <span class="banner__dot"></span>
        <?= htmlspecialchars($bannerText) ?>
    </div>

    <?php if (empty($data)): ?>
        <p class="empty">No services configured yet.</p>
    <?php else: ?>
        <div class="services">
            <?php foreach ($data as $row): ?>
                <?php
                    $service      = $row['service'];
                    $latest       = $row['latest'];
                    $isUp         = $row['is_up'];
                    $badge        = $latest === null ? 'unknown' : ($isUp ? 'up' : 'down');
                    $label        = $latest === null ? 'No data' : ($isUp ? 'Operational' : 'Down');
                    $latency      = $latest ? $latest->getLatencyMs() . ' ms' : '—';
                    $recentChecks = $db->getRecentChecks($service->getId(), 24);
                    $latencyData  = json_encode(array_map(fn($c) => $c->getLatencyMs(), $recentChecks));
                ?>
                <div class="service" data-id="<?= $service->getId() ?>">
                    <div class="service__header">
                        <span class="service__name"><?= htmlspecialchars($service->getName()) ?></span>
                        <span class="badge badge--<?= $badge ?>"><?= $label ?></span>
                    </div>

                    <div class="service__meta">
                        <span>Response: <span data-role="latency"><?= $latency ?></span></span>
                        <span>30d uptime: <span data-role="uptime30"><?= number_format($row['uptime30'], 1) ?></span>%</span>
                        <span>90d uptime: <span data-role="uptime90"><?= number_format($row['uptime90'], 1) ?></span>%</span>
                    </div>

                    <?php if (!empty($recentChecks)): ?>
                    <div class="sparkline-wrap">
                        <canvas
                            data-service-id="<?= $service->getId() ?>"
                            data-latency="<?= htmlspecialchars($latencyData) ?>"
                            height="40"
                        ></canvas>
                    </div>
                    <?php endif ?>

                    <div class="uptime-bars">
                        <?php foreach ($row['days'] as $day): ?>
                            <span
                                class="uptime-bar"
                                data-status="<?= $day['status'] ?>"
                                data-date="<?= $day['date'] ?>"
                                title="<?= $day['date'] ?>: <?= $day['uptime_pct'] !== null ? $day['uptime_pct'] . '%' : 'no data' ?>"
                            ></span>
                        <?php endforeach ?>
                    </div>

                    <div class="service__footer">
                        <span class="uptime-label">90 days ago</span>
                        <span class="uptime-label">Today</span>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>

    <section class="incidents">
        <?php $incidents = $db->getOpenIncidents(); ?>
        <?php if (!empty($incidents)): ?>
            <h2>Active incidents</h2>
            <?php foreach ($incidents as $inc): ?>
                <div class="incident">
                    <strong><?= htmlspecialchars($inc->getTitle()) ?></strong>
                    <?php if ($inc->getDescription()): ?>
                        <p><?= htmlspecialchars($inc->getDescription()) ?></p>
                    <?php endif ?>
                    <small><?= $inc->getStartTime() ?><?= $inc->getServiceName() ? ' · ' . htmlspecialchars($inc->getServiceName()) : '' ?></small>
                </div>
            <?php endforeach ?>
        <?php endif ?>
    </section>
</main>

<footer>
    <small>Updated <span id="last-updated"><?= date('H:i') ?></span> · <a href="/admin.php">Admin</a></small>
</footer>

<script src="/js/auto-refresh.js"></script>
</body>
</html>
