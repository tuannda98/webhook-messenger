<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Admin\AdminGuard;
use App\Admin\StatsService;
use App\Config\Config;
use App\Service\Database;
use App\Service\SystemConfig;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$db    = Database::connection();
$guard = new AdminGuard($db);
$guard->protect(); // HTTPS + IP whitelist + security headers

session_start();

// ─── Auth ────────────────────────────────────────────────────────────────────

$adminToken = Config::string('ADMIN_TOKEN');
$loginError = null;

if ($adminToken === '') {
    http_response_code(500);
    die('Server misconfiguration: ADMIN_TOKEN is not set.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $rateLimitError = $guard->checkLoginRateLimit();

    if ($rateLimitError !== null) {
        $loginError = $rateLimitError;
    } elseif ($adminToken && hash_equals($adminToken, $_POST['token'] ?? '')) {
        $guard->clearAttempts();
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
    } else {
        $guard->recordFailedAttempt();
        $loginError = 'Sai token.';
    }
}

if (($_POST['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: admin');
    exit;
}

if (empty($_SESSION['admin'])) {
    renderLogin($loginError);
    exit;
}

// ─── Authenticated ────────────────────────────────────────────────────────────

$sysConfig  = new SystemConfig($db);
$stats      = new StatsService($db);
$page       = $_GET['page'] ?? 'dashboard';
$flash      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_config') {
        $key   = trim($_POST['key'] ?? '');
        $value = $_POST['value'] ?? '';
        if ($key !== '') {
            $sysConfig->set($key, $value);
            $flash = "Đã lưu <strong>" . htmlspecialchars($key) . "</strong>.";
        }
        $page = 'config';
    }

    if ($action === 'add_config') {
        $key   = trim($_POST['new_key'] ?? '');
        $value = $_POST['new_value'] ?? '';
        if ($key !== '') {
            $sysConfig->set($key, $value);
            $flash = "Đã thêm <strong>" . htmlspecialchars($key) . "</strong>.";
        }
        $page = 'config';
    }
}

// ─── Render ───────────────────────────────────────────────────────────────────

$overview      = $page === 'dashboard' ? $stats->overview()              : [];
$recentSess    = $page === 'dashboard' ? $stats->recentSessions(15)      : [];
$chartData     = $page === 'dashboard' ? $stats->dailySessionsChart(14)  : [];
$topUsers      = $page === 'dashboard' ? $stats->topUsers(10)            : [];
$allConfig     = $page === 'config'    ? $db->query('SELECT `key`, `value`, updated_at FROM system_config ORDER BY `key`')->fetchAll() : [];

ob_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Panel</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg: #f0f2f5; --sidebar: #1a1d23; --sidebar-text: #a0aec0;
    --primary: #4f46e5; --primary-hover: #4338ca;
    --white: #fff; --border: #e2e8f0;
    --text: #1a202c; --text-muted: #718096;
    --green: #10b981; --yellow: #f59e0b; --red: #ef4444; --blue: #3b82f6;
}
body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

