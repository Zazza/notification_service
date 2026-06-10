<?php

declare(strict_types=1);

namespace App\Infrastructure\Gateway;

interface EmailGatewayInterface
{
    public function send(string $recipientId, string $content): GatewayResult;
}
