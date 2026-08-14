<?php
// ─── Live Lobster Price Board — Admin ────────────────────────────────────────
define('ADMIN_APP', true);
ini_set('display_errors', '0');

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();
require_once __DIR__ . '/auth.php';

// ── Config ────────────────────────────────────────────────────────────────────
$config        = loadAdminConfig();
$configMissing = ($config === null);

// ── JSON path ─────────────────────────────────────────────────────────────────
$jsonPath = __DIR__ . '/../data/current-prices.json';

// ── Load current JSON ─────────────────────────────────────────────────────────
$jsonData  = null;
$jsonError = '';

if (!file_exists($jsonPath)) {
    $jsonError = 'current-prices.json not found at /data/current-prices.json.';
} else {
    $raw     = @file_get_contents($jsonPath);
    $decoded = ($raw !== false) ? json_decode($raw, true) : null;
    if ($decoded === null) {
        $jsonError = 'current-prices.json contains invalid JSON.';
    } else {
        $jsonData = $decoded;
    }
}

// ── Status helpers ────────────────────────────────────────────────────────────
function statusOptions(): array {
    return [
        'available'         => 'Available',
        'limited'           => 'Limited',
        'soldOut'           => 'Sold Out',
        'contactSalesFirst' => 'Contact Sales First',
        'preOrderOnly'      => 'Pre-order Only',
    ];
}

function normalizeStatus(string $raw): string {
    static $map = [
        'available'           => 'available',
        'Available'           => 'available',
        'limited'             => 'limited',
        'Limited'             => 'limited',
        'soldOut'             => 'soldOut',
        'Sold Out'            => 'soldOut',
        'sold_out'            => 'soldOut',
        'contactSalesFirst'   => 'contactSalesFirst',
        'Contact Sales First' => 'contactSalesFirst',
        'contact_sales_first' => 'contactSalesFirst',
        'preOrderOnly'        => 'preOrderOnly',
        'Pre-order Only'      => 'preOrderOnly',
        'pre_order_only'      => 'preOrderOnly',
    ];
    return $map[$raw] ?? $raw;
}

function generateId(string $size): string {
    $id = strtolower($size);
    $id = preg_replace('/[^a-z0-9]+/', '-', $id);
    return 'size-' . trim($id, '-');
}

// ── Build normalized form data from POST ──────────────────────────────────────
function buildFormData(array $p): array {
    $prices = [];
    foreach ($p['prices'] ?? [] as $r) {
        $sv  = trim($r['size_single'] ?? '');
        $id  = trim($r['id'] ?? '');
        if ($id === '' && $sv !== '') {
            $id = generateId($sv);
        }
        $prices[] = [
            'id'       => $id,
            'size'     => $sv,
            'cadPerLb' => trim($r['cadPerLb'] ?? ''),
            'usdPerLb' => trim($r['usdPerLb'] ?? ''),
            'rmbPerKg' => trim($r['rmbPerKg'] ?? ''),
            'status'   => normalizeStatus(trim($r['status'] ?? '')),
            'notes'    => [
                'zhHans' => trim($r['notes']['zhHans'] ?? ''),
                'zhHant' => trim($r['notes']['zhHant'] ?? ''),
                'en'     => trim($r['notes']['en'] ?? ''),
            ],
        ];
    }
    $cnf = [];
    foreach ($p['cnf'] ?? [] as $r) {
        $cnfId = trim($r['id'] ?? '');
        $cnf[] = [
            'id'     => $cnfId,
            'region' => [
                'zhHans' => trim($r['region']['zhHans'] ?? ''),
                'zhHant' => trim($r['region']['zhHant'] ?? ''),
                'en'     => trim($r['region']['en'] ?? ''),
            ],
            'addOn'  => trim($r['addOn'] ?? ''),
            'notes'  => [
                'zhHans' => trim($r['notes']['zhHans'] ?? ''),
                'zhHant' => trim($r['notes']['zhHant'] ?? ''),
                'en'     => trim($r['notes']['en'] ?? ''),
            ],
        ];
    }
    return [
        'dataStatus'   => trim($p['dataStatus'] ?? ''),
        'lastUpdated'  => trim($p['lastUpdated'] ?? ''),
        'announcement' => [
            'zhHans' => trim($p['ann_zhHans'] ?? ''),
            'zhHant' => trim($p['ann_zhHant'] ?? ''),
            'en'     => trim($p['ann_en'] ?? ''),
        ],
        'contacts'     => [
            'wechat' => trim($p['wechat'] ?? ''),
            'email'  => trim($p['email'] ?? ''),
            'phone'  => trim($p['phone'] ?? ''),
        ],
        'prices'       => $prices,
        'cnfAddOns'    => $cnf,
        'disclaimer'   => [
            'zhHans' => trim($p['disc_zhHans'] ?? ''),
            'zhHant' => trim($p['disc_zhHant'] ?? ''),
            'en'     => trim($p['disc_en'] ?? ''),
        ],
    ];
}

