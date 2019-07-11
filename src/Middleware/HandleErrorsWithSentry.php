<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Middleware;

use Flarum\Api\Exception\InvalidAccessTokenException;
use Flarum\Foundation\ValidationException;
use Flarum\Http\Exception\ForbiddenException;
use Flarum\Http\Exception\MethodNotAllowedException;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\Post\Exception\FloodingException;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Throwable;

class HandleErrorsWithSentry implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $this->reportException($request, $e);

            throw $e;
        }
    }

    protected function reportException(ServerRequestInterface $request, Throwable $error)
    {
        if ($this->ignoreError($error)) {
            return;
        }

        $user = $request->getAttribute('actor');

        $status = 500;
        $errorCode = $error->getCode();

        // If it seems to be a valid HTTP status code, we pass on the
        // exception's status.
        if (is_int($errorCode) && $errorCode >= 400 && $errorCode < 600) {
            $status = $errorCode;
        }

        if ($status >= 500 && $status < 600) {
            if (app()->bound('sentry')) {
                /**
                 * @var $hub HubInterface
                 */
                $hub = app('sentry');

                if ($user != null) {
                    $hub->withScope(function (Scope $scope) use ($error, $hub, $user) {
                        $scope->setUser([
                            'id'       => $user->id,
                            'username' => $user->username,
                            'email'    => $user->email,
                        ]);

                        $hub->captureException($error);
                    });
                };
            }
        }
    }

    private function ignoreError(Throwable $error)
    {
        return $error instanceof ForbiddenException
            || $error instanceof FloodingException
            || $error instanceof \Illuminate\Validation\ValidationException
            || $error instanceof InvalidAccessTokenException
            || $error instanceof InvalidConfigurationException
            || $error instanceof MethodNotAllowedException
            || $error instanceof ModelNotFoundException
            || $error instanceof PermissionDeniedException
            || $error instanceof RouteNotFoundException
            || $error instanceof TokenMismatchException
            || $error instanceof ValidationException;
    }
}
