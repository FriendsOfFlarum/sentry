<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Renderers;

use Flarum\Foundation\ErrorHandling\Formatter;
use Flarum\Foundation\ErrorHandling\HandledError;
use Flarum\Foundation\ErrorHandling\ViewRenderer;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Component\Translation\TranslatorInterface;

class SentryRenderer implements Formatter
{
    /**
     * @var ViewRenderer
     */
    private $renderer;

    /**
     * @var ViewFactory
     */
    protected $view;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(ViewRenderer $renderer)
    {
        $this->renderer = $renderer;

        $this->view = app(ViewFactory::class);
        $this->translator = app(TranslatorInterface::class);
    }

    public function format(HandledError $error, Request $request): Response
    {
        $response = $this->renderer->format($error, $request);
        $sentry = app('sentry');

        if (!$error->shouldBeReported() || $sentry == null || $sentry->getLastEventId() == null) {
            return $response;
        }

        $dsn = app('flarum.settings')->get('fof-sentry.dsn');
        $user = app('sentry.request')->getAttribute('actor');
        $locale = $this->translator->getLocale();
        $eventId =  $sentry->getLastEventId();
        $userData = ($user != null && $user->id != 0) ?
            "user: {
                email: '$user->email',
                name: '$user->username'
            }" : '';

        $body = $response->getBody();

        $body->seek($body->getSize());

        $body->write("
            <script src=\"https://browser.sentry-cdn.com/5.6.1/bundle.min.js\" integrity=\"sha384-pGTFmbQfua2KiaV2+ZLlfowPdd5VMT2xU4zCBcuJr7TVQozMO+I1FmPuVHY3u8KB\" crossorigin=\"anonymous\"></script>
            
            <script>
                Sentry.init({ dsn: '$dsn' });
                Sentry.showReportDialog({
                    lang: '$locale',
                    eventId: '$eventId',
                    $userData
                });
            </script>
        ");

        return $response;
    }
}
