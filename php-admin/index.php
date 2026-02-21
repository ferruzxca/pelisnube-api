<?php

declare(strict_types=1);

use Admin\ApiClient;
use Admin\Env;

require_once __DIR__ . '/src/bootstrap.php';

$client = new ApiClient(Env::get('API_BASE_URL', 'http://localhost:8080/api/v1'));

if (isset($_GET['lang'])) {
    $lang = strtolower((string) $_GET['lang']);
    if (in_array($lang, ['es', 'en'], true)) {
        $_SESSION['lang'] = $lang;
    }
}

$lang = app_lang();
$page = $_GET['page'] ?? 'dashboard';
$token = $_SESSION['token'] ?? null;
$user = $_SESSION['user'] ?? null;

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

/** @param array<string,mixed> $data */
function post_bool(array $data, string $key): bool
{
    return isset($data[$key]) && in_array((string) $data[$key], ['1', 'on', 'true', 'yes'], true);
}

/** @return array<int,string> */
function csv_ids(string $value): array
{
    $parts = array_map('trim', explode(',', $value));
    return array_values(array_filter($parts, static fn(string $item): bool => $item !== ''));
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function clean_payload(array $payload): array
{
    return array_filter(
        $payload,
        static fn(mixed $value): bool => $value !== '' && $value !== null && $value !== []
    );
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** @return array<string,string>|null */
function pull_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $result = $client->request('POST', 'auth/login', [
            'email' => (string) ($_POST['email'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
        ], null, $lang);

        if (($result['success'] ?? false) === true) {
            $loggedUser = $result['data']['user'] ?? [];
            if (!in_array($loggedUser['role'] ?? '', ['ADMIN', 'SUPER_ADMIN'], true)) {
                set_flash('danger', $lang === 'en' ? 'Access denied: admin role required.' : 'Acceso denegado: se requiere rol admin.');
            } else {
                $_SESSION['token'] = $result['data']['token'] ?? null;
                $_SESSION['user'] = $loggedUser;
                set_flash('success', (string) ($result['message'] ?? 'OK'));
            }
        } else {
            $message = (string) ($result['message'] ?? 'Login failed');
            if (Env::bool('APP_DEBUG', false) && isset($result['errors']) && is_array($result['errors'])) {
                $url = trim((string) ($result['errors']['url'] ?? ''));
                $rawSnippet = trim((string) ($result['errors']['raw_snippet'] ?? ''));
                if ($url !== '') {
                    $message .= ' URL: ' . $url;
                }
                if ($rawSnippet !== '') {
                    $message .= ' RAW: ' . $rawSnippet;
                }
            }
            set_flash('danger', $message);
        }

        header('Location: index.php');
        exit;
    }

    if ($token) {
        $redirectPage = $_POST['redirect_page'] ?? 'dashboard';

        switch ($action) {
            case 'content_create':
                $payload = [
                    'title' => (string) ($_POST['title'] ?? ''),
                    'type' => (string) ($_POST['type'] ?? 'MOVIE'),
                    'synopsis' => (string) ($_POST['synopsis'] ?? ''),
                    'year' => (int) ($_POST['year'] ?? 0),
                    'duration' => (int) ($_POST['duration'] ?? 0),
                    'rating' => (float) ($_POST['rating'] ?? 0),
                    'trailerWatchUrl' => (string) ($_POST['trailer_watch_url'] ?? ''),
                    'posterUrl' => (string) ($_POST['poster_url'] ?? ''),
                    'bannerUrl' => (string) ($_POST['banner_url'] ?? ''),
                    'sectionIds' => csv_ids((string) ($_POST['section_ids'] ?? '')),
                ];
                $result = $client->request('POST', 'admin/contents', $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'content_update':
                $id = (string) ($_POST['id'] ?? '');
                $payload = clean_payload([
                    'title' => trim((string) ($_POST['title'] ?? '')),
                    'type' => trim((string) ($_POST['type'] ?? '')),
                    'synopsis' => trim((string) ($_POST['synopsis'] ?? '')),
                    'year' => ($_POST['year'] ?? '') !== '' ? (int) $_POST['year'] : null,
                    'duration' => ($_POST['duration'] ?? '') !== '' ? (int) $_POST['duration'] : null,
                    'rating' => ($_POST['rating'] ?? '') !== '' ? (float) $_POST['rating'] : null,
                    'trailerWatchUrl' => trim((string) ($_POST['trailer_watch_url'] ?? '')),
                    'posterUrl' => trim((string) ($_POST['poster_url'] ?? '')),
                    'bannerUrl' => trim((string) ($_POST['banner_url'] ?? '')),
                    'sectionIds' => csv_ids((string) ($_POST['section_ids'] ?? '')),
                ]);
                if (isset($_POST['is_active'])) {
                    $payload['isActive'] = post_bool($_POST, 'is_active');
                }
                $result = $client->request('PATCH', 'admin/contents/' . rawurlencode($id), $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'content_inactivate':
                $id = (string) ($_POST['id'] ?? '');
                $result = $client->request('PATCH', 'admin/contents/' . rawurlencode($id) . '/inactive', [], $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'section_create':
                $payload = [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'key' => (string) ($_POST['key'] ?? ''),
                    'description' => (string) ($_POST['description'] ?? ''),
                    'sortOrder' => (int) ($_POST['sort_order'] ?? 0),
                    'isHomeVisible' => post_bool($_POST, 'is_home_visible'),
                    'contentIds' => csv_ids((string) ($_POST['content_ids'] ?? '')),
                ];
                $result = $client->request('POST', 'admin/sections', $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'section_update':
                $id = (string) ($_POST['id'] ?? '');
                $payload = clean_payload([
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'key' => trim((string) ($_POST['key'] ?? '')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'sortOrder' => ($_POST['sort_order'] ?? '') !== '' ? (int) $_POST['sort_order'] : null,
                    'contentIds' => csv_ids((string) ($_POST['content_ids'] ?? '')),
                ]);
                if (isset($_POST['is_home_visible'])) {
                    $payload['isHomeVisible'] = post_bool($_POST, 'is_home_visible');
                }
                if (isset($_POST['is_active'])) {
                    $payload['isActive'] = post_bool($_POST, 'is_active');
                }
                $result = $client->request('PATCH', 'admin/sections/' . rawurlencode($id), $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'section_inactivate':
                $id = (string) ($_POST['id'] ?? '');
                $result = $client->request('PATCH', 'admin/sections/' . rawurlencode($id) . '/inactive', [], $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'user_create':
                $payload = [
                    'name' => (string) ($_POST['name'] ?? ''),
                    'email' => (string) ($_POST['email'] ?? ''),
                    'password' => (string) ($_POST['password'] ?? ''),
                    'role' => (string) ($_POST['role'] ?? 'USER'),
                    'status' => (string) ($_POST['status'] ?? 'ACTIVE'),
                    'preferredLang' => (string) ($_POST['preferred_lang'] ?? 'es'),
                    'mustChangePassword' => post_bool($_POST, 'must_change_password'),
                ];
                $result = $client->request('POST', 'admin/users', $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'user_update':
                $id = (string) ($_POST['id'] ?? '');
                $payload = clean_payload([
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'role' => trim((string) ($_POST['role'] ?? '')),
                    'status' => trim((string) ($_POST['status'] ?? '')),
                    'preferredLang' => trim((string) ($_POST['preferred_lang'] ?? '')),
                ]);
                if (isset($_POST['must_change_password'])) {
                    $payload['mustChangePassword'] = post_bool($_POST, 'must_change_password');
                }
                if (isset($_POST['is_active'])) {
                    $payload['isActive'] = post_bool($_POST, 'is_active');
                }
                $result = $client->request('PATCH', 'admin/users/' . rawurlencode($id), $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'user_reset_password':
                $id = (string) ($_POST['id'] ?? '');
                $newPassword = (string) ($_POST['new_password'] ?? '');
                $payload = $newPassword !== '' ? ['newPassword' => $newPassword] : [];
                $result = $client->request('POST', 'admin/users/' . rawurlencode($id) . '/password-reset', $payload, $token, $lang);
                $message = (string) ($result['message'] ?? '');
                if (($result['success'] ?? false) && isset($result['data']['temporaryPassword'])) {
                    $message .= ' Temp: ' . $result['data']['temporaryPassword'];
                }
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', $message);
                break;

            case 'user_inactivate':
                $id = (string) ($_POST['id'] ?? '');
                $result = $client->request('PATCH', 'admin/users/' . rawurlencode($id) . '/inactive', [], $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'plan_create':
                $payload = [
                    'code' => (string) ($_POST['code'] ?? ''),
                    'name' => (string) ($_POST['name'] ?? ''),
                    'priceMonthly' => (float) ($_POST['price_monthly'] ?? 0),
                    'currency' => (string) ($_POST['currency'] ?? 'MXN'),
                    'quality' => (string) ($_POST['quality'] ?? 'HD'),
                    'screens' => (int) ($_POST['screens'] ?? 1),
                ];
                $result = $client->request('POST', 'admin/subscription-plans', $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'plan_update':
                $id = (string) ($_POST['id'] ?? '');
                $payload = clean_payload([
                    'code' => trim((string) ($_POST['code'] ?? '')),
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'priceMonthly' => ($_POST['price_monthly'] ?? '') !== '' ? (float) $_POST['price_monthly'] : null,
                    'currency' => trim((string) ($_POST['currency'] ?? '')),
                    'quality' => trim((string) ($_POST['quality'] ?? '')),
                    'screens' => ($_POST['screens'] ?? '') !== '' ? (int) $_POST['screens'] : null,
                ]);
                if (isset($_POST['is_active'])) {
                    $payload['isActive'] = post_bool($_POST, 'is_active');
                }
                $result = $client->request('PATCH', 'admin/subscription-plans/' . rawurlencode($id), $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'plan_inactivate':
                $id = (string) ($_POST['id'] ?? '');
                $result = $client->request('PATCH', 'admin/subscription-plans/' . rawurlencode($id) . '/inactive', [], $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'subscription_update':
                $id = (string) ($_POST['id'] ?? '');
                $payload = clean_payload([
                    'status' => trim((string) ($_POST['status'] ?? '')),
                    'planCode' => trim((string) ($_POST['plan_code'] ?? '')),
                    'renewalAt' => trim((string) ($_POST['renewal_at'] ?? '')),
                    'endedAt' => trim((string) ($_POST['ended_at'] ?? '')),
                ]);
                if (isset($_POST['is_active'])) {
                    $payload['isActive'] = post_bool($_POST, 'is_active');
                }
                $result = $client->request('PATCH', 'admin/subscriptions/' . rawurlencode($id), $payload, $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;

            case 'subscription_inactivate':
                $id = (string) ($_POST['id'] ?? '');
                $result = $client->request('PATCH', 'admin/subscriptions/' . rawurlencode($id) . '/inactive', [], $token, $lang);
                set_flash(($result['success'] ?? false) ? 'success' : 'danger', (string) ($result['message'] ?? ''));
                break;
        }

        header('Location: index.php?page=' . urlencode((string) $redirectPage));
        exit;
    }
}

if (!$token) {
    $flash = pull_flash();
    ?>
    <!DOCTYPE html>
    <html lang="<?= h($lang) ?>">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?= h(Env::get('APP_NAME', 'PelisNube Admin')) ?></title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <style>
        :root {
          --bg: #f4f1ea;
          --card: #ffffff;
          --ink: #1c2431;
          --accent: #0b6b6f;
          --accent-soft: #d8f0f1;
        }
        body { font-family: 'Sora', sans-serif; background: radial-gradient(circle at top right, #fff5d8, #f4f1ea 45%, #e8ecf8); min-height: 100vh; color: var(--ink); }
        .login-card { max-width: 460px; margin: 8vh auto; border: 0; border-radius: 24px; box-shadow: 0 18px 48px rgba(15, 27, 55, 0.12); }
        .badge-brand { background: var(--accent-soft); color: var(--accent); }
      </style>
    </head>
    <body>
      <div class="container py-5">
        <div class="card login-card">
          <div class="card-body p-4 p-md-5">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="badge rounded-pill badge-brand px-3 py-2">PHP Admin</span>
              <div class="btn-group btn-group-sm">
                <a class="btn btn-outline-secondary" href="?lang=es">ES</a>
                <a class="btn btn-outline-secondary" href="?lang=en">EN</a>
              </div>
            </div>
            <h1 class="h4 mb-1"><?= h(Env::get('APP_NAME', 'PelisNube Admin')) ?></h1>
            <p class="text-secondary mb-4"><?= $lang === 'en' ? 'Admin login with JWT Bearer against API' : 'Login de administrador con JWT Bearer contra la API' ?></p>

            <?php if ($flash): ?>
              <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="action" value="login">
              <div class="mb-3">
                <label class="form-label"><?= h(t_admin('email')) ?></label>
                <input name="email" type="email" class="form-control" required>
              </div>
              <div class="mb-4">
                <label class="form-label"><?= h(t_admin('password')) ?></label>
                <input name="password" type="password" class="form-control" required>
              </div>
              <button class="btn btn-dark w-100"><?= h(t_admin('login')) ?></button>
            </form>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$flash = pull_flash();

$kpis = [];
$chartUsers = [];
$chartSubscriptions = [];
$chartPayments = [];
$chartTopFavorites = [];
$contents = [];
$sections = [];
$users = [];
$plans = [];
$subscriptions = [];
$auditLogs = [];
$dashboardHealth = [];
$dashboardRequestState = [
    'health' => false,
    'kpis' => false,
    'users' => false,
    'subscriptions' => false,
    'payments' => false,
    'favorites' => false,
];
$dashboardRoutes = [
    ['method' => 'GET', 'path' => '/api/v1/health'],
    ['method' => 'GET', 'path' => '/api/v1/catalog'],
    ['method' => 'POST', 'path' => '/api/v1/auth/login'],
    ['method' => 'GET', 'path' => '/api/v1/auth/me'],
    ['method' => 'GET', 'path' => '/api/v1/favorites'],
    ['method' => 'GET', 'path' => '/api/v1/subscription/me'],
    ['method' => 'GET', 'path' => '/api/v1/admin/dashboard/kpis'],
    ['method' => 'GET', 'path' => '/api/v1/admin/dashboard/charts/users-monthly'],
    ['method' => 'GET', 'path' => '/api/v1/admin/dashboard/charts/subscriptions-status'],
    ['method' => 'GET', 'path' => '/api/v1/admin/dashboard/charts/payments-monthly'],
    ['method' => 'GET', 'path' => '/api/v1/admin/dashboard/charts/top-content-favorites'],
    ['method' => 'GET', 'path' => '/api/v1/admin/audit-logs'],
];
$dashboardMethodTotals = [];
foreach ($dashboardRoutes as $routeItem) {
    $methodKey = (string) $routeItem['method'];
    $dashboardMethodTotals[$methodKey] = ($dashboardMethodTotals[$methodKey] ?? 0) + 1;
}
$apiBaseUrl = rtrim(Env::get('API_BASE_URL', 'http://localhost:8080/api/v1'), '/');

if ($page === 'dashboard') {
    $healthRes = $client->request('GET', 'health', null, null, $lang);
    $kpisRes = $client->request('GET', 'admin/dashboard/kpis', null, $token, $lang);
    $usersRes = $client->request('GET', 'admin/dashboard/charts/users-monthly', null, $token, $lang);
    $subsRes = $client->request('GET', 'admin/dashboard/charts/subscriptions-status', null, $token, $lang);
    $paymentsRes = $client->request('GET', 'admin/dashboard/charts/payments-monthly', null, $token, $lang);
    $topsRes = $client->request('GET', 'admin/dashboard/charts/top-content-favorites', null, $token, $lang);

    $dashboardHealth = $healthRes['data'] ?? [];
    $dashboardRequestState = [
        'health' => ($healthRes['success'] ?? false) === true,
        'kpis' => ($kpisRes['success'] ?? false) === true,
        'users' => ($usersRes['success'] ?? false) === true,
        'subscriptions' => ($subsRes['success'] ?? false) === true,
        'payments' => ($paymentsRes['success'] ?? false) === true,
        'favorites' => ($topsRes['success'] ?? false) === true,
    ];
    $kpis = $kpisRes['data'] ?? [];
    $chartUsers = $usersRes['data']['items'] ?? [];
    $chartSubscriptions = $subsRes['data']['items'] ?? [];
    $chartPayments = $paymentsRes['data']['items'] ?? [];
    $chartTopFavorites = $topsRes['data']['items'] ?? [];
}

if ($page === 'contents') {
    $contentsRes = $client->request('GET', 'admin/contents?page=1&pageSize=100', null, $token, $lang);
    $sectionsRes = $client->request('GET', 'admin/sections?page=1&pageSize=100', null, $token, $lang);
    $contents = $contentsRes['data']['items'] ?? [];
    $sections = $sectionsRes['data']['items'] ?? [];
}

if ($page === 'sections') {
    $sectionsRes = $client->request('GET', 'admin/sections?page=1&pageSize=100', null, $token, $lang);
    $contentsRes = $client->request('GET', 'admin/contents?page=1&pageSize=100', null, $token, $lang);
    $sections = $sectionsRes['data']['items'] ?? [];
    $contents = $contentsRes['data']['items'] ?? [];
}

if ($page === 'users') {
    $usersRes = $client->request('GET', 'admin/users?page=1&pageSize=100', null, $token, $lang);
    $users = $usersRes['data']['items'] ?? [];
}

if ($page === 'plans') {
    $plansRes = $client->request('GET', 'admin/subscription-plans?page=1&pageSize=100', null, $token, $lang);
    $plans = $plansRes['data']['items'] ?? [];
}

if ($page === 'subscriptions') {
    $subsRes = $client->request('GET', 'admin/subscriptions?page=1&pageSize=100', null, $token, $lang);
    $subscriptions = $subsRes['data']['items'] ?? [];
}

if ($page === 'audit') {
    $auditRes = $client->request('GET', 'admin/audit-logs?page=1&pageSize=100', null, $token, $lang);
    $auditLogs = $auditRes['data']['items'] ?? [];
}

?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h(Env::get('APP_NAME', 'PelisNube Admin')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
  <style>
    :root {
      --bg-a: #f2e8ff;
      --bg-b: #ffe3f1;
      --bg-c: #e4edff;
      --paper: #ffffff;
      --ink: #231c3d;
      --muted: #716999;
      --line: #e8e2f5;
      --purple: #6d28d9;
      --pink: #ec4899;
      --blue: #2563eb;
      --purple-soft: rgba(109, 40, 217, 0.14);
      --pink-soft: rgba(236, 72, 153, 0.14);
      --blue-soft: rgba(37, 99, 235, 0.14);
    }
    body { font-family: 'Sora', sans-serif; color: var(--ink); background: linear-gradient(140deg, var(--bg-a) 0%, var(--bg-b) 45%, var(--bg-c) 100%); min-height: 100vh; }
    .shell { min-height: 100vh; }
    .sidebar { background: linear-gradient(210deg, #24124a, #4b1d95 45%, #8e2d92); color: #fff; min-height: 100vh; }
    .sidebar a { color: rgba(255, 255, 255, 0.82); text-decoration: none; border-radius: 10px; padding: 8px 10px; }
    .sidebar a.active, .sidebar a:hover { color: #fff; background: rgba(255, 255, 255, 0.14); }
    .card-soft { background: var(--paper); border: 1px solid var(--line); border-radius: 18px; box-shadow: 0 14px 34px rgba(39, 26, 71, 0.08); }
    .kpi-card { overflow: hidden; position: relative; }
    .kpi-card::after { content: ''; position: absolute; right: -20px; top: -20px; width: 96px; height: 96px; border-radius: 999px; opacity: 0.6; }
    .kpi-purple { background: linear-gradient(135deg, #ffffff, #f7f1ff); }
    .kpi-purple::after { background: var(--purple-soft); }
    .kpi-pink { background: linear-gradient(135deg, #ffffff, #fff0f8); }
    .kpi-pink::after { background: var(--pink-soft); }
    .kpi-blue { background: linear-gradient(135deg, #ffffff, #eef4ff); }
    .kpi-blue::after { background: var(--blue-soft); }
    .kpi-value { font-size: 1.6rem; font-weight: 800; }
    .kpi-sub { font-size: 0.8rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; }
    .chart-title { font-size: 0.9rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 10px; }
    .chart-wrap { min-height: 300px; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    .dot-ok { background: #10b981; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.15); }
    .dot-fail { background: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15); }
    .route-list { max-height: 260px; overflow: auto; }
    .route-item { border: 1px solid var(--line); border-radius: 12px; padding: 8px 10px; background: #fff; }
    .route-method { font-size: 0.75rem; font-weight: 800; border-radius: 999px; padding: 3px 10px; }
    .route-method.get { background: var(--blue-soft); color: var(--blue); }
    .route-method.post { background: var(--pink-soft); color: var(--pink); }
    .route-method.patch { background: var(--purple-soft); color: var(--purple); }
    .status-card-title { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
    .muted-2 { color: var(--muted); font-size: 0.88rem; }
    .muted { color: var(--muted); }
    .table-sm td, .table-sm th { vertical-align: middle; }
  </style>
</head>
<body>
<div class="container-fluid shell">
  <div class="row g-0">
    <aside class="col-12 col-lg-2 p-3 sidebar">
      <div class="mb-4">
        <h5 class="mb-1">PelisNube</h5>
        <small class="text-light-emphasis">Admin Console</small>
      </div>
      <nav class="d-flex flex-column gap-2">
        <?php
        $menu = [
            'dashboard' => t_admin('dashboard'),
            'contents' => t_admin('contents'),
            'sections' => t_admin('sections'),
            'users' => t_admin('users'),
            'plans' => t_admin('plans'),
            'subscriptions' => t_admin('subscriptions'),
            'audit' => t_admin('audit'),
            'api-routes' => t_admin('api_routes'),
        ];
        foreach ($menu as $key => $label):
        ?>
          <a href="?page=<?= h($key) ?>" class="<?= $page === $key ? 'active fw-bold' : '' ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <hr class="border-secondary my-4">
      <div class="small mb-3">
        <div><?= h((string) ($user['name'] ?? '')) ?></div>
        <div class="text-light-emphasis"><?= h((string) ($user['role'] ?? '')) ?></div>
      </div>
      <div class="d-flex gap-2 mb-3">
        <a class="btn btn-sm btn-outline-light" href="?lang=es&page=<?= h($page) ?>">ES</a>
        <a class="btn btn-sm btn-outline-light" href="?lang=en&page=<?= h($page) ?>">EN</a>
      </div>
      <a class="btn btn-sm btn-danger" href="?action=logout"><?= h(t_admin('logout')) ?></a>
    </aside>

    <main class="col-12 col-lg-10 p-3 p-lg-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($page === 'dashboard'): ?>
        <?php
        $successRequests = count(array_filter($dashboardRequestState, static fn(bool $ok): bool => $ok));
        $totalRequests = count($dashboardRequestState);
        $paymentSuccessTotal = 0;
        $paymentFailedTotal = 0;
        foreach ($chartPayments as $paymentPoint) {
            $paymentSuccessTotal += (int) ($paymentPoint['success_count'] ?? 0);
            $paymentFailedTotal += (int) ($paymentPoint['failed_count'] ?? 0);
        }
        ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h4 mb-1"><?= h($lang === 'en' ? 'Informative Dashboard' : 'Dashboard informativo') ?></h2>
            <div class="muted-2"><?= h($lang === 'en' ? 'API base URL:' : 'Base URL API:') ?> <code><?= h($apiBaseUrl) ?></code></div>
          </div>
          <div class="card-soft px-3 py-2">
            <span class="status-dot <?= !empty($dashboardRequestState['health']) ? 'dot-ok' : 'dot-fail' ?>"></span>
            <strong><?= h($lang === 'en' ? 'API status:' : 'Estado API:') ?></strong>
            <?= h((string) ($dashboardHealth['status'] ?? 'unknown')) ?>
            <span class="muted-2 ms-2"><?= h($successRequests . '/' . $totalRequests . ' ' . ($lang === 'en' ? 'endpoints ok' : 'endpoints ok')) ?></span>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card-soft kpi-card kpi-purple p-3">
              <div class="kpi-sub"><?= h($lang === 'en' ? 'Active users' : 'Usuarios activos') ?></div>
              <div class="kpi-value"><?= h((string) ($kpis['activeUsersTotal'] ?? 0)) ?></div>
              <div class="muted-2">SUPER_ADMIN: <?= h((string) (($kpis['activeUsersByRole']['SUPER_ADMIN'] ?? 0))) ?> | ADMIN: <?= h((string) (($kpis['activeUsersByRole']['ADMIN'] ?? 0))) ?> | USER: <?= h((string) (($kpis['activeUsersByRole']['USER'] ?? 0))) ?></div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card-soft kpi-card kpi-pink p-3">
              <div class="kpi-sub"><?= h($lang === 'en' ? 'Active content' : 'Contenido activo') ?></div>
              <div class="kpi-value"><?= h((string) ($kpis['activeContentTotal'] ?? 0)) ?></div>
              <div class="muted-2"><?= h($lang === 'en' ? 'Movies and series available' : 'Peliculas y series disponibles') ?></div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card-soft kpi-card kpi-blue p-3">
              <div class="kpi-sub"><?= h($lang === 'en' ? 'Subscriptions' : 'Suscripciones') ?></div>
              <div class="kpi-value"><?= h((string) ($kpis['activeSubscriptionsTotal'] ?? 0)) ?></div>
              <div class="muted-2"><?= h($lang === 'en' ? 'Active subscription records' : 'Registros activos de suscripcion') ?></div>
            </div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card-soft kpi-card kpi-purple p-3">
              <div class="kpi-sub"><?= h($lang === 'en' ? 'Payments this month' : 'Pagos del mes') ?></div>
              <div class="kpi-value"><?= h((string) ($kpis['paymentsSuccessCurrentMonth'] ?? 0)) ?></div>
              <div class="muted-2">SUCCESS: <?= h((string) $paymentSuccessTotal) ?> | FAILED: <?= h((string) $paymentFailedTotal) ?></div>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-xl-4">
            <div class="card-soft p-3">
              <div class="status-card-title mb-3"><?= h($lang === 'en' ? 'API endpoint status' : 'Estado de endpoints API') ?></div>
              <div class="d-flex flex-column gap-2">
                <?php
                $endpointStatusLabels = [
                    'health' => 'GET /health',
                    'kpis' => 'GET /admin/dashboard/kpis',
                    'users' => 'GET /admin/dashboard/charts/users-monthly',
                    'subscriptions' => 'GET /admin/dashboard/charts/subscriptions-status',
                    'payments' => 'GET /admin/dashboard/charts/payments-monthly',
                    'favorites' => 'GET /admin/dashboard/charts/top-content-favorites',
                ];
                foreach ($endpointStatusLabels as $statusKey => $statusLabel):
                ?>
                  <div class="d-flex justify-content-between align-items-center border rounded-3 px-2 py-2">
                    <code class="small"><?= h($statusLabel) ?></code>
                    <span class="small">
                      <span class="status-dot <?= !empty($dashboardRequestState[$statusKey]) ? 'dot-ok' : 'dot-fail' ?>"></span>
                      <?= h(!empty($dashboardRequestState[$statusKey]) ? 'OK' : 'FAIL') ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="col-12 col-xl-8">
            <div class="card-soft p-3">
              <div class="status-card-title mb-3"><?= h($lang === 'en' ? 'Main API routes' : 'Rutas API principales') ?></div>
              <div class="route-list d-flex flex-column gap-2">
                <?php foreach ($dashboardRoutes as $route): ?>
                  <div class="route-item d-flex justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="route-method <?= strtolower((string) $route['method']) ?>"><?= h((string) $route['method']) ?></span>
                      <code><?= h((string) $route['path']) ?></code>
                    </div>
                    <small class="muted"><?= h($lang === 'en' ? 'Enabled' : 'Habilitada') ?></small>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-xl-6">
            <div class="card-soft p-3 chart-wrap">
              <div class="chart-title" style="color: var(--purple);"><?= h($lang === 'en' ? 'Users by month' : 'Usuarios por mes') ?></div>
              <canvas id="usersChart"></canvas>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="card-soft p-3 chart-wrap">
              <div class="chart-title" style="color: var(--pink);"><?= h($lang === 'en' ? 'Subscription status' : 'Estado de suscripciones') ?></div>
              <canvas id="subsChart"></canvas>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="card-soft p-3 chart-wrap">
              <div class="chart-title" style="color: var(--blue);"><?= h($lang === 'en' ? 'Payments by month' : 'Pagos por mes') ?></div>
              <canvas id="paymentsChart"></canvas>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="card-soft p-3 chart-wrap">
              <div class="chart-title" style="color: var(--purple);"><?= h($lang === 'en' ? 'Top favorites' : 'Top favoritos') ?></div>
              <canvas id="favChart"></canvas>
            </div>
          </div>
          <div class="col-12">
            <div class="card-soft p-3 chart-wrap">
              <div class="chart-title" style="color: var(--pink);"><?= h($lang === 'en' ? 'Routes by HTTP method' : 'Rutas por metodo HTTP') ?></div>
              <canvas id="routesChart"></canvas>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($page === 'contents'): ?>
        <h2 class="h4 mb-3"><?= h(t_admin('contents')) ?></h2>
        <div class="card-soft p-3 mb-3">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="content_create">
            <input type="hidden" name="redirect_page" value="contents">
            <div class="col-md-4"><input class="form-control" name="title" placeholder="Title" required></div>
            <div class="col-md-2"><select class="form-select" name="type"><option>MOVIE</option><option>SERIES</option></select></div>
            <div class="col-md-2"><input class="form-control" type="number" name="year" placeholder="Year" required></div>
            <div class="col-md-2"><input class="form-control" type="number" name="duration" placeholder="Duration" required></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.1" name="rating" placeholder="Rating" required></div>
            <div class="col-12"><textarea class="form-control" name="synopsis" rows="2" placeholder="Synopsis" required></textarea></div>
            <div class="col-md-4"><input class="form-control" name="trailer_watch_url" placeholder="Trailer URL" required></div>
            <div class="col-md-4"><input class="form-control" name="poster_url" placeholder="Poster URL" required></div>
            <div class="col-md-4"><input class="form-control" name="banner_url" placeholder="Banner URL" required></div>
            <div class="col-12"><input class="form-control" name="section_ids" placeholder="Section IDs comma separated"></div>
            <div class="col-12"><button class="btn btn-dark">Create</button></div>
          </form>
        </div>

        <div class="card-soft p-3 mb-3">
          <h6>Update Content</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="content_update">
            <input type="hidden" name="redirect_page" value="contents">
            <div class="col-md-3"><input class="form-control" name="id" placeholder="Content ID" required></div>
            <div class="col-md-3"><input class="form-control" name="title" placeholder="Title"></div>
            <div class="col-md-2"><select class="form-select" name="type"><option>MOVIE</option><option>SERIES</option></select></div>
            <div class="col-md-2"><input class="form-control" type="number" name="year" placeholder="Year"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.1" name="rating" placeholder="Rating"></div>
            <div class="col-12"><textarea class="form-control" name="synopsis" rows="2" placeholder="Synopsis"></textarea></div>
            <div class="col-md-4"><input class="form-control" name="trailer_watch_url" placeholder="Trailer URL"></div>
            <div class="col-md-4"><input class="form-control" name="poster_url" placeholder="Poster URL"></div>
            <div class="col-md-4"><input class="form-control" name="banner_url" placeholder="Banner URL"></div>
            <div class="col-md-6"><input class="form-control" name="section_ids" placeholder="Section IDs comma separated"></div>
            <div class="col-md-2 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> <label class="form-check-label">Active</label></div>
            <div class="col-12"><button class="btn btn-primary">Update</button></div>
          </form>
        </div>

        <div class="card-soft p-3">
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>ID</th><th>Title</th><th>Type</th><th>Year</th><th>Rating</th><th>Active</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($contents as $item): ?>
                <tr>
                  <td class="small"><?= h((string) $item['id']) ?></td>
                  <td><?= h((string) $item['title']) ?></td>
                  <td><?= h((string) $item['type']) ?></td>
                  <td><?= h((string) $item['year']) ?></td>
                  <td><?= h((string) $item['rating']) ?></td>
                  <td><?= !empty($item['isActive']) ? 'Yes' : 'No' ?></td>
                  <td>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="content_inactivate">
                      <input type="hidden" name="redirect_page" value="contents">
                      <input type="hidden" name="id" value="<?= h((string) $item['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger">Inactivate</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($page === 'sections'): ?>
        <h2 class="h4 mb-3"><?= h(t_admin('sections')) ?></h2>
        <div class="card-soft p-3 mb-3">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="section_create">
            <input type="hidden" name="redirect_page" value="sections">
            <div class="col-md-3"><input class="form-control" name="name" placeholder="Name" required></div>
            <div class="col-md-3"><input class="form-control" name="key" placeholder="Key"></div>
            <div class="col-md-2"><input class="form-control" name="sort_order" type="number" value="0"></div>
            <div class="col-md-2 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="is_home_visible" value="1" checked> <label class="form-check-label">Home visible</label></div>
            <div class="col-12"><textarea class="form-control" name="description" rows="2" placeholder="Description"></textarea></div>
            <div class="col-12"><input class="form-control" name="content_ids" placeholder="Content IDs comma separated"></div>
            <div class="col-12"><button class="btn btn-dark">Create</button></div>
          </form>
        </div>

        <div class="card-soft p-3 mb-3">
          <h6>Update Section</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="section_update">
            <input type="hidden" name="redirect_page" value="sections">
            <div class="col-md-3"><input class="form-control" name="id" placeholder="Section ID" required></div>
            <div class="col-md-3"><input class="form-control" name="name" placeholder="Name"></div>
            <div class="col-md-3"><input class="form-control" name="key" placeholder="Key"></div>
            <div class="col-md-3"><input class="form-control" name="sort_order" type="number" placeholder="Sort"></div>
            <div class="col-12"><textarea class="form-control" name="description" rows="2" placeholder="Description"></textarea></div>
            <div class="col-md-4"><input class="form-control" name="content_ids" placeholder="Content IDs comma separated"></div>
            <div class="col-md-2 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="is_home_visible" value="1" checked> <label class="form-check-label">Home visible</label></div>
            <div class="col-md-2 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> <label class="form-check-label">Active</label></div>
            <div class="col-12"><button class="btn btn-primary">Update</button></div>
          </form>
        </div>

        <div class="card-soft p-3">
          <table class="table table-sm">
            <thead><tr><th>ID</th><th>Key</th><th>Name</th><th>Sort</th><th>Active</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($sections as $item): ?>
                <tr>
                  <td class="small"><?= h((string) $item['id']) ?></td>
                  <td><?= h((string) $item['key']) ?></td>
                  <td><?= h((string) $item['name']) ?></td>
                  <td><?= h((string) $item['sortOrder']) ?></td>
                  <td><?= !empty($item['isActive']) ? 'Yes' : 'No' ?></td>
                  <td>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="section_inactivate">
                      <input type="hidden" name="redirect_page" value="sections">
                      <input type="hidden" name="id" value="<?= h((string) $item['id']) ?>">
                      <button class="btn btn-sm btn-outline-danger">Inactivate</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($page === 'users'): ?>
        <h2 class="h4 mb-3"><?= h(t_admin('users')) ?></h2>
        <div class="card-soft p-3 mb-3">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="user_create">
            <input type="hidden" name="redirect_page" value="users">
            <div class="col-md-3"><input class="form-control" name="name" placeholder="Name" required></div>
            <div class="col-md-3"><input class="form-control" name="email" type="email" placeholder="Email" required></div>
            <div class="col-md-2"><input class="form-control" name="password" type="text" placeholder="Password" required></div>
            <div class="col-md-2"><select class="form-select" name="role"><option>USER</option><option>ADMIN</option><option>SUPER_ADMIN</option></select></div>
            <div class="col-md-2"><select class="form-select" name="status"><option>ACTIVE</option><option>PENDING</option><option>SUSPENDED</option><option>INACTIVE</option></select></div>
            <div class="col-md-2"><select class="form-select" name="preferred_lang"><option value="es">es</option><option value="en">en</option></select></div>
            <div class="col-md-3 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="must_change_password" value="1"> <label class="form-check-label">Must change password</label></div>
            <div class="col-12"><button class="btn btn-dark">Create</button></div>
          </form>
        </div>

        <div class="card-soft p-3 mb-3">
          <h6>Update User</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="user_update">
            <input type="hidden" name="redirect_page" value="users">
            <div class="col-md-3"><input class="form-control" name="id" placeholder="User ID" required></div>
            <div class="col-md-3"><input class="form-control" name="name" placeholder="Name"></div>
            <div class="col-md-2"><select class="form-select" name="role"><option>USER</option><option>ADMIN</option><option>SUPER_ADMIN</option></select></div>
            <div class="col-md-2"><select class="form-select" name="status"><option>ACTIVE</option><option>PENDING</option><option>SUSPENDED</option><option>INACTIVE</option></select></div>
            <div class="col-md-2"><select class="form-select" name="preferred_lang"><option value="es">es</option><option value="en">en</option></select></div>
            <div class="col-md-2 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="must_change_password" value="1"> <label class="form-check-label">Must change password</label></div>
            <div class="col-md-2 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> <label class="form-check-label">Active</label></div>
            <div class="col-12"><button class="btn btn-primary">Update</button></div>
          </form>
        </div>

        <div class="card-soft p-3 mb-3">
          <h6>Reset Password</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="user_reset_password">
            <input type="hidden" name="redirect_page" value="users">
            <div class="col-md-4"><input class="form-control" name="id" placeholder="User ID" required></div>
            <div class="col-md-4"><input class="form-control" name="new_password" placeholder="Optional new password"></div>
            <div class="col-md-4"><button class="btn btn-warning">Reset Password</button></div>
          </form>
        </div>

        <div class="card-soft p-3">
          <table class="table table-sm">
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $item): ?>
              <tr>
                <td class="small"><?= h((string) $item['id']) ?></td>
                <td><?= h((string) $item['name']) ?></td>
                <td><?= h((string) $item['email']) ?></td>
                <td><?= h((string) $item['role']) ?></td>
                <td><?= h((string) $item['status']) ?></td>
                <td><?= !empty($item['is_active']) ? 'Yes' : 'No' ?></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="user_inactivate">
                    <input type="hidden" name="redirect_page" value="users">
                    <input type="hidden" name="id" value="<?= h((string) $item['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger">Inactivate</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($page === 'plans'): ?>
        <h2 class="h4 mb-3"><?= h(t_admin('plans')) ?></h2>
        <div class="card-soft p-3 mb-3">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="plan_create">
            <input type="hidden" name="redirect_page" value="plans">
            <div class="col-md-2"><input class="form-control" name="code" placeholder="Code" required></div>
            <div class="col-md-3"><input class="form-control" name="name" placeholder="Name" required></div>
            <div class="col-md-2"><input class="form-control" name="price_monthly" type="number" step="0.01" placeholder="Price" required></div>
            <div class="col-md-2"><input class="form-control" name="currency" value="MXN"></div>
            <div class="col-md-2"><input class="form-control" name="quality" value="HD"></div>
            <div class="col-md-1"><input class="form-control" name="screens" type="number" value="1"></div>
            <div class="col-12"><button class="btn btn-dark">Create</button></div>
          </form>
        </div>

        <div class="card-soft p-3 mb-3">
          <h6>Update Plan</h6>
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="plan_update">
            <input type="hidden" name="redirect_page" value="plans">
            <div class="col-md-2"><input class="form-control" name="id" placeholder="Plan ID" required></div>
            <div class="col-md-2"><input class="form-control" name="code" placeholder="Code"></div>
            <div class="col-md-2"><input class="form-control" name="name" placeholder="Name"></div>
            <div class="col-md-2"><input class="form-control" name="price_monthly" type="number" step="0.01" placeholder="Price"></div>
            <div class="col-md-1"><input class="form-control" name="currency" value="MXN"></div>
            <div class="col-md-2"><input class="form-control" name="quality" value="HD"></div>
            <div class="col-md-1"><input class="form-control" name="screens" type="number" value="1"></div>
            <div class="col-md-2 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked> <label class="form-check-label">Active</label></div>
            <div class="col-12"><button class="btn btn-primary">Update</button></div>
          </form>
        </div>

        <div class="card-soft p-3">
          <table class="table table-sm">
            <thead><tr><th>ID</th><th>Code</th><th>Name</th><th>Price</th><th>Quality</th><th>Screens</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($plans as $item): ?>
              <tr>
                <td class="small"><?= h((string) $item['id']) ?></td>
                <td><?= h((string) $item['code']) ?></td>
                <td><?= h((string) $item['name']) ?></td>
                <td><?= h((string) $item['priceMonthly']) ?> <?= h((string) $item['currency']) ?></td>
                <td><?= h((string) $item['quality']) ?></td>
                <td><?= h((string) $item['screens']) ?></td>
                <td><?= !empty($item['isActive']) ? 'Yes' : 'No' ?></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="plan_inactivate">
                    <input type="hidden" name="redirect_page" value="plans">
                    <input type="hidden" name="id" value="<?= h((string) $item['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger">Inactivate</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($page === 'subscriptions'): ?>
        <h2 class="h4 mb-3"><?= h(t_admin('subscriptions')) ?></h2>
        <div class="card-soft p-3 mb-3">
          <form method="post" class="row g-2">
            <input type="hidden" name="action" value="subscription_update">
            <input type="hidden" name="redirect_page" value="subscriptions">
            <div class="col-md-3"><input class="form-control" name="id" placeholder="Subscription ID" required></div>
            <div class="col-md-2"><select class="form-select" name="status"><option>PENDING</option><option selected>ACTIVE</option><option>CANCELED</option><option>EXPIRED</option></select></div>
            <div class="col-md-2"><input class="form-control" name="plan_code" placeholder="Plan code"></div>
            <div class="col-md-2"><input class="form-control" name="renewal_at" placeholder="YYYY-MM-DD HH:MM:SS"></div>
            <div class="col-md-2"><input class="form-control" name="ended_at" placeholder="YYYY-MM-DD HH:MM:SS"></div>
            <div class="col-md-1 form-check align-self-center ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked><label class="form-check-label">Active</label></div>
            <div class="col-12"><button class="btn btn-primary">Update</button></div>
          </form>
        </div>

        <div class="card-soft p-3">
          <table class="table table-sm">
            <thead><tr><th>ID</th><th>User</th><th>Email</th><th>Status</th><th>Plan</th><th>Renewal</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($subscriptions as $item): ?>
              <tr>
                <td class="small"><?= h((string) $item['id']) ?></td>
                <td><?= h((string) ($item['user']['name'] ?? '')) ?></td>
                <td><?= h((string) ($item['user']['email'] ?? '')) ?></td>
                <td><?= h((string) $item['status']) ?></td>
                <td><?= h((string) ($item['plan']['code'] ?? '')) ?></td>
                <td><?= h((string) ($item['renewalAt'] ?? '')) ?></td>
                <td><?= !empty($item['isActive']) ? 'Yes' : 'No' ?></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="subscription_inactivate">
                    <input type="hidden" name="redirect_page" value="subscriptions">
                    <input type="hidden" name="id" value="<?= h((string) $item['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger">Inactivate</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($page === 'audit'): ?>
        <h2 class="h4 mb-3"><?= h(t_admin('audit')) ?></h2>
        <div class="card-soft p-3">
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th>ID</th><th>Date</th><th>Actor</th><th>Role</th><th>Target</th><th>Action</th><th>IP</th></tr></thead>
              <tbody>
              <?php foreach ($auditLogs as $log): ?>
                <tr>
                  <td><?= h((string) $log['id']) ?></td>
                  <td><?= h((string) $log['created_at']) ?></td>
                  <td><?= h((string) ($log['actor_user_id'] ?? '')) ?></td>
                  <td><?= h((string) ($log['actor_role'] ?? '')) ?></td>
                  <td><?= h((string) ($log['target_type'] ?? '')) ?> / <?= h((string) ($log['target_id'] ?? '')) ?></td>
                  <td><?= h((string) ($log['action'] ?? '')) ?></td>
                  <td><?= h((string) ($log['ip_address'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($page === 'api-routes'): ?>
        <h2 class="h4 mb-3"><?= h(t_admin('api_routes')) ?></h2>
        <div class="card-soft p-3">
<pre class="mb-0 small">GET    /api/v1/health
GET    /api/v1/catalog
GET    /api/v1/catalog/{slug}
GET    /api/v1/sections/home
POST   /api/v1/auth/signup-with-payment
POST   /api/v1/auth/login
GET    /api/v1/auth/me
POST   /api/v1/auth/password/otp/request
POST   /api/v1/auth/password/otp/verify
POST   /api/v1/auth/password/reset
GET    /api/v1/favorites
POST   /api/v1/favorites/{contentId}
PATCH  /api/v1/favorites/{contentId}/inactive
GET    /api/v1/subscription/me
POST   /api/v1/subscription/change-plan
POST   /api/v1/subscription/cancel
GET    /api/v1/admin/dashboard/kpis
GET    /api/v1/admin/dashboard/charts/users-monthly
GET    /api/v1/admin/dashboard/charts/subscriptions-status
GET    /api/v1/admin/dashboard/charts/payments-monthly
GET    /api/v1/admin/dashboard/charts/top-content-favorites
GET    /api/v1/admin/audit-logs
GET    /api/v1/admin/contents
POST   /api/v1/admin/contents
PATCH  /api/v1/admin/contents/{id}
PATCH  /api/v1/admin/contents/{id}/inactive
GET    /api/v1/admin/sections
POST   /api/v1/admin/sections
PATCH  /api/v1/admin/sections/{id}
PATCH  /api/v1/admin/sections/{id}/inactive
GET    /api/v1/admin/users
POST   /api/v1/admin/users
PATCH  /api/v1/admin/users/{id}
POST   /api/v1/admin/users/{id}/password-reset
PATCH  /api/v1/admin/users/{id}/inactive
GET    /api/v1/admin/subscription-plans
POST   /api/v1/admin/subscription-plans
PATCH  /api/v1/admin/subscription-plans/{id}
PATCH  /api/v1/admin/subscription-plans/{id}/inactive
GET    /api/v1/admin/subscriptions
PATCH  /api/v1/admin/subscriptions/{id}
PATCH  /api/v1/admin/subscriptions/{id}/inactive</pre>
        </div>
      <?php endif; ?>
    </main>
  </div>
</div>

<script>
<?php if ($page === 'dashboard'): ?>
const usersData = <?= json_encode($chartUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const subsData = <?= json_encode($chartSubscriptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const paymentData = <?= json_encode($chartPayments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const favData = <?= json_encode($chartTopFavorites, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const routeMethodTotals = <?= json_encode($dashboardMethodTotals, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const purple = '#6d28d9';
const pink = '#ec4899';
const blue = '#2563eb';
const gridColor = 'rgba(113, 105, 153, 0.18)';
const tickColor = '#5a4f86';

const axisOptions = {
  ticks: { color: tickColor, font: { weight: 600 } },
  grid: { color: gridColor }
};

new Chart(document.getElementById('usersChart'), {
  type: 'line',
  data: {
    labels: usersData.map(i => i.ym),
    datasets: [{
      label: 'Users/month',
      data: usersData.map(i => Number(i.total)),
      borderColor: purple,
      backgroundColor: 'rgba(109,40,217,0.20)',
      fill: true,
      tension: 0.35,
      pointBackgroundColor: purple,
      pointRadius: 3,
    }]
  },
  options: {
    plugins: { legend: { labels: { color: tickColor } } },
    scales: { x: axisOptions, y: axisOptions }
  },
});

new Chart(document.getElementById('subsChart'), {
  type: 'doughnut',
  data: {
    labels: subsData.map(i => i.status),
    datasets: [{ data: subsData.map(i => Number(i.total)), backgroundColor: [purple, pink, blue, '#8b5cf6'] }]
  },
  options: { plugins: { legend: { labels: { color: tickColor } } } }
});

new Chart(document.getElementById('paymentsChart'), {
  type: 'bar',
  data: {
    labels: paymentData.map(i => i.ym),
    datasets: [
      { label: 'Success', data: paymentData.map(i => Number(i.success_count)), backgroundColor: blue, borderRadius: 8 },
      { label: 'Failed', data: paymentData.map(i => Number(i.failed_count)), backgroundColor: pink, borderRadius: 8 }
    ]
  },
  options: {
    plugins: { legend: { labels: { color: tickColor } } },
    scales: { x: axisOptions, y: axisOptions }
  },
});

new Chart(document.getElementById('favChart'), {
  type: 'bar',
  data: {
    labels: favData.map(i => i.title),
    datasets: [{ label: 'Favorites', data: favData.map(i => Number(i.total)), backgroundColor: purple, borderRadius: 8 }]
  },
  options: {
    indexAxis: 'y',
    plugins: { legend: { labels: { color: tickColor } } },
    scales: { x: axisOptions, y: axisOptions }
  }
});

new Chart(document.getElementById('routesChart'), {
  type: 'bar',
  data: {
    labels: Object.keys(routeMethodTotals),
    datasets: [{
      label: 'Total routes',
      data: Object.values(routeMethodTotals).map(i => Number(i)),
      backgroundColor: [blue, pink, purple],
      borderRadius: 10
    }]
  },
  options: {
    plugins: { legend: { labels: { color: tickColor } } },
    scales: { x: axisOptions, y: axisOptions }
  }
});
<?php endif; ?>
</script>
</body>
</html>
