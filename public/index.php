<?php

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/StatusPage.php';

$db   = new Database(__DIR__ . '/../db/status.sqlite');
$page = new StatusPage($db);
$data = $page->getData();
$overall = $page->overallStatus($data);

$bannerText = match ($overall) {
    'operational' => 'All systems operational',
    'partial'     => 'Partial outage',
    default       => 'Major outage',
};

$siteName = getenv('SITE_NAME') ?: 'Status Page';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<header>
    <h1><?= htmlspecialchars($siteName) ?></h1>
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
                    $service = $row['service'];
                    $latest  = $row['latest'];
                    $isUp    = $row['is_up'];
                    $badge   = $latest === null ? 'unknown' : ($isUp ? 'up' : 'down');
                    $label   = $latest === null ? 'No data' : ($isUp ? 'Operational' : 'Down');
                    $latency = $latest ? $latest->latency_ms . ' ms' : '—';
                ?>
                <div class="service">
                    <div class="service__header">
                        <span class="service__name"><?= htmlspecialchars($service->name) ?></span>
                        <span class="badge badge--<?= $badge ?>"><?= $label ?></span>
                    </div>

                    <div class="service__meta">
                        <span>Response: <?= $latency ?></span>
                        <span>30d uptime: <?= number_format($row['uptime30'], 1) ?>%</span>
                        <span>90d uptime: <?= number_format($row['uptime90'], 1) ?>%</span>
                    </div>

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
                    <strong><?= htmlspecialchars($inc->title) ?></strong>
                    <?php if ($inc->description): ?>
                        <p><?= htmlspecialchars($inc->description) ?></p>
                    <?php endif ?>
                    <small><?= $inc->start_time ?><?= $inc->service_name ? ' · ' . htmlspecialchars($inc->service_name) : '' ?></small>
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
