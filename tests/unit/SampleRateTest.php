<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Tests\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Sample rates are stored as 0-100 integers in settings but the Sentry SDK
 * expects a 0.0-1.0 float. That clamp-and-divide appears in both
 * SentryServiceProvider (backend) and SentryJavaScript (frontend), and the two
 * are subtly different: the backend rounds before dividing, the frontend does
 * not.
 *
 * These tests document the conversion the extension must produce, so the
 * duplication can be collapsed into one helper without changing behaviour.
 */
class SampleRateTest extends TestCase
{
    /**
     * The backend conversion, as written in SentryServiceProvider.
     */
    private function backendRate(mixed $setting): float
    {
        $value = (int) $setting;

        return round(max(0, min(100, $value))) / 100;
    }

    /**
     * The frontend conversion, as written in SentryJavaScript.
     */
    private function frontendRate(mixed $setting): float
    {
        $value = (int) $setting;

        return max(0, min(100, $value)) / 100;
    }

    #[Test]
    #[DataProvider('rateProvider')]
    public function settings_convert_to_a_sentry_sample_rate(mixed $setting, float $expected): void
    {
        $this->assertSame($expected, $this->backendRate($setting));
        $this->assertSame($expected, $this->frontendRate($setting));
    }

    public static function rateProvider(): array
    {
        return [
            'disabled'            => [0, 0.0],
            'one percent'         => [1, 0.01],
            'quarter'             => [25, 0.25],
            'full'                => [100, 1.0],
            'above range clamps'  => [250, 1.0],
            'negative clamps'     => [-10, 0.0],
            'numeric string'      => ['50', 0.5],
            'empty string is off' => ['', 0.0],
            'null is off'         => [null, 0.0],
            // Settings arrive from the database as strings; a stored bool-ish
            // value must not become a surprise sample rate.
            'false-ish string'    => ['0', 0.0],
        ];
    }

    #[Test]
    public function a_rate_is_always_within_the_range_the_sdk_accepts(): void
    {
        foreach ([-100, -1, 0, 1, 50, 100, 101, 10000] as $setting) {
            $rate = $this->backendRate($setting);

            $this->assertGreaterThanOrEqual(0.0, $rate);
            $this->assertLessThanOrEqual(1.0, $rate);
        }
    }

    #[Test]
    public function backend_and_frontend_conversions_agree_across_the_whole_range(): void
    {
        // If these ever diverge, the JS and PHP transactions sample at
        // different rates and traces stop lining up.
        for ($setting = 0; $setting <= 100; $setting++) {
            $this->assertSame(
                $this->backendRate($setting),
                $this->frontendRate($setting),
                "Conversions differ at $setting"
            );
        }
    }
}
