<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

/**
 * Trait for Workflow MarkingStore integration.
 * Symfony Workflow reads/writes the marking (status) via these methods.
 *
 * @property-read \BackedEnum $status Enum with string backing representing the workflow place.
 */
trait WorkflowSubjectTrait
{
    private string $statusValue = '';

    public function getStatusValue(): string
    {
        if ($this->statusValue === '') {
            return $this->status->value;
        }

        return $this->statusValue;
    }

    public function setStatusValue(string $statusValue): void
    {
        $this->statusValue = $statusValue;
    }
}
