<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tests\fixtures;

use Flarum\User\DisplayName\DriverInterface;
use Flarum\User\User;

/**
 * Core only ships a display name driver that returns the username verbatim, so
 * `display_name` and `username` can never diverge without another extension in
 * play. This driver makes them differ, which is what the `username_slug`
 * branch of the user context needs in order to be exercised.
 */
class PrefixedDisplayNameDriver implements DriverInterface
{
    public function displayName(User $user): string
    {
        return 'Display '.$user->username;
    }
}
