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

use Flarum\Extension\Extension;
use Flarum\Extend\ExtenderInterface;
use Illuminate\Contracts\Container\Container;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

class Sentry implements ExtenderInterface
{
    private $customRelease = null;
    private $customEnvironment = null;
    private $backendConfig = [];
    private $frontendConfig = [];
    private $tags = [];

    /**
     * Set a custom release version.
     * 
     * @param string $release The release version to use
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
     * @return self
     */
    public function setEnvironment(string $environment): self
    {
        $this->customEnvironment = $environment;
        return $this;
    }

    /**
     * Add a custom configuration option for the PHP SDK.
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return self
     */
    public function addBackendConfig(string $key, $value): self
    {
        $this->backendConfig[$key] = $value;
        return $this;
    }

    /**
     * Add a custom configuration option for the JavaScript SDK.
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return self
     */
    public function addFrontendConfig(string $key, $value): self
    {
        $this->frontendConfig[$key] = $value;
        return $this;
    }

    /**
     * Add a tag that will be sent with all events.
     * 
     * @param string $key Tag key
     * @param string $value Tag value
     * @return self
     */
    public function addTag(string $key, string $value): self
    {
        $this->tags[$key] = $value;
        return $this;
    }

    public function extend(Container $container, Extension $extension = null)
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

        // Add backend config options
        if (!empty($this->backendConfig)) {
            $container->extend('fof.sentry.backend.config', function ($config) {
                return array_merge($config ?? [], $this->backendConfig);
            });
        }

        // Add frontend config options
        if (!empty($this->frontendConfig)) {
            $container->extend('fof.sentry.frontend.config', function ($config) {
                return array_merge($config ?? [], $this->frontendConfig);
            });
        }

        // Add tags to backend
        if (!empty($this->tags)) {
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
    }
}
