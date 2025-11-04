<?php

declare(strict_types=1);

namespace X402\Events;

/**
 * Simple in-memory event dispatcher.
 */
class SimpleEventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<callable>> */
    private array $listeners = [];

    /**
     * @inheritDoc
     */
    public function dispatch(PaymentEvent $event): void
    {
        $eventName = $event->getEventName();
        
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            $listener($event);
        }
    }

    /**
     * @inheritDoc
     */
    public function listen(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Remove all listeners for an event.
     *
     * @param string $eventName Event name
     * @return void
     */
    public function clearListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
    }

    /**
     * Remove all listeners.
     *
     * @return void
     */
    public function clearAllListeners(): void
    {
        $this->listeners = [];
    }
}
