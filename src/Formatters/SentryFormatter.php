<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Formatters;

use Flarum\Foundation\ErrorHandling\HandledError;
use Flarum\Foundation\ErrorHandling\HttpFormatter;
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SentryFormatter implements HttpFormatter
{
    /**
     * @var ViewFactory
     */
    protected $view;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(private ViewFormatter $formatter)
    {
        $this->view = resolve(ViewFactory::class);
        $this->translator = resolve(TranslatorInterface::class);
    }

    public function format(HandledError $error, Request $request): Response
    {
        $response = $this->formatter->format($error, $request);

        /** @var SettingsRepositoryInterface */
        $settings = resolve(SettingsRepositoryInterface::class);
        $sentry = resolve('sentry');

        if (!$error->shouldBeReported() || $sentry == null || $sentry->getLastEventId() == null || !((bool) (int) $settings->get('fof-sentry.user_feedback'))) {
            return $response;
        }

        $dsn = $settings->get('fof-sentry.dsn');
        $user = resolve('sentry.request')->getAttribute('actor');
        $locale = $this->translator->getLocale();
        $eventId = $sentry->getLastEventId();
        $userData = ($user != null && $user->id != 0) ?
            "user: {
                email: '$user->email',
                name: '$user->username'
            }" : '';

        $body = $response->getBody();

        $body->seek($body->getSize());

        $body->write("
            <script src=\"https://browser.sentry-cdn.com/5.25.0/bundle.min.js\" integrity=\"sha384-2p7fXoWSRPG49ZgmmJlTEI/01BY1LgxCNFQFiWpImAERmS/bROOQm+cJMdq/kmWS\" crossorigin=\"anonymous\"></script>

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
