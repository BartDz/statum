const REFRESH_INTERVAL = 60_000;

const BANNER_CLASS = {
    operational: 'banner--operational',
    partial:     'banner--partial',
    major:       'banner--major',
};

const BANNER_TEXT = {
    operational: 'All systems operational',
    partial:     'Partial outage',
    major:       'Major outage',
};

const BADGE_CLASS = {
    up:      'badge--up',
    down:    'badge--down',
    unknown: 'badge--unknown',
};

const charts = {};

function initSparklines() {
    document.querySelectorAll('[data-service-id]').forEach(canvas => {
        const id      = canvas.dataset.serviceId;
        const raw     = canvas.dataset.latency ?? '[]';
        const points  = JSON.parse(raw);

        charts[id] = new Chart(canvas, {
            type: 'line',
            data: {
                labels:   points.map((_, i) => i),
                datasets: [{
                    data:            points,
                    borderColor:     '#3ecf8e',
                    borderWidth:     1.5,
                    pointRadius:     0,
                    tension:         0.3,
                    fill:            false,
                }],
            },
            options: {
                animation:  false,
                plugins:    { legend: { display: false }, tooltip: { enabled: false } },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: true },
                },
            },
        });
    });
}

function updateBanner(overall) {
    const banner = document.querySelector('.banner');
    if (!banner) return;

    Object.values(BANNER_CLASS).forEach(c => banner.classList.remove(c));
    banner.classList.add(BANNER_CLASS[overall] ?? BANNER_CLASS.major);

    const textNode = banner.querySelector('.banner__dot').nextSibling;
    if (textNode) textNode.textContent = ' ' + (BANNER_TEXT[overall] ?? 'Unknown');
}

function updateService(svc) {
    const card = document.querySelector(`[data-id="${svc.id}"]`);
    if (!card) return;

    const badge = card.querySelector('.badge');
    if (badge) {
        const key = svc.is_up ? 'up' : (svc.status === null ? 'unknown' : 'down');
        Object.values(BADGE_CLASS).forEach(c => badge.classList.remove(c));
        badge.classList.add(BADGE_CLASS[key]);
        badge.textContent = svc.is_up ? 'Operational' : (svc.status === null ? 'No data' : 'Down');
    }

    const latencyEl = card.querySelector('[data-role="latency"]');
    if (latencyEl) {
        latencyEl.textContent = svc.latency !== null ? svc.latency + ' ms' : '—';
    }

    const u30 = card.querySelector('[data-role="uptime30"]');
    if (u30) u30.textContent = svc.uptime30.toFixed(1) + '%';

    const u90 = card.querySelector('[data-role="uptime90"]');
    if (u90) u90.textContent = svc.uptime90.toFixed(1) + '%';
}

async function refresh() {
    try {
        const res  = await fetch('/api/status');
        if (!res.ok) return;
        const data = await res.json();

        updateBanner(data.overall);
        data.services.forEach(updateService);

        const ts = document.getElementById('last-updated');
        if (ts) ts.textContent = new Date(data.timestamp).toLocaleTimeString();
    } catch (_) {
        // network error — silently skip, retry next interval
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof Chart !== 'undefined') {
        initSparklines();
    }
    setInterval(refresh, REFRESH_INTERVAL);
});
