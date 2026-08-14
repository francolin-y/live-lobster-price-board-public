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

// ── Load current JSON ─────────────────────────────────────────────────────────
$jsonData  = null;
$jsonError = '';

$jsonPath = __DIR__ . '/../data/current-prices.json';
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

// ── Build normalized form data from POST ──────────────────────────────────────
function buildFormData(array $p): array {
    $prices = [];
    foreach ($p['prices'] ?? [] as $r) {
        $prices[] = [
            'id'       => trim($r['id'] ?? ''),
            'size'     => [
                'zhHans' => trim($r['size']['zhHans'] ?? ''),
                'zhHant' => trim($r['size']['zhHant'] ?? ''),
                'en'     => trim($r['size']['en'] ?? ''),
            ],
            'cadPerLb' => trim($r['cadPerLb'] ?? ''),
            'usdPerLb' => trim($r['usdPerLb'] ?? ''),
            'rmbPerKg' => trim($r['rmbPerKg'] ?? ''),
            'status'   => trim($r['status'] ?? ''),
            'notes'    => [
                'zhHans' => trim($r['notes']['zhHans'] ?? ''),
                'zhHant' => trim($r['notes']['zhHant'] ?? ''),
                'en'     => trim($r['notes']['en'] ?? ''),
            ],
        ];
    }
    $cnf = [];
    foreach ($p['cnf'] ?? [] as $r) {
        $cnf[] = [
            'id'     => trim($r['id'] ?? ''),
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

    // B · Announcement
    if (!$nz($d['announcement']['zhHans'])) $errors[] = '[B · Announcement] Simplified Chinese is required.';
    if (!$nz($d['announcement']['zhHant'])) $errors[] = '[B · Announcement] Traditional Chinese is required.';
    if (!$nz($d['announcement']['en']))     $errors[] = '[B · Announcement] English announcement is required.';

    // C · Contacts
    foreach (['wechat', 'email', 'phone'] as $f) {
        $val = $d['contacts'][$f] ?? '';
        if (!$nz($val))       $errors[]   = "[C · Contacts] $f is required.";
        elseif ($val === 'TBD') $warnings[] = "[C · Contacts] $f is TBD.";
    }

    // D · Price Rows
    if (empty($d['prices'])) {
        $errors[] = '[D · Price Rows] At least one price row is required.';
    }
    $tbdPrice = false;
    foreach ($d['prices'] as $i => $r) {
        $n = $i + 1;
        if (!$nz($r['id']))             $errors[] = "[D · Price Rows] Row $n: id is required.";
        if (!$nz($r['size']['zhHans'])) $errors[] = "[D · Price Rows] Row $n: size.zhHans is required.";
        if (!$nz($r['size']['zhHant'])) $errors[] = "[D · Price Rows] Row $n: size.zhHant is required.";
        if (!$nz($r['size']['en']))     $errors[] = "[D · Price Rows] Row $n: size.en is required.";
        if (!$nz($r['cadPerLb']))       $errors[] = "[D · Price Rows] Row $n: cadPerLb is required.";
        if (!$nz($r['usdPerLb']))       $errors[] = "[D · Price Rows] Row $n: usdPerLb is required.";
        if (!$nz($r['rmbPerKg']))       $errors[] = "[D · Price Rows] Row $n: rmbPerKg is required.";
        if (!$nz($r['status']))         $errors[] = "[D · Price Rows] Row $n: status is required.";
        if (!$nz($r['notes']['zhHans'])) $errors[] = "[D · Price Rows] Row $n: notes.zhHans is required.";
        if (!$nz($r['notes']['zhHant'])) $errors[] = "[D · Price Rows] Row $n: notes.zhHant is required.";
        if (!$nz($r['notes']['en']))     $errors[] = "[D · Price Rows] Row $n: notes.en is required.";
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
        if (!$nz($r['notes']['zhHans']))  $errors[] = "[E · CNF Add-ons] Row $n: notes.zhHans is required.";
        if (!$nz($r['notes']['zhHant']))  $errors[] = "[E · CNF Add-ons] Row $n: notes.zhHant is required.";
        if (!$nz($r['notes']['en']))      $errors[] = "[E · CNF Add-ons] Row $n: notes.en is required.";
    }

    // F · Disclaimer
    if (!$nz($d['disclaimer']['zhHans'])) $errors[] = '[F · Disclaimer] Simplified Chinese disclaimer is required.';
    if (!$nz($d['disclaimer']['zhHant'])) $errors[] = '[F · Disclaimer] Traditional Chinese disclaimer is required.';
    if (!$nz($d['disclaimer']['en']))     $errors[] = '[F · Disclaimer] English disclaimer is required.';

    return ['errors' => $errors, 'warnings' => $warnings];
}

// ── Request dispatch ──────────────────────────────────────────────────────────
$loginError       = '';
$formError        = '';
$isPreviewing     = false;
$previewJson      = null;
$validationResult = null;
$fd               = null;

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
    }
}

