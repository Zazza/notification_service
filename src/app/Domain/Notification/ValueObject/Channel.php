<?php

declare(strict_types=1);

namespace App\Domain\Notification\ValueObject;

enum Channel: string
{
    case SMS = 'sms';
    case EMAIL = 'email';

    public function getLabel(): string
    {
        return match ($this) {
            self::SMS => 'SMS',
            self::EMAIL => 'Email',
        };
    }
}
