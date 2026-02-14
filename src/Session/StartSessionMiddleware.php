<?php

declare(strict_types=1);

namespace Denosys\Session;

use Denosys\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class StartSessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected readonly SessionManager $manager,
        protected readonly ?ContainerInterface $container = null
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $session = $this->manager->getSession();

        $session->start();

        $session->setPreviousUrl((string) $request->getUri());

        $request = $request->withAttribute(SessionAttributeKeys::SESSION, $session);

        // Re-bind the session-enabled request to the container so DI can resolve 
        // the updated request instance (with session).
        if ($this->container !== null) {
            $this->container->instance(ServerRequestInterface::class, $request);
        }

        $response = $handler->handle($request);

        $session->save();

        $this->manager->setSessionCookie($session);

        if ($this->manager->shouldGarbageCollect()) {
            $this->manager->garbageCollect();
        }

        return $response;
    }

    public function getManager(): SessionManager
    {
        return $this->manager;
    }
}
