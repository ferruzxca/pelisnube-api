<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use Throwable;

final class EmailService
{
    public function sendOtp(string $email, string $code, string $lang): bool
    {
        $subject = $lang === 'en' ? 'PelisNube password recovery code' : 'Codigo de recuperacion PelisNube';
        $body = $lang === 'en'
            ? "Your one-time code is: {$code}. It expires in a few minutes."
            : "Tu codigo temporal es: {$code}. Expira en unos minutos.";

        $toEmail = $this->normalizeEmailAddress($email);
        if ($toEmail === null) {
            $this->log('Invalid OTP recipient email: ' . $email);
            return false;
        }

        $fromName = $this->sanitizeHeaderValue((string) Env::get('SMTP_FROM_NAME', 'PelisNube'));
        $fromEmail = $this->normalizeEmailAddress((string) Env::get('SMTP_FROM_EMAIL', ''));

        if ($fromEmail === null) {
            $fromEmail = $this->normalizeEmailAddress((string) Env::get('SMTP_USER', ''));
        }
        if ($fromEmail === null) {
            $fromEmail = 'no-reply@example.com';
        }

        if ($this->hasPartialSmtpConfig()) {
            $this->log('SMTP config is incomplete. Set SMTP_HOST, SMTP_USER and SMTP_PASS.');
            return false;
        }

        if ($this->hasReadySmtpConfig()) {
            $sent = $this->sendViaSmtp($toEmail, $subject, $body, $fromName, $fromEmail);
            if (!$sent) {
                $this->log('SMTP delivery failed for ' . $toEmail);
            }
            return $sent;
        }

        $sent = $this->sendViaPhpMail($toEmail, $subject, $body, $fromName, $fromEmail);
        if (!$sent) {
            $this->log(sprintf('OTP mail failed for %s. Code: %s', $toEmail, $code));
        }

        return $sent;
    }

    private function hasReadySmtpConfig(): bool
    {
        $host = trim((string) Env::get('SMTP_HOST', ''));
        $user = trim((string) Env::get('SMTP_USER', ''));
        $pass = trim((string) Env::get('SMTP_PASS', ''));

        return $host !== '' && $user !== '' && $pass !== '';
    }

    private function hasPartialSmtpConfig(): bool
    {
        $host = trim((string) Env::get('SMTP_HOST', ''));
        $user = trim((string) Env::get('SMTP_USER', ''));
        $pass = trim((string) Env::get('SMTP_PASS', ''));

        $anySet = $host !== '' || $user !== '' || $pass !== '';
        if (!$anySet) {
            return false;
        }

        $allSet = $host !== '' && $user !== '' && $pass !== '';
        if ($allSet) {
            return false;
        }

        return true;
    }

    private function isTlsEnabled(string $secure): bool
    {
        if ($secure === 'tls') {
            return true;
        }

        if ($secure === 'ssl' || $secure === 'none') {
            return false;
        }

        return Env::bool('SMTP_USE_TLS', true);
    }

    private function isSslEnabled(string $secure): bool
    {
        if ($secure === 'ssl') {
            return true;
        }

        if ($secure === 'tls' || $secure === 'none') {
            return false;
        }

        return Env::bool('SMTP_USE_SSL', false);
    }

    private function resolveSmtpSecureMode(): string
    {
        $secure = strtolower(trim((string) Env::get('SMTP_SECURE', '')));
        if (in_array($secure, ['tls', 'ssl', 'none'], true)) {
            return $secure;
        }

        if (Env::bool('SMTP_USE_SSL', false)) {
            return 'ssl';
        }

        if (Env::bool('SMTP_USE_TLS', true)) {
            return 'tls';
        }

        return 'none';
    }

    private function resolveSmtpClientHost(): string
    {
        $clientHost = trim((string) Env::get('SMTP_CLIENT_HOST', ''));
        if ($clientHost !== '') {
            return $clientHost;
        }

        $clientHost = (string) parse_url((string) Env::get('APP_URL', 'http://localhost'), PHP_URL_HOST);
        if ($clientHost !== '') {
            return $clientHost;
        }

        return 'localhost';
    }

    private function resolveSmtpTargetHost(string $host, string $secure): string
    {
        if ($this->isSslEnabled($secure)) {
            return 'ssl://' . $host;
        }

        return $host;
    }

