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
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class SentryFormatter implements HttpFormatter
{
    /**
     * @var ViewFormatter
     */
    private $formatter;

    /**
     * @var ViewFactory
     */
    protected $view;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(ViewFormatter $formatter)
    {
        $this->formatter = $formatter;

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
        $user = RequestUtil::getActor(resolve('sentry.request'));
        $locale = $this->translator->getLocale();
        $eventId = $sentry->getLastEventId();

        // Build user data with proper escaping to prevent XSS
        $userData = '';
        if ($user != null && $user->id != 0) {
            $userDataArray = [
                'name' => $user->username,
            ];

            // Only include email if setting is enabled
            if ((bool) $settings->get('fof-sentry.send_emails_with_sentry_reports')) {
                $userDataArray['email'] = $user->email;
            }

            // Add user groups
            if (!$user->relationLoaded('groups')) {
                $user->load('groups');
            }
            $groups = $user->groups->pluck('name_singular')->filter()->all();
            if (!empty($groups)) {
                $userDataArray['groups'] = implode(', ', $groups);
            }

            // JSON encode for safe JavaScript embedding
            $userDataJson = json_encode($userDataArray, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $userData = "user: $userDataJson,";
        }

        // JSON encode all values for safe JavaScript embedding
        $configJson = json_encode([
            'dsn' => $dsn,
            'lang' => $locale,
            'eventId' => $eventId,
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
