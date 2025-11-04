<?php

declare(strict_types=1);

namespace X402\Events;

/**
 * Interface for event dispatchers.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch a payment event.
     *
     * @param PaymentEvent $event Event to dispatch
     * @return void
     */
    public function dispatch(PaymentEvent $event): void;

    /**
     * Register an event listener.
     *
     * @param string $eventName Event name to listen for
     * @param callable $listener Callback to invoke when event is dispatched
     * @return void
     */
    public function listen(string $eventName, callable $listener): void;
}
