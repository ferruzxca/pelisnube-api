<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\FavoritesController;
use App\Controllers\HealthController;
use App\Controllers\SubscriptionController;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Repositories\AuditRepository;
use App\Repositories\ContentRepository;
use App\Repositories\FavoriteRepository;
use App\Repositories\OtpRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SectionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\PaymentSimulatorService;
use App\Services\SubscriptionService;

require_once __DIR__ . '/bootstrap.php';

$request = Request::fromGlobals();

$allowedOriginsRaw = (string) Env::get('CORS_ORIGINS', '*');
$origin = $request->header('origin', '*');
$allowedOrigins = array_filter(array_map('trim', explode(',', $allowedOriginsRaw)));

if ($allowedOriginsRaw === '*' || in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . ($allowedOriginsRaw === '*' ? '*' : $origin));
    header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept-Language');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, OPTIONS');
header('Access-Control-Allow-Credentials: false');

if ($request->method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$base = '/api/v1';

// Keep health endpoint alive even if DB is unavailable.
if ($request->method() === 'GET' && rtrim($request->path(), '/') === $base . '/health') {
    $healthController = new HealthController();
    $healthController($request);
    exit;
}

try {
    $pdo = Database::pdo();

    $users = new UserRepository($pdo);
    $contents = new ContentRepository($pdo);
    $sections = new SectionRepository($pdo);
    $plans = new PlanRepository($pdo);
    $subscriptions = new SubscriptionRepository($pdo);
    $favorites = new FavoriteRepository($pdo);
    $payments = new PaymentRepository($pdo);
    $otps = new OtpRepository($pdo);
    $auditRepo = new AuditRepository($pdo);

    $auditService = new AuditService($auditRepo);
    $rateLimiter = new RateLimiter($pdo);
    $emailService = new EmailService();
    $paymentSimulator = new PaymentSimulatorService();
    $authService = new AuthService($users, $plans, $subscriptions, $payments, $otps, $paymentSimulator, $emailService);
    $subscriptionService = new SubscriptionService($subscriptions, $plans, $payments, $paymentSimulator);

    $healthController = new HealthController();
    $catalogController = new CatalogController($contents, $sections);
    $authController = new AuthController($authService, $rateLimiter);
    $favoritesController = new FavoritesController($favorites, $contents, $auditService);
    $subscriptionController = new SubscriptionController($subscriptions, $subscriptionService, $auditService);
    $adminController = new AdminController(
        $users,
        $contents,
        $sections,
        $plans,
        $subscriptions,
        $payments,
        $favorites,
        $auditRepo,
        $auditService,
    );

    $router = new Router();

    $authAny = [Auth::requireAuth($users)];
    $authUser = [Auth::requireAuth($users, ['USER'])];
    $authAdmin = [Auth::requireAuth($users, ['SUPER_ADMIN', 'ADMIN'])];

    $router->add('GET', $base . '/health', $healthController(...));
    $router->add('GET', $base . '/catalog', [$catalogController, 'list']);
    $router->add('GET', $base . '/catalog/{slug}', [$catalogController, 'detail'], $authAny);
    $router->add('GET', $base . '/sections/home', [$catalogController, 'homeSections'], $authAny);

    $router->add('POST', $base . '/auth/signup-with-payment', [$authController, 'signupWithPayment']);
    $router->add('POST', $base . '/auth/login', [$authController, 'login']);
    $router->add('GET', $base . '/auth/me', [$authController, 'me'], $authAny);
    $router->add('POST', $base . '/auth/password/otp/request', [$authController, 'requestOtp']);
    $router->add('POST', $base . '/auth/password/otp/verify', [$authController, 'verifyOtp']);
    $router->add('POST', $base . '/auth/password/reset', [$authController, 'resetPassword']);

    $router->add('GET', $base . '/favorites', [$favoritesController, 'list'], $authUser);
    $router->add('POST', $base . '/favorites/{contentId}', [$favoritesController, 'save'], $authUser);
    $router->add('PATCH', $base . '/favorites/{contentId}/inactive', [$favoritesController, 'inactive'], $authUser);

    $router->add('GET', $base . '/subscription/me', [$subscriptionController, 'me'], $authUser);
    $router->add('POST', $base . '/subscription/change-plan', [$subscriptionController, 'changePlan'], $authUser);
    $router->add('POST', $base . '/subscription/cancel', [$subscriptionController, 'cancel'], $authUser);

    $router->add('GET', $base . '/admin/dashboard/kpis', [$adminController, 'dashboardKpis'], $authAdmin);
    $router->add('GET', $base . '/admin/dashboard/charts/users-monthly', [$adminController, 'chartUsersMonthly'], $authAdmin);
    $router->add('GET', $base . '/admin/dashboard/charts/subscriptions-status', [$adminController, 'chartSubscriptionsStatus'], $authAdmin);
    $router->add('GET', $base . '/admin/dashboard/charts/payments-monthly', [$adminController, 'chartPaymentsMonthly'], $authAdmin);
    $router->add('GET', $base . '/admin/dashboard/charts/top-content-favorites', [$adminController, 'chartTopContentFavorites'], $authAdmin);
    $router->add('GET', $base . '/admin/audit-logs', [$adminController, 'auditLogs'], $authAdmin);

    $router->add('GET', $base . '/admin/contents', [$adminController, 'listContents'], $authAdmin);
    $router->add('POST', $base . '/admin/contents', [$adminController, 'createContent'], $authAdmin);
    $router->add('PATCH', $base . '/admin/contents/{id}', [$adminController, 'updateContent'], $authAdmin);
    $router->add('PATCH', $base . '/admin/contents/{id}/inactive', [$adminController, 'inactivateContent'], $authAdmin);

    $router->add('GET', $base . '/admin/sections', [$adminController, 'listSections'], $authAdmin);
    $router->add('POST', $base . '/admin/sections', [$adminController, 'createSection'], $authAdmin);
    $router->add('PATCH', $base . '/admin/sections/{id}', [$adminController, 'updateSection'], $authAdmin);
    $router->add('PATCH', $base . '/admin/sections/{id}/inactive', [$adminController, 'inactivateSection'], $authAdmin);

    $router->add('GET', $base . '/admin/users', [$adminController, 'listUsers'], $authAdmin);
    $router->add('POST', $base . '/admin/users', [$adminController, 'createUser'], $authAdmin);
    $router->add('PATCH', $base . '/admin/users/{id}', [$adminController, 'updateUser'], $authAdmin);
    $router->add('POST', $base . '/admin/users/{id}/password-reset', [$adminController, 'resetUserPassword'], $authAdmin);
    $router->add('PATCH', $base . '/admin/users/{id}/inactive', [$adminController, 'inactivateUser'], $authAdmin);

    $router->add('GET', $base . '/admin/subscription-plans', [$adminController, 'listPlans'], $authAdmin);
    $router->add('POST', $base . '/admin/subscription-plans', [$adminController, 'createPlan'], $authAdmin);
    $router->add('PATCH', $base . '/admin/subscription-plans/{id}', [$adminController, 'updatePlan'], $authAdmin);
    $router->add('PATCH', $base . '/admin/subscription-plans/{id}/inactive', [$adminController, 'inactivatePlan'], $authAdmin);

    $router->add('GET', $base . '/admin/subscriptions', [$adminController, 'listSubscriptions'], $authAdmin);
    $router->add('PATCH', $base . '/admin/subscriptions/{id}', [$adminController, 'updateSubscription'], $authAdmin);
    $router->add('PATCH', $base . '/admin/subscriptions/{id}/inactive', [$adminController, 'inactivateSubscription'], $authAdmin);

    $router->dispatch($request);
} catch (HttpException $exception) {
    Response::error($exception->getMessage(), $exception->statusCode(), $exception->details());
} catch (Throwable $exception) {
    Response::error(I18n::t('server_error', $request->lang()), 500, [
        'trace' => Env::bool('APP_DEBUG', false) ? $exception->getMessage() : 'hidden',
        'file' => Env::bool('APP_DEBUG', false) ? $exception->getFile() : 'hidden',
        'line' => Env::bool('APP_DEBUG', false) ? $exception->getLine() : 0,
    ]);
}