    private function sendViaPhpMail(string $toEmail, string $subject, string $body, string $fromName, string $fromEmail): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
        ];

        return @mail($toEmail, $subject, $body, implode("\r\n", $headers));
    }

    private function sendViaSmtp(
        string $toEmail,
        string $subject,
        string $body,
        string $fromName,
        string $fromEmail
    ): bool {
        $host = trim((string) Env::get('SMTP_HOST', ''));
        $port = Env::int('SMTP_PORT', 587);
        $timeout = max(5, Env::int('SMTP_TIMEOUT_SECONDS', 15));
        $secure = $this->resolveSmtpSecureMode();
        $username = trim((string) Env::get('SMTP_USER', ''));
        $password = (string) Env::get('SMTP_PASS', '');
        $clientHost = $this->resolveSmtpClientHost();

        $targetHost = $this->resolveSmtpTargetHost($host, $secure);
        $target = sprintf('%s:%d', $targetHost, $port);

        $socket = @stream_socket_client(
            $target,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            $this->log(sprintf('SMTP connect error %s (%d) to %s', $errstr, $errno, $target));
            return false;
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->expectResponse($socket, [220], 'connect');
            $this->sendLine($socket, 'EHLO ' . $clientHost);
            $this->expectResponse($socket, [250], 'EHLO');

            if ($this->isTlsEnabled($secure)) {
                $this->sendLine($socket, 'STARTTLS');
                $this->expectResponse($socket, [220], 'STARTTLS');

                $cryptoEnabled = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );
                if ($cryptoEnabled !== true) {
                    throw new \RuntimeException('TLS negotiation failed');
                }

                $this->sendLine($socket, 'EHLO ' . $clientHost);
                $this->expectResponse($socket, [250], 'EHLO after STARTTLS');
            }

            if ($username !== '' || $password !== '') {
                if ($username === '' || $password === '') {
                    throw new \RuntimeException('SMTP auth requires user and pass');
                }

                $this->sendLine($socket, 'AUTH LOGIN');
                $this->expectResponse($socket, [334], 'AUTH LOGIN');
                $this->sendLine($socket, base64_encode($username));
                $this->expectResponse($socket, [334], 'AUTH USER');
                $this->sendLine($socket, base64_encode($password));
                $this->expectResponse($socket, [235], 'AUTH PASS');
            }

            $this->sendLine($socket, 'MAIL FROM:<' . $fromEmail . '>');
            $this->expectResponse($socket, [250, 251], 'MAIL FROM');
            $this->sendLine($socket, 'RCPT TO:<' . $toEmail . '>');
            $this->expectResponse($socket, [250, 251], 'RCPT TO');
            $this->sendLine($socket, 'DATA');
            $this->expectResponse($socket, [354], 'DATA');

            $encodedSubject = $this->encodeMimeHeader($subject);
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $fromName . ' <' . $fromEmail . '>',
                'To: <' . $toEmail . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
            $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody) ?? $normalizedBody;
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $normalizedBody);

            if (@fwrite($socket, $payload . "\r\n.\r\n") === false) {
                throw new \RuntimeException('SMTP payload write failed');
            }
            $this->expectResponse($socket, [250], 'MAIL DATA');

            $this->sendLine($socket, 'QUIT');
            $this->expectResponse($socket, [221, 250], 'QUIT');

            return true;
        } catch (Throwable $e) {
            $this->log('SMTP send error: ' . $e->getMessage());
            return false;
        } finally {
            @fclose($socket);
        }
    }

    private function sendLine($socket, string $line): void
    {
        if (@fwrite($socket, $line . "\r\n") === false) {
            throw new \RuntimeException('SMTP command write failed');
        }
    }

    /**
     * @param array<int,int> $expectedCodes
     */
    private function expectResponse($socket, array $expectedCodes, string $step): void
    {
        [$code, $response] = $this->readResponse($socket);
        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException(sprintf(
                '%s failed with %d: %s',
                $step,
                $code,
                trim($response)
            ));
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function readResponse($socket): array
    {
        $code = 0;
        $response = '';

        while (($line = @fgets($socket, 2048)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})([ -])/', $line, $matches) === 1) {
                $code = (int) $matches[1];
                if ($matches[2] === ' ') {
                    break;
                }
            }
        }

        if ($response === '') {
            throw new \RuntimeException('SMTP empty response');
        }

        return [$code, $response];
    }

    private function normalizeEmailAddress(string $email): ?string
    {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $email;
    }

    private function sanitizeHeaderValue(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $value));
        return $value === '' ? 'PelisNube' : $value;
    }

    private function encodeMimeHeader(string $text): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            $encoded = @mb_encode_mimeheader($text, 'UTF-8', 'B', "\r\n");
            if (is_string($encoded) && $encoded !== '') {
                return $encoded;
            }
        }

        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    private function log(string $message): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
        @file_put_contents($logDir . '/mail.log', $line, FILE_APPEND);
    }
}
