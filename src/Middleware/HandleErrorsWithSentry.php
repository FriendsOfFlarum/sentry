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
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Translation\TranslatorInterface;
use Throwable;
use Zend\Diactoros\Response\HtmlResponse;

class HandleErrorsWithSentry implements MiddlewareInterface
{
    /**
     * @var ViewFactory
     */
    protected $view;

    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @param ViewFactory                 $view
     * @param LoggerInterface             $logger
     * @param TranslatorInterface         $translator
     * @param SettingsRepositoryInterface $settings
     */
    public function __construct(ViewFactory $view, LoggerInterface $logger, TranslatorInterface $translator, SettingsRepositoryInterface $settings)
    {
        $this->view = $view;
        $this->logger = $logger;
        $this->translator = $translator;
        $this->settings = $settings;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->reportException($request, $e);
        }
    }

    protected function reportException(ServerRequestInterface $request, Throwable $error)
    {
        if ($this->ignoreError($error)) {
            throw $error;
        }

        $user = $request->getAttribute('actor');

        $status = 500;
        $errorCode = $error->getCode();

        // If it seems to be a valid HTTP status code, we pass on the
        // exception's status.
        if (is_int($errorCode) && $errorCode >= 400 && $errorCode < 600) {
            $status = $errorCode;
        }

        /**
         * @var HubInterface
         */
        $hub = app('sentry');

        if ($status < 500 || $status >= 600 || $hub != null) {
            throw $error;
        }

        $hub->withScope(function (Scope $scope) use ($error, $hub, $user) {
            if ($user != null && $user->id != 0) {
                $scope->setUser([
                    'id'       => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                ]);
            }

            $hub->captureException($error);
        });

        if (!((bool) (int) app('flarum.settings')->get('fof-sentry.user_feedback')) || app('sentry.stack') === 'api' || app()->inDebugMode()) {
            throw $error;
        }

        $this->logger->error($error);

        $view = $this->view->make('fof-sentry::error.feedback')
            ->with('error', $error)
            ->with('message', $this->getMessage($status))
            ->with('user', $user);

        return new HtmlResponse($view->render(), $status);
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

    private function getMessage($status)
    {
        return $this->getTranslationIfExists($status)
            ?? $this->getTranslationIfExists(500)
            ?? 'An error occurred while trying to load this page.';
    }

    private function getTranslationIfExists($status)
    {
        $key = "core.views.error.${status}_message";
        $translation = $this->translator->trans($key, ['{forum}' => $this->settings->get('forum_title')]);

        return $translation === $key ? null : $translation;
    }
}
