<?php

session_start();

require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Constants/ActionType.php';

Env::load(__DIR__ . '/../.env');

$adminPassword = getenv('ADMIN_PASSWORD');
if (!$adminPassword) {
    http_response_code(500);
    exit('ADMIN_PASSWORD is not set. Configure it in .env before using the admin panel.');
}

$dbError            = null;
$supabaseConfigured = (bool) getenv('SUPABASE_URL') && (bool) getenv('SUPABASE_KEY');

try {
    $db = Database::fromEnv();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    $db      = Database::sqlite();
}

// --- Auth ---

if (isset($_POST['action']) && $_POST['action'] === ActionType::LOGIN) {
    if (hash_equals($adminPassword, $_POST['password'] ?? '')) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
    } else {
        $error = 'Invalid password.';
    }
}

if (isset($_POST['action']) && $_POST['action'] === ActionType::LOGOUT) {
    session_destroy();
    header('Location: /');
    exit;
}

$isAuth = !empty($_SESSION['admin']);

// --- Actions (authenticated) ---

if ($isAuth && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === ActionType::ADD_SERVICE) {
        $name   = trim($_POST['name']   ?? '');
        $url    = trim($_POST['url']    ?? '');
        $status = (int) ($_POST['expected_status'] ?? 200);

        if ($name && $url && filter_var($url, FILTER_VALIDATE_URL)) {
            $db->addService($name, $url, $status);
            header('Location: /admin.php?ok=service');
            exit;
        } else {
            $error = 'Invalid name or URL.';
        }
    }

    if ($action === ActionType::ADD_INCIDENT) {
        $title     = trim($_POST['title']       ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $serviceId = $_POST['service_id'] ? (int) $_POST['service_id'] : null;

        if ($title) {
            $db->addIncident($title, $desc, $serviceId);
            header('Location: /admin.php?ok=incident');
            exit;
        } else {
            $error = 'Incident title is required.';
        }
    }

    if ($action === ActionType::DELETE_SERVICE) {
        $id = (int) ($_POST['service_id'] ?? 0);
        if ($id) {
            $db->deleteService($id);
            header('Location: /admin.php?ok=deleted');
            exit;
        }
    }

    if ($action === ActionType::RESOLVE_INCIDENT) {
        $id = (int) ($_POST['incident_id'] ?? 0);
        if ($id) {
            $db->resolveIncident($id);
            header('Location: /admin.php?ok=resolved');
            exit;
        }
    }
}

$services = $db->getServices();
$incidents = $db->getAllIncidents(30);
$siteName = getenv('SITE_NAME') ?: 'Status Page';

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body>

<header>
    <div class="header__title">
        <h1><?= htmlspecialchars($siteName) ?> — Admin</h1>
        <?php if ($supabaseConfigured): ?>
            <?php if ($dbError): ?>
                <span class="db-badge db-badge--error" title="<?= htmlspecialchars($dbError) ?>">Supabase</span>
            <?php else: ?>
                <span class="db-badge db-badge--supabase">Supabase</span>
            <?php endif ?>
        <?php else: ?>
            <span class="db-badge db-badge--sqlite">SQLite</span>
        <?php endif ?>
    </div>
    <?php if ($isAuth): ?>
        <form method="post" style="display:inline">
            <input type="hidden" name="action" value="<?= ActionType::LOGOUT ?>">
            <button type="submit" class="btn btn--small">Logout</button>
        </form>
    <?php endif ?>
</header>

<main>

<?php if (!$isAuth): ?>

    <div class="card">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif ?>
        <form method="post">
            <input type="hidden" name="action" value="<?= ActionType::LOGIN ?>">
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" autofocus>
            </div>
            <button type="submit" class="btn" style="margin-top:14px">Login</button>
        </form>
    </div>

<?php else: ?>

    <?php if (isset($_GET['ok'])): ?>
        <p class="notice">Saved successfully.</p>
    <?php endif ?>
    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif ?>

    <!-- Services -->
    <div class="card">
        <h2>Services</h2>
        <table class="table">
            <thead><tr><th>Name</th><th>URL</th><th>Expected</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><?= htmlspecialchars($s->getName()) ?></td>
                        <td><?= htmlspecialchars($s->getUrl()) ?></td>
                        <td><?= $s->getExpectedStatus() ?></td>
                        <td>
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($s->getName()), ENT_QUOTES) ?> and all its data?')">
                                <input type="hidden" name="action"     value="<?= ActionType::DELETE_SERVICE ?>">
                                <input type="hidden" name="service_id" value="<?= $s->getId() ?>">
                                <button type="submit" class="btn btn--small btn--danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <h3>Add service</h3>
        <form method="post">
            <input type="hidden" name="action" value="<?= ActionType::ADD_SERVICE ?>">
            <div class="fields">
                <div class="field">
                    <label>Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="field">
                    <label>URL</label>
                    <input type="url" name="url" required>
                </div>
                <div class="field">
                    <label>Expected status</label>
                    <input type="number" name="expected_status" value="200" min="100" max="599">
                </div>
            </div>
            <button type="submit" class="btn">Add service</button>
        </form>
    </div>

    <!-- Incidents -->
    <div class="card">
        <h2>Incidents</h2>
        <table class="table">
            <thead><tr><th>Title</th><th>Service</th><th>Started</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($incidents as $inc): ?>
                    <tr>
                        <td><?= htmlspecialchars($inc->getTitle()) ?></td>
                        <td><?= htmlspecialchars($inc->getServiceName() ?? '—') ?></td>
                        <td><?= $inc->getStartTime() ?></td>
                        <td><?= htmlspecialchars($inc->getStatus()) ?></td>
                        <td>
                            <?php if ($inc->isOpen()): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="action" value="<?= ActionType::RESOLVE_INCIDENT ?>">
                                    <input type="hidden" name="incident_id" value="<?= $inc->getId() ?>">
                                    <button type="submit" class="btn btn--small">Resolve</button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <h3>Log incident</h3>
        <form method="post">
            <input type="hidden" name="action" value="<?= ActionType::ADD_INCIDENT ?>">
            <div class="fields">
                <div class="field">
                    <label>Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="field">
                    <label>Description</label>
                    <textarea name="description" rows="2"></textarea>
                </div>
                <div class="field">
                    <label>Service (optional)</label>
                    <select name="service_id">
                        <option value="">— all —</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s->getId() ?>"><?= htmlspecialchars($s->getName()) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">Log incident</button>
        </form>
    </div>

<?php endif ?>

</main>

<footer>
    <small><a href="/">← Back to status page</a></small>
</footer>

</body>
</html>
