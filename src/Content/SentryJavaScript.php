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
use Illuminate\Contracts\Container\Container;

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

    /**
     * @var Container
     */
    private $container;

    public function __construct(SettingsRepositoryInterface $settings, UrlGenerator $url, Container $container)
    {
        $this->settings = $settings;
        $this->url = $url;
        $this->container = $container;
    }

    public function __invoke(Document $document)
    {
        $useJs = (bool) (int) $this->settings->get('fof-sentry.javascript');

        if (!$useJs) {
            return;
        }

        $dsn = $this->settings->get('fof-sentry.dsn');

        // Get release from container
        $release = $this->container->make('sentry.release');
        
        // Get environment (custom or from settings)
        $environment = $this->container->bound('fof.sentry.environment') 
            ? $this->container->make('fof.sentry.environment')
            : (empty($this->settings->get('fof-sentry.environment')) 
                ? str_replace(['https://', 'http://'], '', $this->url->to('forum')->base()) 
                : $this->settings->get('fof-sentry.environment'));
                
        $showFeedback = (bool) (int) $this->settings->get('fof-sentry.user_feedback');
        $captureConsole = (bool) (int) $this->settings->get('fof-sentry.javascript.console');

        $shouldScrubEmailsFromUserData = !((bool) (int) $this->settings->get('fof-sentry.send_emails_with_sentry_reports'));

        $tracesSampleRate = (int) $this->settings->get('fof-sentry.javascript.trace_sample_rate');
        $replaysSessionSampleRate = (int) $this->settings->get('fof-sentry.javascript.replays_session_sample_rate');
        $replaysErrorSampleRate = (int) $this->settings->get('fof-sentry.javascript.replays_error_sample_rate');

        $tracesSampleRate = max(0, min(100, $tracesSampleRate)) / 100;
        $replaysSessionSampleRate = max(0, min(100, $replaysSessionSampleRate)) / 100;
        $replaysErrorSampleRate = max(0, min(100, $replaysErrorSampleRate)) / 100;

        // Base configuration
        $config = [
            'dsn' => $dsn,
            'environment' => $environment,
            'release' => $release,
            'scrubEmails' => $shouldScrubEmailsFromUserData,
            'showFeedback' => $showFeedback,
            'captureConsole' => $captureConsole,
            'tracesSampleRate' => $tracesSampleRate,
            'replaysSessionSampleRate' => $replaysSessionSampleRate,
            'replaysOnErrorSampleRate' => $replaysErrorSampleRate,
        ];

        // Merge with custom config
        if ($this->container->bound('fof.sentry.frontend.config')) {
            $customConfig = $this->container->make('fof.sentry.frontend.config');
            $config = array_merge($config, $customConfig);
        }

        // Add the config to the document payload
        $document->payload['fof-sentry'] = $config;
        $document->payload['fof-sentry.scrub-emails'] = (bool) $shouldScrubEmailsFromUserData;

        $document->foot[] = "
                <script>
                    if (window.Sentry) {
                        const client = Sentry.createClient({
                            dsn: '$dsn',
                            environment: '$environment',
                            scrubEmails: ".($shouldScrubEmailsFromUserData ? 'true' : 'false').',
                            showFeedback: '.($showFeedback ? 'true' : 'false').',

                            captureConsole: '.($captureConsole ? 'true' : 'false').",

                            tracesSampleRate: $tracesSampleRate,
                            replaysSessionSampleRate: $replaysSessionSampleRate,
                            replaysOnErrorSampleRate: $replaysErrorSampleRate,
                        });

                        Sentry.getCurrentHub().bindClient(client);
                    } else {
                        console.error('Unable to initialize Sentry');
                    }
                </script>";
    }
}
