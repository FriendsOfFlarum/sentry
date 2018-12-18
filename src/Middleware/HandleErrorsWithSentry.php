<?php

namespace FoF\Sentry\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
        $status = 500;
        $errorCode = $error->getCode();

        // If it seems to be a valid HTTP status code, we pass on the
        // exception's status.
        if (is_int($errorCode) && $errorCode >= 400 && $errorCode < 600) {
            $status = $errorCode;
        }

        if ($status >= 500 && $status < 600) {
            $sentry = app('sentry');

            if ($sentry != null) {
                $user = $request->getAttribute('actor');

                if ($user != null) {
                    $sentry->user_context([
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                    ]);
                }

                $sentry->captureException($error);
            }
        }
    }
}
