<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\PaymentRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\PaymentSimulatorService;
use App\Services\SubscriptionService;

require_once dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::pdo();
$subscriptions = new SubscriptionRepository($pdo);
$plans = new PlanRepository($pdo);
$payments = new PaymentRepository($pdo);
$simulator = new PaymentSimulatorService();
$service = new SubscriptionService($subscriptions, $plans, $payments, $simulator);

$result = $service->runDailyRenewal();

echo json_encode([
    'success' => true,
    'message' => 'Daily renewal job completed.',
    'data' => $result,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
