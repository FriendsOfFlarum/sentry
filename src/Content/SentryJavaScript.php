<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) 2018 FriendsOfFlarum.
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

        $document->foot[] = "
                <script>
                    if (window.Sentry) {
                        Sentry.init({
                            dsn: '$dsn',
                            beforeSend(event) {
                                event.logger = 'javascript';
                                // Check if it is an exception, and if so, show the report dialog
                                if (event.exception && ".($showFeedback ? 'true' : 'false').") {
                                    Sentry.showReportDialog({ eventId: event.event_id, user: Sentry.getUserData('name') });
                                }
                                return event;
                            },
                            integrations: [
                                new Sentry.Integrations.GlobalHandlers({
                                    onerror: true,
                                    onunhandledrejection: true
                                }),
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
