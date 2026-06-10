<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exception;

class InvalidChannelException extends \DomainException
{
    public static function withValue(string $value): self
    {
        return new self(sprintf('Invalid notification channel: "%s". Allowed: sms, email.', $value));
    }
}
