<?php

declare(strict_types=1);

namespace App\Services;

final class PaymentSimulatorService
{
    /**
     * @return array{status:string,reason:?string,last4:string,brand:string}
     */
    public function evaluate(string $cardNumber, ?string $cardBrand): array
    {
        $digits = preg_replace('/\D+/', '', $cardNumber) ?? '';
        $last4 = strlen($digits) >= 4 ? substr($digits, -4) : str_pad($digits, 4, '0', STR_PAD_LEFT);
        $lastDigit = (int) substr($digits !== '' ? $digits : '0', -1);

        $isSuccess = ($lastDigit % 2) === 0;

        return [
            'status' => $isSuccess ? 'SUCCESS' : 'FAILED',
            'reason' => $isSuccess ? null : 'SIMULATED_CARD_REJECTED',
            'last4' => $last4,
            'brand' => $cardBrand ? strtoupper(trim($cardBrand)) : 'SIMULATED',
        ];
    }
}
