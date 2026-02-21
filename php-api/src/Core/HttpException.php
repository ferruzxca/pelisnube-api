<?php

declare(strict_types=1);

namespace App\Core;

use Exception;

final class HttpException extends Exception
{
    /** @var array<string, mixed> */
    private array $details;

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(int $statusCode, string $message, array $details = [])
    {
        parent::__construct($message, $statusCode);
        $this->details = $details;
    }

    public function statusCode(): int
    {
        return $this->getCode();
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
