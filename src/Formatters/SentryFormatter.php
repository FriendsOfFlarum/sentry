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
use Flarum\Foundation\ErrorHandling\ViewFormatter;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sentry\State\HubInterface;

/**
 * Decorates whichever ViewFormatter is in use (core's, or another extension's) by appending the feedback dialog.
 * It still extends ViewFormatter because core type-hints that class (e.g. WhoopsFormatter).
 */
class SentryFormatter extends ViewFormatter
{
    public function __construct(
        protected ViewFormatter $inner,
        ViewFactory $view,
        TranslatorInterface $translator,
        SettingsRepositoryInterface $settings
    ) {
        parent::__construct($view, $translator, $settings);
    }

    public function format(HandledError $error, Request $request): Response
    {
        $response = $this->inner->format($error, $request);

        /** @var SettingsRepositoryInterface $settings */
        $settings = resolve(SettingsRepositoryInterface::class);

        if (!$error->shouldBeReported() || !((bool) (int) $settings->get('fof-sentry.user_feedback'))) {
            return $response;
        }

        // The dialog runs in the visitor's browser, so it always uses the primary DSN: the
        // backend DSN may be a Relay that only the server can reach. A Relay DSN shares the
        // primary DSN's project, so the feedback still attaches to the backend event.
        $dsn = $settings->get('fof-sentry.dsn');
        $eventId = resolve(HubInterface::class)->getLastEventId();

        if (!$dsn || $eventId === null) {
            return $response;
        }

        $user = RequestUtil::getActor($request);
        $userData = '';

        if (!$user->isGuest()) {
            $userDataArray = [
                'name' => $user->display_name,
            ];

            if ((bool) $settings->get('fof-sentry.send_emails_with_sentry_reports')) {
                $userDataArray['email'] = $user->email;
            }

            // JSON encode for safe JavaScript embedding
            $userDataJson = json_encode($userDataArray, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $userData = "user: $userDataJson,";
        }

        $configJson = json_encode([
            'dsn'     => (string) $dsn,
            'lang'    => $this->translator->getLocale(),
            'eventId' => (string) $eventId,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $body = $response->getBody();

        $body->seek($body->getSize());

        $body->write("
            <script src=\"https://browser.sentry-cdn.com/10.0.0/bundle.min.js\" crossorigin=\"anonymous\"></script>

            <script>
                (function() {
                    var config = $configJson;
                    Sentry.init({ dsn: config.dsn });
                    Sentry.showReportDialog({
                        lang: config.lang,
                        eventId: config.eventId,
                        $userData
                    });
                })();
            </script>
        ");

        return $response;
    }
}
