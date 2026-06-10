<?php

declare(strict_types=1);

namespace App\Domain\Notification\Exception;

class InvalidStatusTransitionException extends \DomainException
{
    public static function fromTo(string $from, string $to): self
    {
        return new self(sprintf(
            'Invalid status transition: "%s" -> "%s".',
            $from,
            $to,
        ));
    }
}
