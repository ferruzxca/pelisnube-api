<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SubscriptionRepository;
use App\Services\AuditService;
use App\Services\SubscriptionService;

final class SubscriptionController extends BaseController
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly SubscriptionService $subscriptionService,
        private readonly AuditService $auditService,
    ) {
    }

    public function me(Request $request): void
    {
        $user = $this->requireUser($request);
        if ($user['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $subscription = $this->subscriptions->userSubscriptionDetail((string) $user['id']);
        if (!$subscription) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Subscription not found.' : 'Suscripcion no encontrada.');
        }

        Response::success(I18n::t('subscription_me', $request->lang()), $subscription);
    }

    public function changePlan(Request $request): void
    {
        $user = $this->requireUser($request);
        if ($user['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $planCode = (string) ($request->input('planCode') ?? '');
        $cardNumber = (string) ($request->input('cardNumber') ?? '');
        $cardBrand = (string) ($request->input('cardBrand') ?? 'SIMULATED');

        if ($planCode === '' || $cardNumber === '') {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), [
                'required' => ['planCode', 'cardNumber'],
            ]);
        }

        $before = $this->subscriptions->findByUserId((string) $user['id']);

        $result = $this->subscriptionService->changePlan(
            (string) $user['id'],
            $planCode,
            $cardNumber,
            $cardBrand,
        );

        if ($result['changed'] === true) {
            $this->auditService->mutation(
                $request,
                'subscriptions',
                (string) $result['subscription']['id'],
                'SUBSCRIPTION_PLAN_CHANGE',
                $before,
                $result['subscription'],
            );

            Response::success(I18n::t('subscription_changed', $request->lang()), $result);
            return;
        }

        Response::error(
            $request->lang() === 'en' ? 'Payment failed, plan not changed.' : 'Pago fallido, plan no actualizado.',
            402,
            $result,
        );
    }

    public function cancel(Request $request): void
    {
        $user = $this->requireUser($request);
        if ($user['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $subscription = $this->subscriptions->findByUserId((string) $user['id']);
        if (!$subscription) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Subscription not found.' : 'Suscripcion no encontrada.');
        }

        $updated = $this->subscriptions->update((string) $subscription['id'], [
            'status' => 'CANCELED',
            'is_active' => 0,
            'ended_at' => now_utc(),
        ]);

        $this->auditService->mutation(
            $request,
            'subscriptions',
            (string) $subscription['id'],
            'SUBSCRIPTION_CANCEL',
            $subscription,
            $updated,
        );

        Response::success(I18n::t('subscription_canceled', $request->lang()), [
            'subscription' => $updated,
        ]);
    }
}
