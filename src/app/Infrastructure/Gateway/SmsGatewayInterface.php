<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateway;

interface SmsGatewayInterface
{
    public function send(string $recipientId, string $content): GatewayResult;
}