// ── Validate ──────────────────────────────────────────────────────────────────
function validatePriceBoard(array $d): array {
    $errors = $warnings = [];
    $nz = fn($v) => is_string($v) && $v !== '';

    // A · Page Status
    if (!in_array($d['dataStatus'], ['sample', 'live'], true)) {
        $errors[] = '[A · Page Status] dataStatus must be "sample" or "live".';
    }
    if (!$nz($d['lastUpdated'])) {
        $errors[] = '[A · Page Status] lastUpdated is required.';
    }
    if ($d['dataStatus'] === 'sample') {
        $warnings[] = '[A · Page Status] dataStatus is "sample". Public page will show the sample data banner.';
    }

    // B · Announcement (optional — warn if all empty)
    $ann = $d['announcement'] ?? [];
    if (!$nz($ann['zhHans'] ?? '') && !$nz($ann['zhHant'] ?? '') && !$nz($ann['en'] ?? '')) {
        $warnings[] = '[B · Announcement] Announcement is empty.';
    }

    // C · Contacts
    foreach (['wechat', 'email', 'phone'] as $f) {
        $val = $d['contacts'][$f] ?? '';
        if (!$nz($val))         $errors[]   = "[C · Contacts] $f is required.";
        elseif ($val === 'TBD') $warnings[] = "[C · Contacts] $f is TBD.";
    }

    // D · Price Rows
    if (empty($d['prices'])) {
        $errors[] = '[D · Price Rows] At least one price row is required.';
    }
    $tbdPrice = false;
    foreach ($d['prices'] as $i => $r) {
        $n    = $i + 1;
        $size = is_array($r['size'] ?? null)
            ? ($r['size']['en'] ?? $r['size']['zhHans'] ?? '')
            : ($r['size'] ?? '');
        if (!$nz($r['id']))       $errors[] = "[D · Price Rows] Row $n: id is required.";
        if (!$nz($size))          $errors[] = "[D · Price Rows] Row $n: size is required.";
        if (!$nz($r['cadPerLb'])) $errors[] = "[D · Price Rows] Row $n: cadPerLb is required.";
        if (!$nz($r['usdPerLb'])) $errors[] = "[D · Price Rows] Row $n: usdPerLb is required.";
        if (!$nz($r['rmbPerKg'])) $errors[] = "[D · Price Rows] Row $n: rmbPerKg is required.";
        if (!$nz($r['status']))   $errors[] = "[D · Price Rows] Row $n: status is required.";
        foreach (['cadPerLb', 'usdPerLb', 'rmbPerKg'] as $pf) {
            if (($r[$pf] ?? '') === 'TBD') $tbdPrice = true;
        }
    }
    if ($tbdPrice) {
        $warnings[] = '[D · Price Rows] Some price fields are TBD.';
        if ($d['dataStatus'] === 'live') {
            $warnings[] = '[A · Page Status] dataStatus is "live" but some price fields are TBD. Confirm before publishing.';
        }
    }

    // E · CNF Add-ons
    foreach ($d['cnfAddOns'] as $i => $r) {
        $n = $i + 1;
        if (!$nz($r['id']))               $errors[] = "[E · CNF Add-ons] Row $n: id is required.";
        if (!$nz($r['region']['zhHans'])) $errors[] = "[E · CNF Add-ons] Row $n: region.zhHans is required.";
        if (!$nz($r['region']['zhHant'])) $errors[] = "[E · CNF Add-ons] Row $n: region.zhHant is required.";
        if (!$nz($r['region']['en']))     $errors[] = "[E · CNF Add-ons] Row $n: region.en is required.";
        if (!$nz($r['addOn']))            $errors[] = "[E · CNF Add-ons] Row $n: addOn is required.";
    }

    // F · Disclaimer (optional)

    return ['errors' => $errors, 'warnings' => $warnings];
}

