<?php

namespace Tests\Support;

use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\SentrySdk;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Turns on the app's real Sentry client with a fake DSN and keeps the events it would have
 * sent, so tests can check what reaches Sentry without any network traffic.
 */
class SentrySpy implements TransportInterface
{
    /** @var list<Event> */
    public array $events = [];

    public static function install(): self
    {
        $spy = new self;
        $hub = SentrySdk::getCurrentHub();
        $options = clone $hub->getClient()->getOptions();
        $options->updateOptions(['dsn' => 'https://public@o1.ingest.de.sentry.io/1']);

        $hub->bindClient((new ClientBuilder($options))->setTransport($spy)->getClient());

        return $spy;
    }

    public function send(Event $event): Result
    {
        $this->events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }

    /**
     * @return list<Event>
     */
    public function errors(): array
    {
        return array_values(array_filter($this->events, fn (Event $event): bool => $event->getExceptions() !== []));
    }
}
