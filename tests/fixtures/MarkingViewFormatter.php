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

use Flarum\Foundation\ErrorHandling\HandledError;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Stands in for another extension that customises Flarum's HTML error page.
 */
class MarkingViewFormatter extends ViewFormatter
{
    public const MARKER = '<!-- customised by another extension -->';

    public function format(HandledError $error, Request $request): Response
    {
        $response = parent::format($error, $request);
        $body = $response->getBody();
        $body->seek($body->getSize());
        $body->write(self::MARKER);

        return $response;
    }
}
