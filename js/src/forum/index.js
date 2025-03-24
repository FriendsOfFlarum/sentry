import app from 'flarum/forum/app';

import {
  BrowserClient,
  defaultStackParser,
  makeFetchTransport,
  setCurrentClient,
  setUser,
  showReportDialog,
  globalHandlersIntegration,
  breadcrumbsIntegration,
  browserApiErrorsIntegration,
  browserTracingIntegration,
  functionToStringIntegration,
  httpContextIntegration,
  inboundFiltersIntegration,
  linkedErrorsIntegration,
  replayIntegration,
} from '@sentry/browser';

import { captureConsoleIntegration } from '@sentry/integrations';

const integrations = [
  globalHandlersIntegration({
    onerror: true,
    onunhandledrejection: true,
  }),
  inboundFiltersIntegration(),
  functionToStringIntegration(),
  breadcrumbsIntegration({
    console: true,
    dom: true,
    fetch: true,
    history: true,
    sentry: true,
    xhr: true,
  }),
  browserApiErrorsIntegration(),
  linkedErrorsIntegration({
    key: 'cause',
    limit: 5,
  }),
  httpContextIntegration(),
];

if (__SENTRY_TRACING__) {
  integrations.push(browserTracingIntegration());
}
if (__SENTRY_SESSION_REPLAY__) {
  integrations.push(replayIntegration());
}

const createClient = (config) =>
  new BrowserClient({
    dsn: config.dsn,
    environment: config.environment,

    transport: makeFetchTransport,
    stackParser: defaultStackParser,

    beforeSend: (event) => {
      event.logger = 'javascript';

      if (config.scrubEmails && event.user?.email) {
        delete event.user.email;
      }

      if (config.showFeedback && event.exception) {
        showReportDialog({ eventId: event.event_id, user: Sentry.getUserData('name') });
      }

      return event;
    },

    tracesSampleRate: config.tracesSampleRate,
    replaysSessionSampleRate: config.replaysSessionSampleRate,
    replaysOnErrorSampleRate: config.replaysOnErrorSampleRate,

    integrations: [...integrations, config.captureConsole && captureConsoleIntegration()].filter(Boolean),
  });

window.Sentry = { createClient, setCurrentClient, setUser };

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

      if (! app.data['fof-sentry.scrub-emails']) {
        userData.email = user.email();
      }
    } else if (app.data.session && app.data.session.userId !== 0) {
      userData = {
        id: app.data.session.userId,
      };
    }
  }

  return userData;
};

app.initializers.add('fof/sentry', () => {
  Sentry.setUser(Sentry.getUserData());
});
