<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2020 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Content;

use Flarum\Frontend\Document;
use Flarum\Settings\SettingsRepositoryInterface;

class SentryJavaScript
{
    /**
     * @var SettingsRepositoryInterface
     */
    private $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function __invoke(Document $document)
    {
        $useJs = (bool) (int) $this->settings->get('fof-sentry.javascript');

        if (!$useJs) {
            return;
        }

        $dsn = $this->settings->get('fof-sentry.dsn');
        $showFeedback = (bool) (int) $this->settings->get('fof-sentry.user_feedback');
        $captureConsole = (bool) (int) $this->settings->get('fof-sentry.javascript.console');

        $tracesSampleRate = (int) $this->settings->get('fof-sentry.javascript.trace_sample_rate', 0);
        $tracesSampleRate /= 100;

        // This needs to be between 0 and 1
        if ($tracesSampleRate > 1) {
            $tracesSampleRate = 1;
        }
        if ($tracesSampleRate < 0) {
            $tracesSampleRate = 0;
        }

        $document->foot[] = "
                <script>
                    if (window.Sentry) {
                        Sentry.init({
                            dsn: '$dsn',
                            beforeSend: function(event) {
                                event.logger = 'javascript';
                                // Check if it is an exception, and if so, show the report dialog
                                if (event.exception && ".($showFeedback ? 'true' : 'false').") {
                                    Sentry.showReportDialog({ eventId: event.event_id, user: Sentry.getUserData && Sentry.getUserData('name') });
                                }
                                return event;
                            },
                            defaultIntegrations: false,
                            tracesSampleRate: $tracesSampleRate,
                            integrations: [
                                ".($tracesSampleRate > 0 ? 'new Sentry.TracingIntegrations.BrowserTracing(),' : '')."
                                new Sentry.Integrations.InboundFilters(),
                                new Sentry.Integrations.FunctionToString(),
                                new Sentry.Integrations.GlobalHandlers({
                                    onerror: true,
                                    onunhandledrejection: true
                                }),
                                new Sentry.Integrations.Breadcrumbs({
                                    'console': true,
                                    dom: true,
                                    fetch: true,
                                    history: true,
                                    sentry: true,
                                    xhr: true
                                }),
                                new Sentry.Integrations.LinkedErrors({
                                    key: 'cause',
                                    limit: 5,
                                }),
                                new Sentry.Integrations.UserAgent(),
                                ".($captureConsole ? 'new Sentry.Integrations.CaptureConsole(),' : '')."
                            ]
                        });

                        if (Sentry.getUserData) Sentry.setUser(Sentry.getUserData());
                    } else {
                        console.error('Unable to initialize Sentry');
                    }
                </script>";
    }
}
