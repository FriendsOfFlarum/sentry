<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class UserContext
{
    public function __construct(protected SettingsRepositoryInterface $settings)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function fromRequest(ServerRequestInterface $request): array
    {
        $data = [];

        if ($ipAddress = $request->getAttribute('ipAddress')) {
            $data['ip_address'] = $ipAddress;
        }

        $user = RequestUtil::getActor($request);

        if ($user->isGuest()) {
            return $data;
        }

        $data['id'] = $user->id;
        $data['username'] = $user->display_name;

        if ($user->display_name !== $user->username) {
            $data['username_slug'] = $user->username;
        }

        if ((bool) $this->settings->get('fof-sentry.send_emails_with_sentry_reports')) {
            $data['email'] = $user->email;
        }

        // Runs while an event is being captured, possibly during shutdown: a failed
        // groups lookup must never prevent the event itself from being sent.
        try {
            $groups = $user->groups->pluck('name_singular')->filter()->all();

            if (!empty($groups)) {
                $data['groups'] = implode(', ', $groups);
            }
        } catch (Throwable) {
        }

        return $data;
    }
}