/* Sidebar */
.sidebar { width: 220px; background: var(--sidebar); display: flex; flex-direction: column; padding: 0; flex-shrink: 0; }
.sidebar-brand { padding: 20px 24px; font-size: 16px; font-weight: 700; color: #fff; border-bottom: 1px solid #2d3748; }
.sidebar-brand span { color: var(--primary); }
.sidebar-nav { padding: 12px 0; flex: 1; }
.sidebar-nav a { display: flex; align-items: center; gap: 10px; padding: 10px 24px; color: var(--sidebar-text); text-decoration: none; font-size: 14px; transition: all .15s; }
.sidebar-nav a:hover, .sidebar-nav a.active { color: #fff; background: #2d3748; }
.sidebar-nav a.active { border-left: 3px solid var(--primary); }
.sidebar-nav .icon { width: 18px; text-align: center; }
.sidebar-footer { padding: 16px 24px; border-top: 1px solid #2d3748; }
.sidebar-footer form button { background: none; border: none; color: var(--sidebar-text); cursor: pointer; font-size: 13px; padding: 0; display: flex; align-items: center; gap: 8px; }
.sidebar-footer form button:hover { color: #fff; }

/* Main */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.topbar { background: var(--white); border-bottom: 1px solid var(--border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; }
.topbar-title { font-size: 18px; font-weight: 600; }
.content { padding: 28px; flex: 1; overflow-y: auto; }

/* Flash */
.flash { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 10px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }

/* Cards */
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
.card { background: var(--white); border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.card-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
.card-value { font-size: 28px; font-weight: 700; }
.card-value.green { color: var(--green); }
.card-value.yellow { color: var(--yellow); }
.card-value.blue { color: var(--blue); }
.card-value.purple { color: var(--primary); }

/* Chart */
.chart-wrap { background: var(--white); border-radius: 12px; padding: 20px; margin-bottom: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.chart-title { font-size: 14px; font-weight: 600; margin-bottom: 16px; }
.bar-chart { display: flex; align-items: flex-end; gap: 6px; height: 100px; }
.bar-chart .bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; height: 100%; justify-content: flex-end; }
.bar-chart .bar { width: 100%; background: var(--primary); border-radius: 4px 4px 0 0; min-height: 2px; transition: opacity .2s; }
.bar-chart .bar:hover { opacity: .75; }
.bar-chart .bar-label { font-size: 9px; color: var(--text-muted); white-space: nowrap; }

/* Tables */
.section { background: var(--white); border-radius: 12px; padding: 20px; margin-bottom: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
.section-title { font-size: 14px; font-weight: 600; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th { text-align: left; padding: 8px 12px; color: var(--text-muted); font-weight: 600; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid var(--border); }
tbody td { padding: 10px 12px; border-bottom: 1px solid var(--border); }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #f7fafc; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-gray { background: #f1f5f9; color: var(--text-muted); }

/* Config table */
.config-value-input { width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; font-size: 13px; font-family: monospace; resize: vertical; min-height: 36px; }
.config-value-input:focus { outline: none; border-color: var(--primary); }
.btn { padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; font-size: 13px; font-weight: 500; transition: background .15s; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-hover); }
.btn-sm { padding: 4px 10px; font-size: 12px; }
.add-row td { padding-top: 16px; border-top: 2px dashed var(--border); }
.add-row input { width: 100%; border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; font-size: 13px; }
.add-row input:focus { outline: none; border-color: var(--primary); }

/* Rows grid for 2 tables side by side */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
@media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">💬 <span>Chat</span>Admin</div>
    <nav class="sidebar-nav">
        <a href="admin?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
            <span class="icon">📊</span> Dashboard
        </a>
        <a href="admin?page=config" class="<?= $page === 'config' ? 'active' : '' ?>">
            <span class="icon">⚙️</span> Cấu hình
        </a>
    </nav>
    <div class="sidebar-footer">
        <form method="POST"><input type="hidden" name="action" value="logout">
            <button type="submit">🚪 Đăng xuất</button>
        </form>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div class="topbar-title"><?= $page === 'dashboard' ? 'Dashboard' : 'Cấu hình hệ thống' ?></div>
        <div style="font-size:13px;color:var(--text-muted)"><?= date('d/m/Y H:i') ?></div>
    </div>
    <div class="content">

    <?php if ($flash): ?>
        <div class="flash"><?= $flash ?></div>
    <?php endif; ?>

    <?php if ($page === 'dashboard'): ?>

        <!-- Overview cards -->
        <div class="cards">
            <div class="card">
                <div class="card-label">Tổng users</div>
                <div class="card-value blue"><?= number_format((int)$overview['total_users']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Users hôm nay</div>
                <div class="card-value green"><?= number_format((int)$overview['users_today']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Đang chờ ghép</div>
                <div class="card-value yellow"><?= number_format((int)$overview['waiting']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Cặp đang chat</div>
                <div class="card-value purple"><?= number_format((int)$overview['chatting_pairs']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Sessions hôm nay</div>
                <div class="card-value green"><?= number_format((int)$overview['sessions_today']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Sessions 7 ngày</div>
                <div class="card-value blue"><?= number_format((int)$overview['sessions_7d']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Sessions 30 ngày</div>
                <div class="card-value blue"><?= number_format((int)$overview['sessions_30d']) ?></div>
            </div>
            <div class="card">
                <div class="card-label">Avg thời gian chat</div>
                <div class="card-value"><?= $overview['avg_duration_min'] ?> <span style="font-size:14px;color:var(--text-muted)">phút</span></div>
            </div>
        </div>

        <!-- Bar chart -->
        <?php if ($chartData): ?>
        <?php
            $maxCount = max(array_column($chartData, 'count') ?: [1]);
            $chartMap = array_column($chartData, 'count', 'date');
        ?>
        <div class="chart-wrap">
            <div class="chart-title">Sessions 14 ngày qua</div>
            <div class="bar-chart">
            <?php for ($i = 13; $i >= 0; $i--): ?>
                <?php
                    $d     = date('Y-m-d', strtotime("-{$i} days"));
                    $count = (int)($chartMap[$d] ?? 0);
                    $pct   = $maxCount > 0 ? max(2, round($count / $maxCount * 100)) : 2;
                    $label = date('d/m', strtotime($d));
                ?>
                <div class="bar-wrap" title="<?= $label ?>: <?= $count ?>">
                    <div class="bar" style="height:<?= $pct ?>%"></div>
                    <div class="bar-label"><?= $label ?></div>
                </div>
            <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="two-col">
            <!-- Recent sessions -->
            <div class="section">
                <div class="section-title">Sessions gần đây</div>
                <table>
                    <thead><tr>
                        <th>#</th><th>User 1</th><th>User 2</th><th>Bắt đầu</th><th>Thời gian</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recentSess as $s): ?>
                    <tr>
                        <td style="color:var(--text-muted)"><?= $s['id'] ?></td>
                        <td><?= htmlspecialchars($s['name1'] ?: $s['psid1']) ?></td>
                        <td><?= htmlspecialchars($s['name2'] ?: $s['psid2']) ?></td>
                        <td style="color:var(--text-muted);white-space:nowrap"><?= date('d/m H:i', strtotime($s['started_at'])) ?></td>
                        <td>
                            <?php if ($s['duration_sec'] !== null): ?>
                                <?= gmdate($s['duration_sec'] >= 3600 ? 'H:i:s' : 'i:s', (int)$s['duration_sec']) ?>
                            <?php else: ?>
                                <span class="badge badge-green">đang chat</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Top users -->
            <div class="section">
                <div class="section-title">Top users</div>
                <table>
                    <thead><tr><th>Tên</th><th>Sessions</th><th>Điểm</th></tr></thead>
                    <tbody>
                    <?php foreach ($topUsers as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['name'] ?: $u['psid']) ?></td>
                        <td><?= $u['session_count'] ?></td>
                        <td><?= number_format((int)$u['points']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($page === 'config'): ?>

        <div class="section">
            <div class="section-title">
                Cấu hình hệ thống
                <span style="font-size:12px;color:var(--text-muted)">Thay đổi có hiệu lực ngay lập tức</span>
            </div>
            <?php foreach ($allConfig as $row):
                $fid = 'cfg-' . md5($row['key']); ?>
            <form id="<?= $fid ?>" method="POST">
                <input type="hidden" name="action" value="update_config">
                <input type="hidden" name="key" value="<?= htmlspecialchars($row['key']) ?>">
            </form>
            <?php endforeach; ?>
            <form id="cfg-add" method="POST">
                <input type="hidden" name="action" value="add_config">
            </form>
            <table>
                <thead><tr>
                    <th style="width:220px">Key</th>
                    <th>Value</th>
                    <th style="width:140px">Cập nhật lúc</th>
                    <th style="width:70px"></th>
                </tr></thead>
                <tbody>
                <?php foreach ($allConfig as $row):
                    $fid = 'cfg-' . md5($row['key']); ?>
                <tr>
                    <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($row['key']) ?></td>
                    <td>
                        <textarea name="value" form="<?= $fid ?>" class="config-value-input"
                            rows="<?= substr_count($row['value'], "\n") + 1 ?>"><?= htmlspecialchars($row['value']) ?></textarea>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;white-space:nowrap">
                        <?= $row['updated_at'] ? date('d/m/Y H:i', strtotime($row['updated_at'])) : '—' ?>
                    </td>
                    <td>
                        <button type="submit" form="<?= $fid ?>" class="btn btn-primary btn-sm">Lưu</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <!-- Add new key -->
                <tr class="add-row">
                    <td><input type="text" name="new_key" form="cfg-add" placeholder="key_mới" required></td>
                    <td><input type="text" name="new_value" form="cfg-add" placeholder="giá trị"></td>
                    <td></td>
                    <td><button type="submit" form="cfg-add" class="btn btn-primary btn-sm">Thêm</button></td>
                </tr>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    </div><!-- .content -->
</div><!-- .main -->

</body>
</html>
<?php
echo ob_get_clean();

// ─── Login page ───────────────────────────────────────────────────────────────

function renderLogin(?string $error): void
{
    ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.box { background: #fff; border-radius: 16px; padding: 40px; width: 360px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
.sub { color: #718096; font-size: 14px; margin-bottom: 28px; }
label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
input[type=password] { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; }
input[type=password]:focus { border-color: #4f46e5; }
button { width: 100%; margin-top: 16px; padding: 11px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; }
button:hover { background: #4338ca; }
.error { margin-top: 12px; color: #ef4444; font-size: 13px; }
</style>
</head>
<body>
<div class="box">
    <h1>💬 Admin Panel</h1>
    <p class="sub">Nhập token để đăng nhập</p>
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <label for="token">Admin Token</label>
        <input type="password" id="token" name="token" placeholder="••••••••" autofocus required>
        <button type="submit">Đăng nhập</button>
    </form>
    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</div>
</body>
</html>
    <?php
}
