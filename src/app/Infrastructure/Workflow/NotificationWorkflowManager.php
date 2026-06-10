<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow;

use App\Domain\Notification\Entity\Notification;
use App\Domain\Notification\ValueObject\NotificationStatus;
use LogicException;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

class NotificationWorkflowManager
{
    private ?WorkflowInterface $workflow = null;

    public function getWorkflow(): WorkflowInterface
    {
        if ($this->workflow === null) {
            $this->workflow = $this->buildWorkflow();
        }

        return $this->workflow;
    }

    public function applyTransition(Notification $notification, string $transitionName): void
    {
        $workflow = $this->getWorkflow();

        if (!$workflow->can($notification, $transitionName)) {
            throw new LogicException(sprintf(
                'Transition "%s" is not allowed from status "%s".',
                $transitionName,
                $notification->getStatus()->value,
            ));
        }

        $workflow->apply($notification, $transitionName);
    }

    public function canTransition(Notification $notification, string $transitionName): bool
    {
        return $this->getWorkflow()->can($notification, $transitionName);
    }

    /**
     * Discard from any non-final status (queued or sent).
     */
    public function applyDiscard(Notification $notification): void
    {
        foreach (['discard_from_queued', 'discard_from_sent'] as $transition) {
            if ($this->getWorkflow()->can($notification, $transition)) {
                $this->getWorkflow()->apply($notification, $transition);
                return;
            }
        }

        throw new LogicException(sprintf(
            'Cannot discard notification from status "%s".',
            $notification->getStatus()->value,
        ));
    }

    public function canDiscard(Notification $notification): bool
    {
        return $this->getWorkflow()->can($notification, 'discard_from_queued')
            || $this->getWorkflow()->can($notification, 'discard_from_sent');
    }

    /**
     * Build the state machine definition:
     *
     *   queued ──send──> sent ──confirm_delivery──> delivered
     *     │                │
     *     └──discard──────┴──discard──> discarded
     */
    private function buildWorkflow(): WorkflowInterface
    {
        $definitionBuilder = new DefinitionBuilder();

        $places = [
            NotificationStatus::QUEUED->value,
            NotificationStatus::SENT->value,
            NotificationStatus::DELIVERED->value,
            NotificationStatus::DISCARDED->value,
        ];

        $placesMetadata = [
            NotificationStatus::QUEUED->value => ['label' => 'В очереди'],
            NotificationStatus::SENT->value => ['label' => 'Отправлено'],
            NotificationStatus::DELIVERED->value => ['label' => 'Доставлено'],
            NotificationStatus::DISCARDED->value => ['label' => 'Отброшено'],
        ];

        $transitions = [];
        $transitionsMetadata = [];

        $transitions[] = new Transition('send', NotificationStatus::QUEUED->value, NotificationStatus::SENT->value);
        $transitionsMetadata['send'] = ['label' => 'Отправить'];

        $transitions[] = new Transition('confirm_delivery', NotificationStatus::SENT->value, NotificationStatus::DELIVERED->value);
        $transitionsMetadata['confirm_delivery'] = ['label' => 'Подтвердить доставку'];

        $transitions[] = new Transition('discard_from_queued', NotificationStatus::QUEUED->value, NotificationStatus::DISCARDED->value);
        $transitionsMetadata['discard_from_queued'] = ['label' => 'Отбросить из очереди'];

        $transitions[] = new Transition('discard_from_sent', NotificationStatus::SENT->value, NotificationStatus::DISCARDED->value);
        $transitionsMetadata['discard_from_sent'] = ['label' => 'Отбросить после отправки'];

        $metadataStore = new InMemoryMetadataStore($placesMetadata, $transitionsMetadata);

        $definition = $definitionBuilder
            ->addPlaces($places)
            ->addTransitions($transitions)
            ->setInitialPlaces(NotificationStatus::QUEUED->value)
            ->setMetadataStore($metadataStore)
            ->build();

        $markingStore = new MethodMarkingStore(
            singleState: true,
            property: 'statusValue',
        );

        return new StateMachine($definition, $markingStore);
    }
}
