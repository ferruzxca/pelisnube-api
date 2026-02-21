<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Jwt;
use App\Core\Request;
use App\Repositories\OtpRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use DateInterval;
use DateTimeImmutable;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PlanRepository $plans,
        private readonly SubscriptionRepository $subscriptions,
        private readonly PaymentRepository $payments,
        private readonly OtpRepository $otps,
        private readonly PaymentSimulatorService $paymentSimulator,
        private readonly EmailService $emailService,
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function signupWithPayment(array $input, string $lang): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $planCode = strtoupper(trim((string) ($input['planCode'] ?? '')));
        $cardNumber = trim((string) ($input['cardNumber'] ?? ''));
        $cardBrand = trim((string) ($input['cardBrand'] ?? ''));
        $preferredLang = strtolower(trim((string) ($input['preferredLang'] ?? 'es')));

        if ($name === '' || $email === '' || $password === '' || $planCode === '' || $cardNumber === '') {
            throw new HttpException(422, I18n::t('validation_error', $lang), [
                'required' => ['name', 'email', 'password', 'planCode', 'cardNumber'],
            ]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, I18n::t('validation_error', $lang), ['email' => 'invalid']);
        }

        if (strlen($password) < 8) {
            throw new HttpException(422, I18n::t('validation_error', $lang), ['password' => 'min_8']);
        }

        $existing = $this->users->findByEmail($email);
        if ($existing && (int) $existing['is_active'] === 1) {
            throw new HttpException(409, $lang === 'en' ? 'Email already registered.' : 'El correo ya esta registrado.');
        }

        $plan = $this->plans->findByCode($planCode);
        if (!$plan || (int) $plan['is_active'] !== 1) {
            throw new HttpException(404, $lang === 'en' ? 'Plan not found.' : 'Plan no encontrado.');
        }

        $payment = $this->paymentSimulator->evaluate($cardNumber, $cardBrand);
        $paymentStatus = $payment['status'];

        if ($paymentStatus !== 'SUCCESS') {
            $paymentRecord = $this->payments->create([
                'id' => uuidv4(),
                'user_email' => $email,
                'plan_id' => $plan['id'],
                'amount' => $plan['price_monthly'],
                'currency' => $plan['currency'] ?? Env::get('APP_CURRENCY', 'MXN'),
                'card_last4' => $payment['last4'],
                'card_brand' => $payment['brand'],
                'status' => $paymentStatus,
                'reason' => $payment['reason'],
                'metadata' => ['phase' => 'signup'],
            ]);

            return [
                'payment' => $paymentRecord,
                'token' => null,
                'user' => null,
                'subscription' => null,
            ];
        }

        $userId = uuidv4();
        $user = $this->users->create([
            'id' => $userId,
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'USER',
            'status' => 'ACTIVE',
            'is_active' => 1,
            'preferred_lang' => in_array($preferredLang, ['es', 'en'], true) ? $preferredLang : 'es',
            'must_change_password' => 0,
        ]);

        $startedAt = new DateTimeImmutable('now');
        $renewalAt = $startedAt->add(new DateInterval('P30D'));

        $subscription = $this->subscriptions->create([
            'id' => uuidv4(),
            'user_id' => $userId,
            'plan_id' => $plan['id'],
            'status' => 'ACTIVE',
            'started_at' => $startedAt->format('Y-m-d H:i:s'),
            'renewal_at' => $renewalAt->format('Y-m-d H:i:s'),
            'is_active' => 1,
        ]);

        $paymentRecord = $this->payments->create([
            'id' => uuidv4(),
            'user_id' => $userId,
            'user_email' => $email,
            'plan_id' => $plan['id'],
            'amount' => $plan['price_monthly'],
            'currency' => $plan['currency'] ?? Env::get('APP_CURRENCY', 'MXN'),
            'card_last4' => $payment['last4'],
            'card_brand' => $payment['brand'],
            'status' => 'SUCCESS',
            'metadata' => ['phase' => 'subscription_initial'],
        ]);

        $token = $this->issueAccessToken($user);

        return [
            'payment' => $paymentRecord,
            'token' => $token,
            'user' => $this->sanitizeUser($user),
            'subscription' => $subscription,
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function login(array $input, string $lang): array
    {
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new HttpException(422, I18n::t('validation_error', $lang), ['required' => ['email', 'password']]);
        }

        $user = $this->users->findByEmail($email);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            throw new HttpException(401, I18n::t('unauthorized', $lang));
        }

        if ((int) $user['is_active'] !== 1 || $user['status'] !== 'ACTIVE') {
            throw new HttpException(401, I18n::t('unauthorized', $lang));
        }

        $token = $this->issueAccessToken($user);

        return [
            'token' => $token,
            'user' => $this->sanitizeUser($user),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function me(string $userId): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        $subscription = $this->subscriptions->userSubscriptionDetail($userId);

        $data = $this->sanitizeUser($user);
        $data['subscription'] = $subscription;

        return $data;
    }

    public function requestOtp(string $email, string $lang): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, I18n::t('validation_error', $lang), ['email' => 'invalid']);
        }

        $user = $this->users->findByEmail($email);
        if (!$user) {
            throw new HttpException(404, $lang === 'en' ? 'Email not found.' : 'Correo no encontrado.');
        }

        if ($user['role'] !== 'USER') {
            throw new HttpException(403, $lang === 'en'
                ? 'OTP recovery is only available for USER accounts.'
                : 'La recuperacion OTP solo aplica para cuentas USER.');
        }

        if ((int) $user['is_active'] !== 1 || $user['status'] !== 'ACTIVE') {
            throw new HttpException(403, $lang === 'en'
                ? 'Account is inactive.'
                : 'La cuenta esta inactiva.');
        }

        $resendSeconds = Env::int('OTP_RESEND_SECONDS', 60);
        $latest = $this->otps->latestOpenOtp((string) $user['id']);
        if ($latest) {
            $createdTs = strtotime((string) $latest['created_at']) ?: 0;
            if ((time() - $createdTs) < $resendSeconds) {
                throw new HttpException(429, $lang === 'en'
                    ? 'Please wait before requesting another code.'
                    : 'Espera antes de solicitar otro codigo.');
            }
        }

        $this->otps->invalidateOpenOtps((string) $user['id']);

        $code = (string) random_int(100000, 999999);
        $expiresAt = (new DateTimeImmutable('now'))
            ->add(new DateInterval('PT' . Env::int('OTP_EXP_MINUTES', 10) . 'M'))
            ->format('Y-m-d H:i:s');
        $otpId = uuidv4();

        $this->otps->create([
            'id' => $otpId,
            'user_id' => $user['id'],
            'code_hash' => password_hash($code, PASSWORD_BCRYPT),
            'expires_at' => $expiresAt,
            'max_attempts' => Env::int('OTP_MAX_ATTEMPTS', 5),
        ]);

        $sent = $this->emailService->sendOtp($email, $code, $lang);
        if (!$sent) {
            $this->otps->invalidateOpenOtps((string) $user['id']);
            throw new HttpException(500, $lang === 'en'
                ? 'OTP email could not be sent. Please verify SMTP settings.'
                : 'No se pudo enviar el correo OTP. Revisa la configuracion SMTP.');
        }
    }

    public function verifyOtp(string $email, string $code, string $lang): string
    {
        $email = strtolower(trim($email));
        $code = trim($code);

        if ($email === '' || $code === '') {
            throw new HttpException(422, I18n::t('validation_error', $lang), ['required' => ['email', 'code']]);
        }

        $user = $this->users->findByEmail($email);
        if (!$user || $user['role'] !== 'USER') {
            throw new HttpException(401, I18n::t('unauthorized', $lang));
        }

        $otp = $this->otps->latestOpenOtp((string) $user['id']);
        if (!$otp) {
            throw new HttpException(401, $lang === 'en' ? 'Invalid or expired code.' : 'Codigo invalido o expirado.');
        }

        if (strtotime((string) $otp['expires_at']) < time()) {
            throw new HttpException(401, $lang === 'en' ? 'Invalid or expired code.' : 'Codigo invalido o expirado.');
        }

        if ((int) $otp['attempts'] >= (int) $otp['max_attempts']) {
            throw new HttpException(429, $lang === 'en'
                ? 'Too many attempts. Request a new code.'
                : 'Demasiados intentos. Solicita un nuevo codigo.');
        }

        if (!password_verify($code, (string) $otp['code_hash'])) {
            $this->otps->incrementAttempts((string) $otp['id']);
            throw new HttpException(401, $lang === 'en' ? 'Invalid or expired code.' : 'Codigo invalido o expirado.');
        }

        $this->otps->markUsed((string) $otp['id']);

        $ttl = Env::int('JWT_RESET_TTL_MINUTES', 15);
        return Jwt::encode([
            'sub' => $user['id'],
            'type' => 'PASSWORD_RESET',
            'role' => $user['role'],
            'email' => $user['email'],
        ], (string) Env::get('JWT_SECRET', 'change-me'), $ttl);
    }

    public function resetPassword(string $token, string $newPassword, string $lang): void
    {
        if (strlen($newPassword) < 8) {
            throw new HttpException(422, I18n::t('validation_error', $lang), ['newPassword' => 'min_8']);
        }

        $payload = Jwt::decode($token, (string) Env::get('JWT_SECRET', 'change-me'));

        if (($payload['type'] ?? '') !== 'PASSWORD_RESET') {
            throw new HttpException(401, I18n::t('unauthorized', $lang));
        }

        $userId = (string) ($payload['sub'] ?? '');
        $user = $this->users->findById($userId);

        if (!$user || $user['role'] !== 'USER') {
            throw new HttpException(401, I18n::t('unauthorized', $lang));
        }

        $this->users->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT), false);
    }

    /**
     * @param array<string,mixed> $user
     */
    public function issueAccessToken(array $user): string
    {
        $ttl = Env::int('JWT_TTL_MINUTES', 60 * 24 * 7);
        return Jwt::encode([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'preferredLang' => $user['preferred_lang'] ?? 'es',
        ], (string) Env::get('JWT_SECRET', 'change-me'), $ttl);
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function sanitizeUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'isActive' => (int) $user['is_active'] === 1,
            'preferredLang' => $user['preferred_lang'],
            'mustChangePassword' => (int) $user['must_change_password'] === 1,
            'createdAt' => $user['created_at'],
            'updatedAt' => $user['updated_at'],
        ];
    }
}
