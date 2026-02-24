<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Reporters;

use Flarum\Foundation\ErrorHandling\Reporter;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;

class SentryReporter implements Reporter
{
    public function __construct(protected LoggerInterface $logger, private Container $container)
    {
    }

    public function report(Throwable $error): void
    {
        // Check if Sentry is configured before trying to report
        if (!$this->container->bound(HubInterface::class)) {
            return;
        }

        /** @var HubInterface $hub */
        $hub = $this->container->make(HubInterface::class);

        if ($this->container->bound('sentry.request')) {
            $hub->configureScope(function (Scope $scope) {
                $request = $this->container->make('sentry.request');
                $user = RequestUtil::getActor($request);

                $data = [];

                $ipAddress = $request->getAttribute('ipAddress');
                if ($ipAddress) {
                    $data['ip_address'] = $ipAddress;
                }

                if (!$user->isGuest() && $user->id !== 0) {
                    $data['id'] = $user->id;
                    $data['username'] = $user->display_name;

                    if ($user->display_name !== $user->username) {
                        $data['username_slug'] = $user->username;
                    }

                    // Only send email if enabled in settings
                    if ((bool) resolve('flarum.settings')->get('fof-sentry.send_emails_with_sentry_reports')) {
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
                }

                if (!empty($data)) {
                    $scope->setUser($data);
                }
            });
        }

        $id = $hub->captureException($error);

        if ($id === null) {
            $this->logger->warning('[fof/sentry] exception of type '.get_class($error).' failed to send');
        }
    }
}
