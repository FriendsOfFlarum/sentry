<?php

/*
 * This file is part of fof/sentry
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Sentry\Http;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\Dsn;

/**
 * Receives the browser SDK's envelopes and forwards them to the configured DSN.
 *
 * With the tunnel on, the browser posts to the forum itself instead of to the
 * DSN's host. That lets a forum report frontend errors to a Sentry (or a
 * Sentry-compatible server) that visitors cannot reach, such as one on a
 * private network, and keeps the reports from being blocked by ad blockers.
 *
 * It forwards only to the project of the configured DSN: an envelope whose
 * header names any other DSN is refused, so this cannot be used as a relay to
 * anywhere else. See https://docs.sentry.io/platforms/javascript/troubleshooting/#using-the-tunnel-option
 */
class TunnelController implements RequestHandlerInterface
{
    /** A browser envelope is a few KB; anything this size is not one. */
    public const MAX_BYTES = 1048576;

    public function __construct(
        private SettingsRepositoryInterface $settings,
        private Client $http
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $configured = (string) $this->settings->get('fof-sentry.dsn');

        if ($configured === ''
            || !(int) $this->settings->get('fof-sentry.javascript')
            || !(int) $this->settings->get('fof-sentry.javascript.tunnel')) {
            return new EmptyResponse(404);
        }

        $body = (string) $request->getBody();

        if (strlen($body) > self::MAX_BYTES) {
            return new EmptyResponse(413);
        }

        // An envelope's first line is a JSON header naming the DSN it is for.
        $header = json_decode(strtok($body, "\n") ?: '', true);

        try {
            $dsn = Dsn::createFromString($configured);
            $target = Dsn::createFromString((string) (is_array($header) ? ($header['dsn'] ?? '') : ''));
        } catch (\Throwable) {
            return new EmptyResponse(400);
        }

        if ($target->getHost() !== $dsn->getHost()
            || $target->getPort() !== $dsn->getPort()
            || $target->getProjectId() !== $dsn->getProjectId()
            || $target->getPublicKey() !== $dsn->getPublicKey()) {
            return new EmptyResponse(400);
        }

        $headers = ['Content-Type' => 'application/x-sentry-envelope'];

        // Without this every browser event would carry the forum server's IP.
        if ($ip = $request->getAttribute('ipAddress')) {
            $headers['X-Forwarded-For'] = (string) $ip;
        }

        try {
            $response = $this->http->request('POST', $dsn->getEnvelopeApiEndpointUrl(), [
                'body'        => $body,
                'headers'     => $headers,
                'timeout'     => 5,
                'http_errors' => false,
            ]);
        } catch (GuzzleException) {
            return new EmptyResponse(502);
        }

        return new EmptyResponse($response->getStatusCode());
    }
}