// ── Publish ───────────────────────────────────────────────────────────────────
function publishJson(array $data, string $jsonPath): array {
    $backupDir = dirname($jsonPath) . '/backups';

    if (!is_dir($backupDir)) {
        if (!@mkdir($backupDir, 0755, true)) {
            return ['success' => false, 'error' => 'Publish failed. Backup directory could not be created.', 'backup' => null];
        }
    }

    $ts         = date('Y-m-d-His');
    $backupName = 'current-prices-backup-' . $ts . '.json';
    $backupPath = $backupDir . '/' . $backupName;

    if (file_exists($jsonPath) && !@copy($jsonPath, $backupPath)) {
        return ['success' => false, 'error' => 'Publish failed. Backup could not be created.', 'backup' => null];
    }

    $newJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $tmpPath = $jsonPath . '.tmp';

    $bytes = @file_put_contents($tmpPath, $newJson, LOCK_EX);
    if ($bytes === false) {
        @unlink($tmpPath);
        return ['success' => false, 'error' => 'Publish failed. current-prices.json could not be updated.', 'backup' => $backupName];
    }

    if (!@rename($tmpPath, $jsonPath)) {
        @unlink($tmpPath);
        return ['success' => false, 'error' => 'Publish failed. current-prices.json could not be updated.', 'backup' => $backupName];
    }

    return ['success' => true, 'backup' => $backupName, 'error' => null];
}

// ── State ─────────────────────────────────────────────────────────────────────
$loginError       = '';
$formError        = '';
$isPreviewing     = false;
$isPublishing     = false;
$publishSuccess   = false;
$publishError     = '';
$backupFilename   = '';
$previewJson      = null;
$validationResult = null;
$fd               = null;

