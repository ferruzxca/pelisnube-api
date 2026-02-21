<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    /**
     * @param array<int, string> $roles
     */
    public static function requireAuth(UserRepository $users, array $roles = []): callable
    {
        return static function (Request $request) use ($users, $roles): void {
            $token = $request->bearerToken();
            if (!$token) {
                throw new HttpException(401, I18n::t('unauthorized', $request->lang()));
            }

            $secret = (string) Env::get('JWT_SECRET', 'change-me');
            try {
                $payload = Jwt::decode($token, $secret);
            } catch (HttpException) {
                throw new HttpException(401, I18n::t('unauthorized', $request->lang()));
            }
            $userId = (string) ($payload['sub'] ?? '');
            if ($userId === '') {
                throw new HttpException(401, I18n::t('unauthorized', $request->lang()));
            }

            $user = $users->findById($userId);
            if (!$user || (int) $user['is_active'] !== 1 || $user['status'] !== 'ACTIVE') {
                throw new HttpException(401, I18n::t('unauthorized', $request->lang()));
            }

            $request->setUser([
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
                'status' => $user['status'],
                'preferredLang' => $user['preferred_lang'],
            ]);

            if ($roles !== [] && !in_array($user['role'], $roles, true)) {
                throw new HttpException(403, I18n::t('forbidden', $request->lang()));
            }
        };
    }
}
