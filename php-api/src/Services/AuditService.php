<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\AuditRepository;

final class AuditService
{
    private AuditRepository $audit;

    public function __construct(AuditRepository $audit)
    {
        $this->audit = $audit;
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public function mutation(
        Request $request,
        string $targetType,
        string $targetId,
        string $action,
        ?array $before,
        ?array $after
    ): void {
        $actor = $request->user();

        $this->audit->log([
            'actor_user_id' => $actor['id'] ?? null,
            'actor_role' => $actor['role'] ?? null,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'action' => $action,
            'before_state' => $before,
            'after_state' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $tableMap = [
            'users' => 'user_history',
            'contents' => 'content_history',
            'sections' => 'section_history',
            'plans' => 'plan_history',
            'subscriptions' => 'subscription_history',
        ];

        if (isset($tableMap[$targetType])) {
            $this->audit->addHistory(
                $tableMap[$targetType],
                $targetId,
                $action,
                $after ?? $before ?? [],
                $actor['id'] ?? null
            );
        }
    }
}