// ── Request dispatch ──────────────────────────────────────────────────────────
if (!$configMissing && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login' && !isLoggedIn()) {
        $u  = trim($_POST['username'] ?? '');
        $pw = $_POST['password'] ?? '';
        if ($u === $config['admin_username'] && password_verify($pw, $config['admin_password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = $u;
            $_SESSION['csrf_token']      = bin2hex(random_bytes(32));
            header('Location: index.php');
            exit;
        }
        $loginError = 'Invalid username or password.';

    } elseif ($action === 'preview' && isLoggedIn()) {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            $formError = 'Security token mismatch. Refresh the page and try again.';
        } else {
            $isPreviewing     = true;
            $fd               = buildFormData($_POST);
            $validationResult = validatePriceBoard($fd);
            $previewJson      = json_encode($fd, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

    } elseif ($action === 'publish' && isLoggedIn()) {
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            $formError = 'Security token mismatch. Refresh the page and try again.';
        } else {
            $isPublishing     = true;
            $fd               = buildFormData($_POST);
            $validationResult = validatePriceBoard($fd);

            if (!empty($validationResult['errors'])) {
                $publishError = 'Publish blocked. Fix all validation errors first.';
                $previewJson  = json_encode($fd, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $result = publishJson($fd, $jsonPath);
                if ($result['success']) {
                    $publishSuccess = true;
                    $backupFilename = $result['backup'];
                    $saved = @json_decode(@file_get_contents($jsonPath), true);
                    if (is_array($saved)) { $fd = $saved; }
                } else {
                    $publishError   = $result['error'];
                    $backupFilename = $result['backup'] ?? '';
                    $previewJson    = json_encode($fd, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }
    }
}

if (isLoggedIn() && $fd === null) { $fd = $jsonData; }
if (isLoggedIn() && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Template helpers ──────────────────────────────────────────────────────────
function sv(array $arr, string ...$keys): string {
    $cur = $arr;
    foreach ($keys as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur)) return '';
        $cur = $cur[$k];
    }
    return is_string($cur) ? $cur : (is_numeric($cur) ? (string)$cur : '');
}

function fv(array $arr, string ...$keys): string { return esc(sv($arr, ...$keys)); }

function sel(string $current, string $option): string {
    return $current === $option ? ' selected' : '';
}

function extractSize(array $row): string {
    $s = $row['size'] ?? '';
    if (is_array($s)) { return $s['en'] ?? $s['zhHans'] ?? $s['zhHant'] ?? ''; }
    return is_string($s) ? $s : '';
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

<div class="setup-error-wrapper">
  <div class="setup-error-card">
    <h1>Admin Setup Required</h1>
    <p class="setup-error-msg">Admin config is missing. Create <code>admin/config.php</code> from <code>admin/config.example.php</code>.</p>
    <h2>Setup Steps</h2>
    <ol class="setup-steps">
      <li>Copy <code>admin/config.example.php</code> to <code>admin/config.php</code>.</li>
      <li>Generate a password hash:<br>
        <code>php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"</code>
      </li>
      <li>Paste the hash into <code>admin/config.php</code> as <code>admin_password_hash</code>.</li>
      <li>Do not commit <code>admin/config.php</code> to Git.</li>
    </ol>
  </div>
</div>

<?php elseif (!isLoggedIn()): ?>

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
      <input type="hidden" name="action" value="login">
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

    <?php if ($formError !== ''): ?>
      <div class="form-error-banner"><?= esc($formError) ?></div>
    <?php endif; ?>

    <?php if ($fd === null): ?>
      <div class="dashboard-card error-card">
        <div class="card-header">JSON Read Error</div>
        <div class="card-body"><p class="error-msg"><?= esc($jsonError) ?></p></div>
      </div>
    <?php else: ?>

    <!-- Expand / Collapse All ────────────────────────────────────────────── -->
    <div class="sec-controls">
      <button type="button" class="btn-sec-ctrl" onclick="expandAllSections()">Expand All</button>
      <button type="button" class="btn-sec-ctrl" onclick="collapseAllSections()">Collapse All</button>
    </div>

    <form method="POST" action="index.php" id="admin-form">
      <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

      <!-- ── A · Page Status (expanded) ────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          <button type="button" class="sec-toggle" aria-expanded="true" aria-controls="sec-a-body"
                  onclick="toggleSection('sec-a-body')">
            <span>A &nbsp;·&nbsp; Page Status</span>
            <span class="sec-toggle-icon" aria-hidden="true">▾</span>
          </button>
        </div>
        <div class="form-section-body" id="sec-a-body">
          <div class="row-fields dual">
            <div class="fg">
              <label for="dataStatus">dataStatus</label>
              <select id="dataStatus" name="dataStatus">
                <option value="sample"<?= sel(sv($fd, 'dataStatus'), 'sample') ?>>sample</option>
                <option value="live"<?= sel(sv($fd, 'dataStatus'), 'live') ?>>live</option>
              </select>
            </div>
            <div class="fg">
              <label for="lastUpdated">lastUpdated</label>
              <input type="text" id="lastUpdated" name="lastUpdated"
                     value="<?= fv($fd, 'lastUpdated') ?>"
                     placeholder="e.g. 2026-08-14 10:00 AST">
            </div>
          </div>
        </div>
      </div>

      <!-- ── B · Announcement (collapsed) ──────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          <button type="button" class="sec-toggle" aria-expanded="false" aria-controls="sec-b-body"
                  onclick="toggleSection('sec-b-body')">
            <span>B &nbsp;·&nbsp; Announcement</span>
            <span class="sec-toggle-icon" aria-hidden="true">▾</span>
          </button>
        </div>
        <div class="form-section-body sec-collapsed" id="sec-b-body">
          <div class="row-fields triple">
            <div class="fg">
              <label>zhHans</label>
              <textarea name="ann_zhHans"><?= fv($fd, 'announcement', 'zhHans') ?></textarea>
            </div>
            <div class="fg">
              <label>zhHant</label>
              <textarea name="ann_zhHant"><?= fv($fd, 'announcement', 'zhHant') ?></textarea>
            </div>
            <div class="fg">
              <label>en</label>
              <textarea name="ann_en"><?= fv($fd, 'announcement', 'en') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- ── C · Contacts (expanded) ───────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          <button type="button" class="sec-toggle" aria-expanded="true" aria-controls="sec-c-body"
                  onclick="toggleSection('sec-c-body')">
            <span>C &nbsp;·&nbsp; Contacts</span>
            <span class="sec-toggle-icon" aria-hidden="true">▾</span>
          </button>
        </div>
        <div class="form-section-body" id="sec-c-body">
          <div class="row-fields triple">
            <div class="fg">
              <label>wechat</label>
              <input type="text" name="wechat" value="<?= fv($fd, 'contacts', 'wechat') ?>">
            </div>
            <div class="fg">
              <label>email</label>
              <input type="text" name="email" value="<?= fv($fd, 'contacts', 'email') ?>">
            </div>
            <div class="fg">
              <label>phone</label>
              <input type="text" name="phone" value="<?= fv($fd, 'contacts', 'phone') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- ── D · Price Rows (collapsed) ────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          <button type="button" class="sec-toggle" aria-expanded="false" aria-controls="sec-d-body"
                  onclick="toggleSection('sec-d-body')">
            <span>D &nbsp;·&nbsp; Price Rows <span class="row-count" id="price-row-count"></span></span>
            <span class="sec-toggle-icon" aria-hidden="true">▾</span>
          </button>
        </div>
        <div class="form-section-body sec-collapsed" id="sec-d-body">
          <div id="price-rows-container">
            <?php foreach (($fd['prices'] ?? []) as $i => $row):
              $rowStatus = normalizeStatus(sv($row, 'status'));
            ?>
            <div class="data-row" id="price-row-<?= $i ?>">
              <div class="data-row-header">
                <span class="data-row-label">Row <?= $i + 1 ?></span>
                <button type="button" class="btn-delete-row"
                        onclick="deleteRow('price-row-<?= $i ?>', 'price-row-count')">Remove</button>
              </div>
              <div class="data-row-body">
                <div class="row-fields dual">
                  <div class="fg">
                    <label>Size</label>
                    <input type="text" name="prices[<?= $i ?>][size_single]"
                           value="<?= esc(extractSize($row)) ?>"
                           placeholder="e.g. 1.25–1.50 lb">
                  </div>
                  <div class="fg">
                    <label>Status</label>
                    <select name="prices[<?= $i ?>][status]">
                      <?php foreach (statusOptions() as $val => $label): ?>
                        <option value="<?= esc($val) ?>"<?= sel($rowStatus, $val) ?>><?= esc($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="fg fg-id">
                  <label>Internal ID <span class="fg-hint">Used by the system to track this row. Usually do not edit.</span></label>
                  <input type="text" name="prices[<?= $i ?>][id]" value="<?= fv($row, 'id') ?>">
                </div>
                <div class="row-subheader">Prices</div>
                <div class="row-fields triple">
                  <div class="fg">
                    <label>cadPerLb</label>
                    <input type="text" name="prices[<?= $i ?>][cadPerLb]" value="<?= fv($row, 'cadPerLb') ?>">
                  </div>
                  <div class="fg">
                    <label>usdPerLb</label>
                    <input type="text" name="prices[<?= $i ?>][usdPerLb]" value="<?= fv($row, 'usdPerLb') ?>">
                  </div>
                  <div class="fg">
                    <label>rmbPerKg</label>
                    <input type="text" name="prices[<?= $i ?>][rmbPerKg]" value="<?= fv($row, 'rmbPerKg') ?>">
                  </div>
                </div>
                <div class="row-subheader">Notes <span class="row-subheader-opt">(optional)</span></div>
                <div class="row-fields triple">
                  <div class="fg">
                    <label>zhHans</label>
                    <textarea name="prices[<?= $i ?>][notes][zhHans]"><?= fv($row, 'notes', 'zhHans') ?></textarea>
                  </div>
                  <div class="fg">
                    <label>zhHant</label>
                    <textarea name="prices[<?= $i ?>][notes][zhHant]"><?= fv($row, 'notes', 'zhHant') ?></textarea>
                  </div>
                  <div class="fg">
                    <label>en</label>
                    <textarea name="prices[<?= $i ?>][notes][en]"><?= fv($row, 'notes', 'en') ?></textarea>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="add-row-bar">
            <button type="button" class="btn-add-row" onclick="addPriceRow()">+ Add Price Row</button>
          </div>
        </div>
      </div>

      <!-- ── E · CNF Add-ons (collapsed) ───────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          <button type="button" class="sec-toggle" aria-expanded="false" aria-controls="sec-e-body"
                  onclick="toggleSection('sec-e-body')">
            <span>E &nbsp;·&nbsp; CNF Add-ons <span class="row-count" id="cnf-row-count"></span></span>
            <span class="sec-toggle-icon" aria-hidden="true">▾</span>
          </button>
        </div>
        <div class="form-section-body sec-collapsed" id="sec-e-body">
          <div id="cnf-rows-container">
            <?php foreach (($fd['cnfAddOns'] ?? []) as $i => $row): ?>
            <div class="data-row" id="cnf-row-<?= $i ?>">
              <div class="data-row-header">
                <span class="data-row-label">Row <?= $i + 1 ?></span>
                <button type="button" class="btn-delete-row"
                        onclick="deleteRow('cnf-row-<?= $i ?>', 'cnf-row-count')">Remove</button>
              </div>
              <div class="data-row-body">
                <div class="row-fields dual">
                  <div class="fg">
                    <label>addOn</label>
                    <input type="text" name="cnf[<?= $i ?>][addOn]" value="<?= fv($row, 'addOn') ?>">
                  </div>
                  <div class="fg fg-id">
                    <label>Internal ID <span class="fg-hint">Usually do not edit.</span></label>
                    <input type="text" name="cnf[<?= $i ?>][id]" value="<?= fv($row, 'id') ?>">
                  </div>
                </div>
                <div class="row-subheader">Region</div>
                <div class="row-fields triple">
                  <div class="fg">
                    <label>zhHans</label>
                    <input type="text" name="cnf[<?= $i ?>][region][zhHans]"
                           value="<?= fv($row, 'region', 'zhHans') ?>">
                  </div>
                  <div class="fg">
                    <label>zhHant</label>
                    <input type="text" name="cnf[<?= $i ?>][region][zhHant]"
                           value="<?= fv($row, 'region', 'zhHant') ?>">
                  </div>
                  <div class="fg">
                    <label>en</label>
                    <input type="text" name="cnf[<?= $i ?>][region][en]"
                           value="<?= fv($row, 'region', 'en') ?>">
                  </div>
                </div>
                <div class="row-subheader">Notes <span class="row-subheader-opt">(optional)</span></div>
                <div class="row-fields triple">
                  <div class="fg">
                    <label>zhHans</label>
                    <textarea name="cnf[<?= $i ?>][notes][zhHans]"><?= fv($row, 'notes', 'zhHans') ?></textarea>
                  </div>
                  <div class="fg">
                    <label>zhHant</label>
                    <textarea name="cnf[<?= $i ?>][notes][zhHant]"><?= fv($row, 'notes', 'zhHant') ?></textarea>
                  </div>
                  <div class="fg">
                    <label>en</label>
                    <textarea name="cnf[<?= $i ?>][notes][en]"><?= fv($row, 'notes', 'en') ?></textarea>
                  </div>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="add-row-bar">
            <button type="button" class="btn-add-row" onclick="addCnfRow()">+ Add CNF Row</button>
          </div>
        </div>
      </div>

      <!-- ── F · Disclaimer (collapsed) ────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          <button type="button" class="sec-toggle" aria-expanded="false" aria-controls="sec-f-body"
                  onclick="toggleSection('sec-f-body')">
            <span>F &nbsp;·&nbsp; Disclaimer <span class="sec-opt">(optional)</span></span>
            <span class="sec-toggle-icon" aria-hidden="true">▾</span>
          </button>
        </div>
        <div class="form-section-body sec-collapsed" id="sec-f-body">
          <div class="row-fields triple">
            <div class="fg">
              <label>zhHans</label>
              <textarea name="disc_zhHans" rows="4"><?= fv($fd, 'disclaimer', 'zhHans') ?></textarea>
            </div>
            <div class="fg">
              <label>zhHant</label>
              <textarea name="disc_zhHant" rows="4"><?= fv($fd, 'disclaimer', 'zhHant') ?></textarea>
            </div>
            <div class="fg">
              <label>en</label>
              <textarea name="disc_en" rows="4"><?= fv($fd, 'disclaimer', 'en') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Form actions ───────────────────────────────────────────────── -->
      <div class="form-actions">
        <div class="form-actions-preview">
          <button type="submit" name="action" value="preview" class="btn-preview">
            Preview Changes
          </button>
          <span class="form-actions-note">Validates and shows JSON output. Does not save.</span>
        </div>
        <div class="form-actions-divider"></div>
        <div class="form-actions-publish">
          <p class="publish-warning">
            Publishing will overwrite <code>/data/current-prices.json</code> after creating a timestamped backup.
          </p>
          <button type="submit" name="action" value="publish" class="btn-publish" id="btn-publish">
            Publish to Public Page
          </button>
        </div>
      </div>

      <!-- ── G · Validation & JSON Preview ─────────────────────────────── -->
      <?php
        $gExpanded  = $isPreviewing || $isPublishing || $publishSuccess;
        $gAriaExp   = $gExpanded ? 'true' : 'false';
        $gBodyClass = $gExpanded ? 'form-section-body' : 'form-section-body sec-collapsed';
      ?>
      <div class="form-section">
        <div class="form-section-header">
          <button type="button" class="sec-toggle" aria-expanded="<?= $gAriaExp ?>"
                  aria-controls="sec-g-body" onclick="toggleSection('sec-g-body')">
            <span>G &nbsp;·&nbsp; Validation &amp; JSON Preview</span>
            <span class="sec-toggle-icon" aria-hidden="true">▾</span>
          </button>
        </div>
        <div class="<?= $gBodyClass ?>" id="sec-g-body">

          <?php if (!$isPreviewing && !$isPublishing): ?>

            <p class="vp-idle">
              Click <strong>Preview Changes</strong> to validate and see the JSON output,
              or <strong>Publish to Public Page</strong> to validate and write to the server.
            </p>

          <?php elseif ($publishSuccess): ?>

            <div class="publish-success-box">
              <div class="publish-success-icon">&#10003;</div>
              <p class="publish-success-title">Publish successful.</p>
              <p class="publish-success-body">Public page now reads the updated <code>current-prices.json</code>.</p>
              <?php if ($backupFilename !== ''): ?>
                <p class="publish-success-backup">Backup saved: <code><?= esc($backupFilename) ?></code></p>
              <?php endif; ?>
              <div class="publish-success-links">
                <a href="../" target="_blank" class="publish-link">View Public Page &#8599;</a>
                <a href="../data/current-prices.json" target="_blank" class="publish-link publish-link-secondary">
                  View current-prices.json &#8599;
                </a>
              </div>
            </div>
            <?php if (!empty($validationResult['warnings'])): ?>
              <div class="vp-block-label vp-warn-label" style="margin-top:20px;">
                Warnings published with this version
              </div>
              <ul class="vp-list vp-warnings">
                <?php foreach ($validationResult['warnings'] as $w): ?>
                  <li><?= esc($w) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

          <?php else:
            $errors   = $validationResult['errors']   ?? [];
            $warnings = $validationResult['warnings'] ?? [];
          ?>

            <?php if ($publishError !== ''): ?>
              <div class="publish-error-box">
                <?= esc($publishError) ?>
                <?php if ($backupFilename !== ''): ?>
                  <br><span class="publish-error-detail">Backup at: <code><?= esc($backupFilename) ?></code></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (empty($errors) && empty($warnings)): ?>
              <div class="vp-pass">&#10003; No errors or warnings.</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
              <div class="vp-block-label">Errors — must fix before publishing</div>
              <ul class="vp-list vp-errors">
                <?php foreach ($errors as $e): ?>
                  <li><?= esc($e) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if (!empty($warnings)): ?>
              <div class="vp-block-label vp-warn-label">Warnings — review before publishing</div>
              <ul class="vp-list vp-warnings">
                <?php foreach ($warnings as $w): ?>
                  <li><?= esc($w) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <?php if ($previewJson !== null): ?>
              <div class="vp-block-label" style="margin-top:20px;">JSON Preview</div>
              <p class="vp-preview-note">
                <?php if ($isPublishing): ?>
                  Publish was blocked. The JSON below has <strong>not</strong> been written.
                <?php else: ?>
                  This is what <code>current-prices.json</code> would contain. No file has been written.
                <?php endif; ?>
              </p>
              <textarea class="json-preview-area" readonly><?= esc($previewJson) ?></textarea>
            <?php endif; ?>

          <?php endif; ?>

        </div>
      </div>

    </form>

    <?php endif; ?>
  </main>
</div>

<?php endif; ?>

<script>
// ── Section IDs ──────────────────────────────────────────────────────────────
var ALL_SECTION_BODIES = ['sec-a-body','sec-b-body','sec-c-body','sec-d-body','sec-e-body','sec-f-body','sec-g-body'];

// ── Collapsible sections ─────────────────────────────────────────────────────
function toggleSection(bodyId) {
  var body = document.getElementById(bodyId);
  if (!body) return;
  var willExpand = body.classList.contains('sec-collapsed');
  body.classList.toggle('sec-collapsed');
  var toggle = document.querySelector('[aria-controls="' + bodyId + '"]');
  if (toggle) toggle.setAttribute('aria-expanded', willExpand ? 'true' : 'false');
}

function expandAllSections() {
  ALL_SECTION_BODIES.forEach(function(id) {
    var body = document.getElementById(id);
    if (!body) return;
    body.classList.remove('sec-collapsed');
    var toggle = document.querySelector('[aria-controls="' + id + '"]');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
  });
}

function collapseAllSections() {
  ALL_SECTION_BODIES.forEach(function(id) {
    var body = document.getElementById(id);
    if (!body) return;
    body.classList.add('sec-collapsed');
    var toggle = document.querySelector('[aria-controls="' + id + '"]');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  });
}

// ── Row counters ─────────────────────────────────────────────────────────────
var priceRowCount = <?= count($fd['prices']    ?? []) ?>;
var cnfRowCount   = <?= count($fd['cnfAddOns'] ?? []) ?>;

function updateCount(badgeId, containerId) {
  var badge = document.getElementById(badgeId);
  if (!badge) return;
  var n = document.querySelectorAll('#' + containerId + ' > .data-row').length;
  badge.textContent = n + (n === 1 ? ' row' : ' rows');
}

function deleteRow(rowId, badgeId) {
  var el = document.getElementById(rowId);
  if (el) el.remove();
  var containerId = rowId.indexOf('price') === 0 ? 'price-rows-container' : 'cnf-rows-container';
  updateCount(badgeId, containerId);
}

// ── Status select options HTML ────────────────────────────────────────────────
var STATUS_SELECT_OPTIONS =
  '<option value="available">Available</option>' +
  '<option value="limited">Limited</option>' +
  '<option value="soldOut">Sold Out</option>' +
  '<option value="contactSalesFirst">Contact Sales First</option>' +
  '<option value="preOrderOnly">Pre-order Only</option>';

// ── Add Price Row ────────────────────────────────────────────────────────────
function addPriceRow() {
  var idx = priceRowCount++;
  var container = document.getElementById('price-rows-container');
  var n = container.querySelectorAll('.data-row').length + 1;
  var div = document.createElement('div');
  div.className = 'data-row';
  div.id = 'price-row-' + idx;
  div.innerHTML =
    '<div class="data-row-header">' +
      '<span class="data-row-label">Row ' + n + '</span>' +
      '<button type="button" class="btn-delete-row" onclick="deleteRow(\'price-row-' + idx + '\',\'price-row-count\')">Remove</button>' +
    '</div>' +
    '<div class="data-row-body">' +
      '<div class="row-fields dual">' +
        '<div class="fg"><label>Size</label><input type="text" name="prices[' + idx + '][size_single]" value="" placeholder="e.g. 1.25–1.50 lb"></div>' +
        '<div class="fg"><label>Status</label><select name="prices[' + idx + '][status]">' + STATUS_SELECT_OPTIONS + '</select></div>' +
      '</div>' +
      '<div class="fg fg-id"><label>Internal ID <span class="fg-hint">Used by the system. Usually do not edit.</span></label>' +
        '<input type="text" name="prices[' + idx + '][id]" value=""></div>' +
      '<div class="row-subheader">Prices</div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>cadPerLb</label><input type="text" name="prices[' + idx + '][cadPerLb]" value=""></div>' +
        '<div class="fg"><label>usdPerLb</label><input type="text" name="prices[' + idx + '][usdPerLb]" value=""></div>' +
        '<div class="fg"><label>rmbPerKg</label><input type="text" name="prices[' + idx + '][rmbPerKg]" value=""></div>' +
      '</div>' +
      '<div class="row-subheader">Notes <span class="row-subheader-opt">(optional)</span></div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>zhHans</label><textarea name="prices[' + idx + '][notes][zhHans]"></textarea></div>' +
        '<div class="fg"><label>zhHant</label><textarea name="prices[' + idx + '][notes][zhHant]"></textarea></div>' +
        '<div class="fg"><label>en</label><textarea name="prices[' + idx + '][notes][en]"></textarea></div>' +
      '</div>' +
    '</div>';
  container.appendChild(div);
  updateCount('price-row-count', 'price-rows-container');
}

// ── Add CNF Row ──────────────────────────────────────────────────────────────
function addCnfRow() {
  var idx = cnfRowCount++;
  var container = document.getElementById('cnf-rows-container');
  var n = container.querySelectorAll('.data-row').length + 1;
  var div = document.createElement('div');
  div.className = 'data-row';
  div.id = 'cnf-row-' + idx;
  div.innerHTML =
    '<div class="data-row-header">' +
      '<span class="data-row-label">Row ' + n + '</span>' +
      '<button type="button" class="btn-delete-row" onclick="deleteRow(\'cnf-row-' + idx + '\',\'cnf-row-count\')">Remove</button>' +
    '</div>' +
    '<div class="data-row-body">' +
      '<div class="row-fields dual">' +
        '<div class="fg"><label>addOn</label><input type="text" name="cnf[' + idx + '][addOn]" value=""></div>' +
        '<div class="fg fg-id"><label>Internal ID <span class="fg-hint">Usually do not edit.</span></label>' +
          '<input type="text" name="cnf[' + idx + '][id]" value=""></div>' +
      '</div>' +
      '<div class="row-subheader">Region</div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>zhHans</label><input type="text" name="cnf[' + idx + '][region][zhHans]" value=""></div>' +
        '<div class="fg"><label>zhHant</label><input type="text" name="cnf[' + idx + '][region][zhHant]" value=""></div>' +
        '<div class="fg"><label>en</label><input type="text" name="cnf[' + idx + '][region][en]" value=""></div>' +
      '</div>' +
      '<div class="row-subheader">Notes <span class="row-subheader-opt">(optional)</span></div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>zhHans</label><textarea name="cnf[' + idx + '][notes][zhHans]"></textarea></div>' +
        '<div class="fg"><label>zhHant</label><textarea name="cnf[' + idx + '][notes][zhHant]"></textarea></div>' +
        '<div class="fg"><label>en</label><textarea name="cnf[' + idx + '][notes][en]"></textarea></div>' +
      '</div>' +
    '</div>';
  container.appendChild(div);
  updateCount('cnf-row-count', 'cnf-rows-container');
}

// ── Publish confirm ──────────────────────────────────────────────────────────
var publishBtn = document.getElementById('btn-publish');
if (publishBtn) {
  publishBtn.addEventListener('click', function(e) {
    if (!confirm('Publish these changes to the public price board?')) {
      e.preventDefault();
    }
  });
}

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  updateCount('price-row-count', 'price-rows-container');
  updateCount('cnf-row-count', 'cnf-rows-container');
});
</script>

</body>
</html>
