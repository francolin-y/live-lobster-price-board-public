<?php
define('ADMIN_APP', true);

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();

require_once __DIR__ . '/auth.php';

// ── Config ────────────────────────────────────────────────────────────────────

$config        = loadAdminConfig();
$configMissing = ($config === null);

// ── Login handler ─────────────────────────────────────────────────────────────

$loginError = '';

if (!$configMissing && !isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        $username === $config['admin_username']
        && password_verify($password, $config['admin_password_hash'])
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $username;
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Invalid username or password.';
    }
}

// ── Dashboard data ────────────────────────────────────────────────────────────

$dashboardData = null;
$jsonError     = '';

if (isLoggedIn()) {
    $jsonPath = __DIR__ . '/../data/current-prices.json';

    if (!file_exists($jsonPath)) {
        $jsonError = 'current-prices.json not found at /data/current-prices.json.';
    } else {
        $raw  = file_get_contents($jsonPath);
        $data = json_decode($raw, true);

        if ($data === null) {
            $jsonError = 'current-prices.json exists but contains invalid JSON.';
        } else {
            $dashboardData = $data;
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function safeGet(array $arr, string $key, string $fallback = '—'): string {
    $val = $arr[$key] ?? null;
    return (is_string($val) && $val !== '') ? $val : $fallback;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Live Lobster Price Board</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php if ($configMissing): ?>

<!-- ── Config missing error ───────────────────────────────────────────────── -->
<div class="setup-error-wrapper">
  <div class="setup-error-card">
    <h1>Admin Setup Required</h1>
    <p class="setup-error-msg">Admin config is missing. Create <code>admin/config.php</code> from <code>admin/config.example.php</code>.</p>

    <h2>Setup Steps</h2>
    <ol class="setup-steps">
      <li>Copy <code>admin/config.example.php</code> to <code>admin/config.php</code>.</li>
      <li>Generate a password hash using PHP:<br>
        <code>php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"</code>
      </li>
      <li>Paste the hash string into <code>admin/config.php</code> as <code>admin_password_hash</code>.</li>
      <li>Do not commit <code>admin/config.php</code> to Git.</li>
    </ol>
  </div>
</div>

<?php elseif (!isLoggedIn()): ?>

<!-- ── Login form ────────────────────────────────────────────────────────── -->
<div class="login-wrapper">
  <div class="login-card">
    <div class="login-header">
      <div class="login-logo">&#127754;</div>
      <h1>Admin Login</h1>
      <p class="login-subtitle">Live Lobster Price Board</p>
    </div>

    <?php if ($loginError !== ''): ?>
      <div class="login-error"><?= esc($loginError) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" autocomplete="off">
      <div class="field-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required>
      </div>
      <div class="field-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-login">Log In</button>
    </form>
  </div>
</div>

<?php else: ?>

<!-- ── Dashboard ─────────────────────────────────────────────────────────── -->
<div class="admin-wrapper">

  <header class="admin-header">
    <div class="admin-header-inner">
      <span class="admin-header-title">&#127754; Live Lobster Price Board &nbsp;·&nbsp; Admin</span>
      <nav class="admin-nav">
        <span class="admin-nav-user">Logged in as <?= esc($_SESSION['admin_username'] ?? 'admin') ?></span>
        <a href="logout.php" class="btn-logout">Log Out</a>
      </nav>
    </div>
  </header>

  <main class="admin-main">

    <!-- Current Stage notice -->
    <div class="stage-notice">
      <strong>Current Stage:</strong>
      This PHP admin backend currently reads the published JSON only.
      Save / Publish will be added in a later patch.
    </div>

    <?php if ($jsonError !== ''): ?>

      <div class="dashboard-card error-card">
        <div class="card-header">JSON Read Error</div>
        <div class="card-body">
          <p class="error-msg"><?= esc($jsonError) ?></p>
        </div>
      </div>

    <?php else:
      $contacts   = is_array($dashboardData['contacts'] ?? null)   ? $dashboardData['contacts']   : [];
      $prices     = is_array($dashboardData['prices']   ?? null)   ? $dashboardData['prices']     : [];
      $cnfAddOns  = is_array($dashboardData['cnfAddOns'] ?? null)  ? $dashboardData['cnfAddOns']  : [];
    ?>

      <!-- Overview card -->
      <div class="dashboard-card">
        <div class="card-header">Published Data Overview</div>
        <div class="card-body">
          <table class="overview-table">
            <tbody>
              <tr>
                <th>dataStatus</th>
                <td>
                  <span class="status-badge status-<?= esc(safeGet($dashboardData, 'dataStatus', 'unknown')) ?>">
                    <?= esc(safeGet($dashboardData, 'dataStatus', 'unknown')) ?>
                  </span>
                </td>
              </tr>
              <tr>
                <th>lastUpdated</th>
                <td><?= esc(safeGet($dashboardData, 'lastUpdated')) ?></td>
              </tr>
              <tr>
                <th>Price Rows</th>
                <td><?= count($prices) ?></td>
              </tr>
              <tr>
                <th>CNF Add-Ons</th>
                <td><?= count($cnfAddOns) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Contacts card -->
      <div class="dashboard-card">
        <div class="card-header">Contacts</div>
        <div class="card-body">
          <table class="overview-table">
            <tbody>
              <tr>
                <th>WeChat</th>
                <td><?= esc(safeGet($contacts, 'wechat')) ?></td>
              </tr>
              <tr>
                <th>Email</th>
                <td><?= esc(safeGet($contacts, 'email')) ?></td>
              </tr>
              <tr>
                <th>Phone</th>
                <td><?= esc(safeGet($contacts, 'phone')) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif; ?>

  </main>
</div>

<?php endif; ?>

</body>
</html>
