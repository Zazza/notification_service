<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateway;

use Random\RandomException;

/**
 * Mock SMS gateway — simulates real provider behaviour.
 * Success rate ~90%, simulates random failures for retry testing.
 */
class MockSmsGateway implements SmsGatewayInterface
{
    private float $successRate;

    public function __construct(float $successRate = 0.9)
    {
        $this->successRate = $successRate;
    }

    /**
     * @throws RandomException
     */
    public function send(string $recipientId, string $content): GatewayResult
    {
        // Simulate invalid number
        if (str_starts_with($recipientId, 'INVALID')) {
            return GatewayResult::failure('Invalid phone number');
        }

        // Simulate random provider failure
        if (lcg_value() > $this->successRate) {
            return GatewayResult::failure('SMS provider temporarily unavailable');
        }

        $gatewayId = 'sms_' . bin2hex(random_bytes(8));

        return GatewayResult::success($gatewayId);
    }
}
