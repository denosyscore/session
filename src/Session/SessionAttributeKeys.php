<?php

declare(strict_types=1);

namespace CFXP\Core\Session;

/**
 * Shared constants for session-related request attributes.
 *
 * Using this interface decouples components that need to access session
 * from the specific middleware implementation (StartSessionMiddleware).
 */
interface SessionAttributeKeys
{
    /**
     * The request attribute key where the session instance is stored.
     */
    public const SESSION = 'session';
}
