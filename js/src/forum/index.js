import app from 'flarum/forum/app';

import {
  BrowserClient,
  defaultStackParser,
  getClient,
  setUser,
  makeFetchTransport,
  showReportDialog,
  breadcrumbsIntegration,
  globalHandlersIntegration,
  inboundFiltersIntegration,
  functionToStringIntegration,
  linkedErrorsIntegration,
  httpContextIntegration,
  dedupeIntegration,
  browserTracingIntegration,
  replayIntegration,
  captureConsoleIntegration,
} from '@sentry/browser';

const integrations = [
  inboundFiltersIntegration(),
  functionToStringIntegration(),
  dedupeIntegration(),
  globalHandlersIntegration({
    onerror: true,
    onunhandledrejection: true,
  }),
  breadcrumbsIntegration({
    console: true,
    dom: true,
    fetch: true,
    history: true,
    sentry: true,
    xhr: true,
  }),
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
        showReportDialog({ eventId: event.event_id, user: Sentry.getUserData('username') });
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

    integrations: [...integrations, config.captureConsole && captureConsoleIntegration()].filter(Boolean),
  });

window.Sentry = { createClient, getClient, setUser, showReportDialog };

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
        [nameAttr]: user.displayName(),
      };

      if (user.displayName() !== user.username()) {
        userData.username_slug = user.username();
      }

      if (!app.data['fof-sentry.scrub-emails']) {
        userData.email = user.email();
      }

      // Add user groups if available
      if (user.groups && user.groups()) {
        const groups = user
          .groups()
          .map((group) => group.nameSingular())
          .filter(Boolean)
          .join(', ');

        if (groups) {
          userData.groups = groups;
        }
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
  setUser(Sentry.getUserData());
});
