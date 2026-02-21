<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\HttpException;
use App\Repositories\PaymentRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use DateInterval;
use DateTimeImmutable;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly PlanRepository $plans,
        private readonly PaymentRepository $payments,
        private readonly PaymentSimulatorService $simulator,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function changePlan(string $userId, string $planCode, string $cardNumber, ?string $cardBrand): array
    {
        $plan = $this->plans->findByCode($planCode);
        if (!$plan || (int) $plan['is_active'] !== 1) {
            throw new HttpException(404, 'Plan no encontrado.');
        }

        $subscription = $this->subscriptions->findByUserId($userId);
        if (!$subscription) {
            throw new HttpException(404, 'Suscripcion no encontrada.');
        }

        $payment = $this->simulator->evaluate($cardNumber, $cardBrand);

        $attempt = $this->payments->create([
            'id' => uuidv4(),
            'user_id' => $userId,
            'plan_id' => $plan['id'],
            'amount' => $plan['price_monthly'],
            'currency' => $plan['currency'] ?? Env::get('APP_CURRENCY', 'MXN'),
            'card_last4' => $payment['last4'],
            'card_brand' => $payment['brand'],
            'status' => $payment['status'],
            'reason' => $payment['reason'],
            'metadata' => ['phase' => 'plan_change'],
        ]);

        if ($payment['status'] !== 'SUCCESS') {
            return [
                'changed' => false,
                'reason' => $payment['reason'],
                'payment' => $attempt,
                'subscription' => $subscription,
            ];
        }

        $renewalAt = (new DateTimeImmutable('now'))->add(new DateInterval('P30D'));

        $updated = $this->subscriptions->update((string) $subscription['id'], [
            'plan_id' => $plan['id'],
            'status' => 'ACTIVE',
            'is_active' => 1,
            'renewal_at' => $renewalAt->format('Y-m-d H:i:s'),
            'ended_at' => null,
        ]);

        return [
            'changed' => true,
            'payment' => $attempt,
            'subscription' => $updated,
        ];
    }

    /**
     * @return array<string,int>
     */
    public function runDailyRenewal(): array
    {
        $due = $this->subscriptions->dueForRenewal();

        $renewed = 0;
        $expired = 0;

        foreach ($due as $row) {
            $subscriptionId = (string) $row['id'];

            if ((int) $row['user_active'] !== 1 || $row['user_status'] !== 'ACTIVE' || (int) $row['plan_active'] !== 1) {
                $this->subscriptions->update($subscriptionId, [
                    'status' => 'EXPIRED',
                    'is_active' => 0,
                    'ended_at' => now_utc(),
                ]);
                $expired++;
                continue;
            }

            $nextRenewal = (new DateTimeImmutable('now'))->add(new DateInterval('P30D'));
            $this->subscriptions->update($subscriptionId, [
                'status' => 'ACTIVE',
                'renewal_at' => $nextRenewal->format('Y-m-d H:i:s'),
                'is_active' => 1,
            ]);
            $renewed++;
        }

        return [
            'renewed' => $renewed,
            'expired' => $expired,
            'totalDue' => count($due),
        ];
    }
}
