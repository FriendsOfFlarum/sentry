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
  BrowserTracing,
  Replay,
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
  integrations.push(new BrowserTracing());
}

if (__SENTRY_SESSION_REPLAY__) {
  integrations.push(new Replay());
}

const createClient = (config) =>
  new BrowserClient({
    dsn: config.dsn,

    transport: makeFetchTransport,
    stackParser: defaultStackParser,

    // Add environment and release from config
    environment: config.environment,
    release: config.release,

    beforeSend: (event) => {
      event.logger = 'javascript';

      if (config.scrubEmails && event.user?.email) {
        delete event.user.email;
      }

      if (config.showFeedback && event.exception) {
        showReportDialog({ eventId: event.event_id, user: Sentry.getUserData('name') });
      }

      // Apply tags if provided
      if (config.tags) {
        if (!event.tags) event.tags = {};
        Object.assign(event.tags, config.tags);
      }

      return event;
    },

    tracesSampleRate: config.tracesSampleRate,
    replaysSessionSampleRate: config.replaysSessionSampleRate,
    replaysOnErrorSampleRate: config.replaysOnErrorSampleRate,

    integrations: [...integrations, config.captureConsole && new CaptureConsole()].filter(Boolean),
  });

window.Sentry = { createClient, getCurrentHub, showReportDialog };

window.Sentry.getUserData = (nameAttr = 'username') => {
  /** @type {Sentry.User} */
  let userData = {};

  // Depending on when the error occurs, `app` might not be defined
  if (app) {
    const user = app.session?.user;

    if (app.session && user && user.id() != 0) {
      userData = {
        ip_address: '{{auto}}',
        id: user.id(),
        [nameAttr]: user.username(),
      };

      if (!app.data['fof-sentry.scrub-emails']) {
        userData.email = user.email();
      }
    } else if (app.data.session && app.data.session.userId != 0) {
      userData = {
        id: app.data.session.userId,
      };
    }
  }

  return userData;
};

app.initializers.add('fof/sentry', () => {
  getCurrentHub().setUser(Sentry.getUserData());
});
