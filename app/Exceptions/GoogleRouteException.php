<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class GoogleRouteException extends RuntimeException
{
    public const CONFIGURATION = 'configuration';

    public const NO_ROUTE = 'no_route';

    public const REQUEST_REJECTED = 'request_rejected';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return $this->reason === self::UNAVAILABLE;
    }
}
