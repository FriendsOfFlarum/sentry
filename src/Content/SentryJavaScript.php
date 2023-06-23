<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Content;

use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;

class SentryJavaScript
{
    /**
     * @var SettingsRepositoryInterface
     */
    private $settings;

    /**
     * @var UrlGenerator
     */
    private $url;

    public function __construct(SettingsRepositoryInterface $settings, UrlGenerator $url)
    {
        $this->settings = $settings;
        $this->url = $url;
    }

    public function __invoke(Document $document)
    {
        $useJs = (bool) (int) $this->settings->get('fof-sentry.javascript');

        if (!$useJs) {
            return;
        }

        $dsn = $this->settings->get('fof-sentry.dsn');
        $environment = empty($this->settings->get('fof-sentry.environment')) ? str_replace(['https://', 'http://'], '', $this->url->to('forum')->base()) : $this->settings->get('fof-sentry.environment');
        $showFeedback = (bool) (int) $this->settings->get('fof-sentry.user_feedback');
        $captureConsole = (bool) (int) $this->settings->get('fof-sentry.javascript.console');

        $shouldScrubEmailsFromUserData = !((bool) (int) $this->settings->get('fof-sentry.send_emails_with_sentry_reports'));

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
                        const client = Sentry.createClient({
                            dsn: '$dsn',
                            environment: '$environment',
                            scrubEmails: ".($shouldScrubEmailsFromUserData ? 'true' : 'false').",
                            showFeedback: ".($showFeedback ? 'true' : 'false').",

                            captureConsole: ".($captureConsole ? 'true' : 'false').",
                            tracesSampleRate: $tracesSampleRate,
                        });

                        Sentry.getCurrentHub().bindClient(client);
                    } else {
                        console.error('Unable to initialize Sentry');
                    }
                </script>";
    }
}
