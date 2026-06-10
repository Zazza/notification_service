<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateway;

final readonly class GatewayResult
{
    public function __construct(
        public bool $success,
        public ?string $gatewayId = null,
        public ?string $errorMessage = null,
    ) {
    }

    public static function success(string $gatewayId): self
    {
        return new self(success: true, gatewayId: $gatewayId);
    }

    public static function failure(string $errorMessage): self
    {
        return new self(success: false, errorMessage: $errorMessage);
    }
}
