<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class HandleErrorsWithSentry implements MiddlewareInterface
{
    public function __construct(public Container $container)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->container->instance('sentry.request', $request);

        // Set user context for Sentry if HubInterface is bound (meaning Sentry is initialized)
        if ($this->container->bound(HubInterface::class)) {
            $this->setUserContext($request);
        }

        return $handler->handle($request);
    }

    /**
     * Set user context for Sentry events.
     */
    protected function setUserContext(ServerRequestInterface $request): void
    {
        /** @var HubInterface $hub */
        $hub = $this->container->make(HubInterface::class);

        /** @var SettingsRepositoryInterface $settings */
        $settings = $this->container->make(SettingsRepositoryInterface::class);

        $hub->configureScope(function (Scope $scope) use ($request, $settings) {
            $user = RequestUtil::getActor($request);

            if (!$user->isGuest() && $user->id !== 0) {
                $data = $user->only('id', 'username');

                // Only send email if enabled in settings
                if ((bool) $settings->get('fof-sentry.send_emails_with_sentry_reports')) {
                    $data['email'] = $user->email;
                }

                // Add user groups (load the relationship if not already loaded)
                if (!$user->relationLoaded('groups')) {
                    $user->load('groups');
                }

                $groups = $user->groups->pluck('name_singular')->filter()->all();

                if (!empty($groups)) {
                    $data['groups'] = implode(', ', $groups);
                }

                $scope->setUser($data);
            }
        });
    }
}
