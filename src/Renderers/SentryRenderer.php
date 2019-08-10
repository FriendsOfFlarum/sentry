<?php


namespace FoF\Sentry\Renderers;

use Flarum\Foundation\ErrorHandling\HandledError;
use Flarum\Foundation\ErrorHandling\ViewRenderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Zend\Diactoros\Response\HtmlResponse;

class SentryRenderer extends ViewRenderer
{
    public function format(HandledError $error, Request $request): Response
    {
        if (!$error->shouldBeReported()) {
            return parent::format($error, $request);
        }

        $sentry = app('sentry');

        if ($sentry == null || $sentry->getLastEventId() == null) {
            return parent::format($error, $request);
        }

        $originalView = $this->determineView($error);

        $view = $this->view->make('fof-sentry::error.feedback')
            ->with('errorView', $originalView)
            ->with('error', $error->getError())
            ->with('message', $this->getMessage($error))
            ->with('user', app('sentry.request')->getAttribute('actor'));

        return new HtmlResponse($view->render(), $error->getStatusCode());
    }
}
