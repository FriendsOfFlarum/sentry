<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class Sentry implements ExtenderInterface
{
    private $customRelease = null;
    private $customEnvironment = null;
    private $tags = [];
    private $sendTestMessage = false;
    private $sendTestException = false;

    /**
     * Set a custom release version.
     *
     * @param string $release The release version to use
     *
     * @return self
     */
    public function setRelease(string $release): self
    {
        $this->customRelease = $release;

        return $this;
    }

    /**
     * Set a custom environment name.
     *
     * @param string $environment The environment name to use
     *
     * @return self
     */
    public function setEnvironment(string $environment): self
    {
        $this->customEnvironment = $environment;

        return $this;
    }

    /**
     * Add a tag that will be sent with all events.
     *
     * @param string $key   Tag key
     * @param string $value Tag value
     *
     * @return self
     */
    public function addTag(string $key, string $value): self
    {
        $this->tags[$key] = $value;

        return $this;
    }

    /**
     * Send a test message to Sentry to verify the integration is working.
     * This will send a message event on the next request.
     *
     * @return self
     */
    public function sendTestMessage(): self
    {
        $this->sendTestMessage = true;

        return $this;
    }

    /**
     * Send a test exception to Sentry to verify the integration is working.
     * This will send an exception event on the next request.
     *
     * @return self
     */
    public function sendTestException(): self
    {
        $this->sendTestException = true;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        // Override the release version if set
        if ($this->customRelease !== null) {
            $container->extend('sentry.release', function ($release) {
                return $this->customRelease;
            });
        }

        // Add custom environment if set
        if ($this->customEnvironment !== null) {
            $container->singleton('fof.sentry.environment', function () {
                return $this->customEnvironment;
            });
        }

        // Add tags to backend
        if (!empty($this->tags)) {
            // Register tags in the container so they can be accessed by SentryJavaScript
            $container->singleton('fof.sentry.tags', function () {
                return $this->tags;
            });

            $container->extend(HubInterface::class, function (HubInterface $hub) {
                $hub->configureScope(function (Scope $scope) {
                    foreach ($this->tags as $key => $value) {
                        $scope->setTag($key, $value);
                    }
                });

                return $hub;
            });

            // Also add tags to frontend config
            $container->extend('fof.sentry.frontend.config', function ($config) {
                if (!isset($config['tags'])) {
                    $config['tags'] = [];
                }
                $config['tags'] = array_merge($config['tags'] ?? [], $this->tags);

                return $config;
            });
        }

        // Send test message if requested
        if ($this->sendTestMessage) {
            $container->resolving(HubInterface::class, function () {
                \Sentry\captureMessage('FoF Sentry test message - integration is working!');
            });
        }

        // Send test exception if requested
        if ($this->sendTestException) {
            $container->resolving(HubInterface::class, function (HubInterface $hub) {
                $hub->captureException(new \Exception('FoF Sentry test exception - integration is working!'));
            });
        }
    }
}
