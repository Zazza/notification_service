<?php

declare(strict_types=1);

namespace App\Domain\Notification\ValueObject;

use Ramsey\Uuid\Uuid;

final readonly class NotificationId
{
    public function __construct(private string $value)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $id): self
    {
        return new self($id);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
