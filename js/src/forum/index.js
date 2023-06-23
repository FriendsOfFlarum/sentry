import app from 'flarum/forum/app';

import {
  BrowserClient,
  defaultStackParser,
  getCurrentHub,
  makeFetchTransport,
  showReportDialog,
  Breadcrumbs,
  GlobalHandlers,
  InboundFilters,
  FunctionToString,
  LinkedErrors,
  HttpContext,
  TryCatch,
  BrowserTracing as SentryBrowserTracing,
} from '@sentry/browser';

import { CaptureConsole } from '@sentry/integrations';

const integrations = [
  new InboundFilters(),
  new FunctionToString(),
  new TryCatch(),
  new GlobalHandlers({
    onerror: true,
    onunhandledrejection: true,
  }),
  new Breadcrumbs({
    console: true,
    dom: true,
    fetch: true,
    history: true,
    sentry: true,
    xhr: true,
  }),
  new LinkedErrors({
    key: 'cause',
    limit: 5,
  }),
  new HttpContext(),
];

if (__SENTRY_TRACING__) {
  integrations.push(new SentryBrowserTracing());
}

const createClient = (config) =>
  new BrowserClient({
    dsn: config.dsn,

    transport: makeFetchTransport,
    stackParser: defaultStackParser,

    beforeSend: (event) => {
      event.logger = 'javascript';
      event.user = Sentry.getUserData();

      if (config.scrubEmails && event.user) {
        delete event.user.email;
      }

      if (config.showFeedback && event.exception) {
        showReportDialog({ eventId: event.event_id, user: event.user });
      }

      return event;
    },

    tracesSampleRate: config.tracesSampleRate,

    integrations: [...integrations, config.captureConsole && new CaptureConsole()].filter(Boolean),
  });

window.Sentry = { createClient, getCurrentHub, showReportDialog };

// All Sentry initialisation happens in `src/Content/SentryJavaScript.php`

window.Sentry.getUserData = (nameAttr = 'username') => {
  /** @type {Sentry.User} */
  let userData = {};

  // Depending on when the error occurs, `app` might not be defined
  if (app) {
    if (app.session && app.session.user && app.session.user.id() != 0) {
      userData = {
        ip_address: '{{auto}}',
        id: app.session.user.id(),
        email: app.session.user.email(),
        [nameAttr]: app.session.user.username(),
      };
    } else if (app.data.session && app.data.session.userId != 0) {
      userData = {
        id: app.data.session.userId,
      };
    }
  }

  return userData;
};