if (isLoggedIn() && $fd === null) {
    $fd = $jsonData;
}

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

function fv(array $arr, string ...$keys): string {
    return esc(sv($arr, ...$keys));
}

function sel(string $current, string $option): string {
    return $current === $option ? ' selected' : '';
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

<!-- ── Config missing ────────────────────────────────────────────────────── -->
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

<!-- ── Login ─────────────────────────────────────────────────────────────── -->
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

<!-- ── Admin shell ────────────────────────────────────────────────────────── -->
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

    <div class="stage-notice">
      <strong>Current Stage:</strong>
      Preview Changes validates and shows the JSON output only.
      Save / Publish to <code>/data/current-prices.json</code> will be added in a later patch.
    </div>

    <?php if ($formError !== ''): ?>
      <div class="form-error-banner"><?= esc($formError) ?></div>
    <?php endif; ?>

    <?php if ($fd === null): ?>
      <!-- JSON load failed, show error only -->
      <div class="dashboard-card error-card">
        <div class="card-header">JSON Read Error</div>
        <div class="card-body">
          <p class="error-msg"><?= esc($jsonError) ?></p>
        </div>
      </div>

    <?php else: ?>

    <!-- ── Edit Form ──────────────────────────────────────────────────────── -->
    <form method="POST" action="index.php" id="admin-form">
      <input type="hidden" name="action" value="preview">
      <input type="hidden" name="csrf_token" value="<?= esc($_SESSION['csrf_token']) ?>">

      <!-- ── A · Page Status ─────────────────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">A &nbsp;·&nbsp; Page Status</div>
        <div class="form-section-body">
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

      <!-- ── B · Announcement ───────────────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">B &nbsp;·&nbsp; Announcement</div>
        <div class="form-section-body">
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

      <!-- ── C · Contacts ───────────────────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">C &nbsp;·&nbsp; Contacts</div>
        <div class="form-section-body">
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

      <!-- ── D · Price Rows ─────────────────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          D &nbsp;·&nbsp; Price Rows
          <span class="row-count" id="price-row-count"></span>
        </div>
        <div class="form-section-body">
          <div id="price-rows-container">
            <?php foreach (($fd['prices'] ?? []) as $i => $row): ?>
            <div class="data-row" id="price-row-<?= $i ?>">
              <div class="data-row-header">
                <span class="data-row-label">Row <?= $i + 1 ?></span>
                <button type="button" class="btn-delete-row" onclick="deleteRow('price-row-<?= $i ?>', 'price-row-count')">Remove</button>
              </div>
              <div class="data-row-body">
                <div class="row-fields dual">
                  <div class="fg">
                    <label>id</label>
                    <input type="text" name="prices[<?= $i ?>][id]" value="<?= fv($row, 'id') ?>">
                  </div>
                  <div class="fg">
                    <label>status</label>
                    <input type="text" name="prices[<?= $i ?>][status]" value="<?= fv($row, 'status') ?>" placeholder="available / unavailable">
                  </div>
                </div>
                <div class="row-subheader">Size</div>
                <div class="row-fields triple">
                  <div class="fg">
                    <label>zhHans</label>
                    <input type="text" name="prices[<?= $i ?>][size][zhHans]" value="<?= fv($row, 'size', 'zhHans') ?>">
                  </div>
                  <div class="fg">
                    <label>zhHant</label>
                    <input type="text" name="prices[<?= $i ?>][size][zhHant]" value="<?= fv($row, 'size', 'zhHant') ?>">
                  </div>
                  <div class="fg">
                    <label>en</label>
                    <input type="text" name="prices[<?= $i ?>][size][en]" value="<?= fv($row, 'size', 'en') ?>">
                  </div>
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
                <div class="row-subheader">Notes</div>
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

      <!-- ── E · CNF Add-ons ────────────────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">
          E &nbsp;·&nbsp; CNF Add-ons
          <span class="row-count" id="cnf-row-count"></span>
        </div>
        <div class="form-section-body">
          <div id="cnf-rows-container">
            <?php foreach (($fd['cnfAddOns'] ?? []) as $i => $row): ?>
            <div class="data-row" id="cnf-row-<?= $i ?>">
              <div class="data-row-header">
                <span class="data-row-label">Row <?= $i + 1 ?></span>
                <button type="button" class="btn-delete-row" onclick="deleteRow('cnf-row-<?= $i ?>', 'cnf-row-count')">Remove</button>
              </div>
              <div class="data-row-body">
                <div class="row-fields dual">
                  <div class="fg">
                    <label>id</label>
                    <input type="text" name="cnf[<?= $i ?>][id]" value="<?= fv($row, 'id') ?>">
                  </div>
                  <div class="fg">
                    <label>addOn</label>
                    <input type="text" name="cnf[<?= $i ?>][addOn]" value="<?= fv($row, 'addOn') ?>">
                  </div>
                </div>
                <div class="row-subheader">Region</div>
                <div class="row-fields triple">
                  <div class="fg">
                    <label>zhHans</label>
                    <input type="text" name="cnf[<?= $i ?>][region][zhHans]" value="<?= fv($row, 'region', 'zhHans') ?>">
                  </div>
                  <div class="fg">
                    <label>zhHant</label>
                    <input type="text" name="cnf[<?= $i ?>][region][zhHant]" value="<?= fv($row, 'region', 'zhHant') ?>">
                  </div>
                  <div class="fg">
                    <label>en</label>
                    <input type="text" name="cnf[<?= $i ?>][region][en]" value="<?= fv($row, 'region', 'en') ?>">
                  </div>
                </div>
                <div class="row-subheader">Notes</div>
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

      <!-- ── F · Disclaimer ─────────────────────────────────────────────── -->
      <div class="form-section">
        <div class="form-section-header">F &nbsp;·&nbsp; Disclaimer</div>
        <div class="form-section-body">
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
        <button type="submit" class="btn-preview">Preview Changes</button>
        <span class="form-actions-note">Validates fields and shows the JSON output. Does not save or publish.</span>
      </div>

      <!-- ── G · Validation & JSON Preview ─────────────────────────────── -->
      <div class="form-section" id="section-g">
        <div class="form-section-header">G &nbsp;·&nbsp; Validation &amp; JSON Preview</div>
        <div class="form-section-body">

          <?php if (!$isPreviewing): ?>
            <p class="vp-idle">Click <strong>Preview Changes</strong> to validate fields and see the JSON output.</p>

          <?php else: ?>

            <?php
              $errors   = $validationResult['errors']   ?? [];
              $warnings = $validationResult['warnings'] ?? [];
            ?>

            <?php if (empty($errors) && empty($warnings)): ?>
              <div class="vp-pass">&#10003; No errors or warnings. Ready to publish when Save / Publish is available.</div>
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

            <div class="vp-block-label" style="margin-top:20px;">JSON Preview</div>
            <p class="vp-preview-note">This is what <code>current-prices.json</code> would contain. No file has been written.</p>
            <textarea class="json-preview-area" readonly><?= esc($previewJson ?? '') ?></textarea>

          <?php endif; ?>

        </div>
      </div>

    </form>

    <?php endif; ?>

  </main>
</div>

<?php endif; ?>

<script>
// ── Row counts ───────────────────────────────────────────────────────────────
let priceRowCount = <?= count($fd['prices']   ?? []) ?>;
let cnfRowCount   = <?= count($fd['cnfAddOns'] ?? []) ?>;

function updateCount(badgeId, containerId) {
  const badge = document.getElementById(badgeId);
  if (!badge) return;
  const n = document.querySelectorAll('#' + containerId + ' .data-row').length;
  badge.textContent = n + (n === 1 ? ' row' : ' rows');
}

function deleteRow(rowId, badgeId) {
  const el = document.getElementById(rowId);
  if (el) el.remove();
  const container = rowId.startsWith('price') ? 'price-rows-container' : 'cnf-rows-container';
  updateCount(badgeId, container);
}

// ── Add Price Row ────────────────────────────────────────────────────────────
function addPriceRow() {
  const idx = priceRowCount++;
  const container = document.getElementById('price-rows-container');
  const n = container.querySelectorAll('.data-row').length + 1;
  const div = document.createElement('div');
  div.className = 'data-row';
  div.id = 'price-row-' + idx;
  div.innerHTML =
    '<div class="data-row-header">' +
      '<span class="data-row-label">Row ' + n + '</span>' +
      '<button type="button" class="btn-delete-row" onclick="deleteRow(\'price-row-' + idx + '\', \'price-row-count\')">Remove</button>' +
    '</div>' +
    '<div class="data-row-body">' +
      '<div class="row-fields dual">' +
        '<div class="fg"><label>id</label><input type="text" name="prices[' + idx + '][id]" value=""></div>' +
        '<div class="fg"><label>status</label><input type="text" name="prices[' + idx + '][status]" value="" placeholder="available / unavailable"></div>' +
      '</div>' +
      '<div class="row-subheader">Size</div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>zhHans</label><input type="text" name="prices[' + idx + '][size][zhHans]" value=""></div>' +
        '<div class="fg"><label>zhHant</label><input type="text" name="prices[' + idx + '][size][zhHant]" value=""></div>' +
        '<div class="fg"><label>en</label><input type="text" name="prices[' + idx + '][size][en]" value=""></div>' +
      '</div>' +
      '<div class="row-subheader">Prices</div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>cadPerLb</label><input type="text" name="prices[' + idx + '][cadPerLb]" value=""></div>' +
        '<div class="fg"><label>usdPerLb</label><input type="text" name="prices[' + idx + '][usdPerLb]" value=""></div>' +
        '<div class="fg"><label>rmbPerKg</label><input type="text" name="prices[' + idx + '][rmbPerKg]" value=""></div>' +
      '</div>' +
      '<div class="row-subheader">Notes</div>' +
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
  const idx = cnfRowCount++;
  const container = document.getElementById('cnf-rows-container');
  const n = container.querySelectorAll('.data-row').length + 1;
  const div = document.createElement('div');
  div.className = 'data-row';
  div.id = 'cnf-row-' + idx;
  div.innerHTML =
    '<div class="data-row-header">' +
      '<span class="data-row-label">Row ' + n + '</span>' +
      '<button type="button" class="btn-delete-row" onclick="deleteRow(\'cnf-row-' + idx + '\', \'cnf-row-count\')">Remove</button>' +
    '</div>' +
    '<div class="data-row-body">' +
      '<div class="row-fields dual">' +
        '<div class="fg"><label>id</label><input type="text" name="cnf[' + idx + '][id]" value=""></div>' +
        '<div class="fg"><label>addOn</label><input type="text" name="cnf[' + idx + '][addOn]" value=""></div>' +
      '</div>' +
      '<div class="row-subheader">Region</div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>zhHans</label><input type="text" name="cnf[' + idx + '][region][zhHans]" value=""></div>' +
        '<div class="fg"><label>zhHant</label><input type="text" name="cnf[' + idx + '][region][zhHant]" value=""></div>' +
        '<div class="fg"><label>en</label><input type="text" name="cnf[' + idx + '][region][en]" value=""></div>' +
      '</div>' +
      '<div class="row-subheader">Notes</div>' +
      '<div class="row-fields triple">' +
        '<div class="fg"><label>zhHans</label><textarea name="cnf[' + idx + '][notes][zhHans]"></textarea></div>' +
        '<div class="fg"><label>zhHant</label><textarea name="cnf[' + idx + '][notes][zhHant]"></textarea></div>' +
        '<div class="fg"><label>en</label><textarea name="cnf[' + idx + '][notes][en]"></textarea></div>' +
      '</div>' +
    '</div>';
  container.appendChild(div);
  updateCount('cnf-row-count', 'cnf-rows-container');
}

// ── Init row counts + scroll to G after preview ──────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  updateCount('price-row-count', 'price-rows-container');
  updateCount('cnf-row-count', 'cnf-rows-container');
  <?php if ($isPreviewing): ?>
  const g = document.getElementById('section-g');
  if (g) g.scrollIntoView({ behavior: 'smooth', block: 'start' });
  <?php endif; ?>
});
</script>

</body>
</html>
