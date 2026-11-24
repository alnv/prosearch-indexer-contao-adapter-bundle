<?php

declare(strict_types=1);

namespace Alnv\ProSearchIndexerContaoAdapterBundle\Proxy\Exception;

/**
 * The proxy rate limit was exceeded (HTTP 429).
 */
class ProxyRateLimitException extends ProxyException
{
    public function __construct(string $message, private readonly int $retryAfter = 0, array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 429, 'rate_limit_exceeded', $details, $previous);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
