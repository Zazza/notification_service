<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exception;

class DuplicateNotificationException extends \DomainException
{
    public static function withKey(string $key): self
    {
        return new self(sprintf('Notification with idempotency key "%s" already exists.', $key));
    }
}
