<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function signupWithPayment(Request $request): void
    {
        $result = $this->authService->signupWithPayment($request->body(), $request->lang());

        if ($result['token'] === null) {
            Response::error(I18n::t('signup_failed_payment', $request->lang()), 402, [
                'payment' => $result['payment'],
            ]);
            return;
        }

        Response::success(I18n::t('signup_success', $request->lang()), [
            'token' => $result['token'],
            'user' => $result['user'],
            'subscription' => $result['subscription'],
            'payment' => $result['payment'],
        ], 201);
    }

    public function login(Request $request): void
    {
        $email = strtolower(trim((string) ($request->input('email') ?? '')));
        $subject = $request->ip() . '|' . $email;

        $limitResult = $this->rateLimiter->hit(
            'auth_login',
            $subject,
            Env::int('RATE_LIMIT_LOGIN', 7),
            Env::int('RATE_LIMIT_WINDOW_SECONDS', 60),
        );

        if (!$limitResult['allowed']) {
            throw new HttpException(429, $request->lang() === 'en'
                ? 'Too many login attempts. Please try again later.'
                : 'Demasiados intentos de inicio de sesion. Intenta mas tarde.', [
                'retryAfter' => $limitResult['retryAfter'],
            ]);
        }

        $result = $this->authService->login($request->body(), $request->lang());

        Response::success(I18n::t('login_success', $request->lang()), $result);
    }

    public function me(Request $request): void
    {
        $user = $this->requireUser($request);
        $data = $this->authService->me((string) $user['id']);

        Response::success(I18n::t('me_success', $request->lang()), $data);
    }

    public function requestOtp(Request $request): void
    {
        $email = (string) ($request->input('email') ?? '');

        $limitResult = $this->rateLimiter->hit(
            'auth_otp_request',
            $request->ip() . '|' . strtolower(trim($email)),
            Env::int('RATE_LIMIT_OTP_REQUEST', 5),
            Env::int('RATE_LIMIT_WINDOW_SECONDS', 60),
        );

        if (!$limitResult['allowed']) {
            throw new HttpException(429, $request->lang() === 'en'
                ? 'Too many OTP requests. Please try again later.'
                : 'Demasiadas solicitudes OTP. Intenta mas tarde.', [
                'retryAfter' => $limitResult['retryAfter'],
            ]);
        }

        $this->authService->requestOtp($email, $request->lang());

        Response::success(I18n::t('generic_otp_request', $request->lang()));
    }

    public function verifyOtp(Request $request): void
    {
        $email = (string) ($request->input('email') ?? '');
        $code = (string) ($request->input('code') ?? '');

        $token = $this->authService->verifyOtp($email, $code, $request->lang());

        Response::success(I18n::t('otp_verified', $request->lang()), [
            'resetToken' => $token,
        ]);
    }

    public function resetPassword(Request $request): void
    {
        $token = (string) ($request->input('token') ?? '');
        $newPassword = (string) ($request->input('newPassword') ?? '');

        if ($token === '' || $newPassword === '') {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), [
                'required' => ['token', 'newPassword'],
            ]);
        }

        $this->authService->resetPassword($token, $newPassword, $request->lang());

        Response::success(I18n::t('password_reset_success', $request->lang()));
    }
}
