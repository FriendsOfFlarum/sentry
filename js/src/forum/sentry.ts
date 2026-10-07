import app from 'flarum/forum/app';
import {
  BrowserClient,
  defaultStackParser,
  setCurrentClient,
  setUser,
  makeFetchTransport,
  showReportDialog,
  breadcrumbsIntegration,
  globalHandlersIntegration,
  eventFiltersIntegration,
  functionToStringIntegration,
  linkedErrorsIntegration,
  httpContextIntegration,
  dedupeIntegration,
  captureConsoleIntegration,
} from '@sentry/browser';
import type { User } from '@sentry/browser';

type Integration = NonNullable<ConstructorParameters<typeof BrowserClient>[0]['integrations']>[number];

/**
 * The browser configuration the backend puts in the forum payload (see SentryJavaScript.php).
 */
export interface SentryConfig {
  dsn: string;
  environment?: string;
  release?: string;
  scrubEmails?: boolean;
  showFeedback?: boolean;
  captureConsole?: boolean;
  tracesSampleRate?: number;
  replaysSessionSampleRate?: number;
  replaysOnErrorSampleRate?: number;
  tags?: Record<string, string>;
}

const integrations: Integration[] = [
  eventFiltersIntegration(),
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

export function getUserData(nameAttr = 'username'): User {
  const user = app.session?.user;

  if (user && Number(user.id()) !== 0) {
    const userData: User = {
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

    const groups = (user.groups() || [])
      .map((group) => group?.nameSingular())
      .filter(Boolean)
      .join(', ');

    if (groups) {
      userData.groups = groups;
    }

    return userData;
  }

  const userId = app.data.session?.userId;

  return userId && Number(userId) !== 0 ? { id: userId } : {};
}

export function createClient(config: SentryConfig): BrowserClient {
  const client = new BrowserClient({
    dsn: config.dsn,

    transport: makeFetchTransport,
    stackParser: defaultStackParser,

    environment: config.environment,
    release: config.release,

    // Record the visitor's IP, as the backend does. Naming only `userInfo` would make the SDK fall back to
    // collecting everything else too, so the rest is spelled out as off.
    dataCollection: {
      userInfo: true,
      cookies: false,
      httpHeaders: false,
      httpBodies: [],
      databaseQueryData: false,
      genAI: { inputs: false, outputs: false },
    },

    beforeSend: (event) => {
      event.logger = 'javascript';

      if (config.scrubEmails && event.user?.email) {
        delete event.user.email;
      }

      if (config.showFeedback && event.exception) {
        const { name, email } = getUserData('name');
        const user: { name?: string; email?: string } = {};

        if (name) user.name = name;
        if (email) user.email = email;

        showReportDialog({ eventId: event.event_id, user });
      }

      if (config.tags) {
        event.tags = { ...event.tags, ...config.tags };
      }

      return event;
    },

    tracesSampleRate: config.tracesSampleRate,
    replaysSessionSampleRate: config.replaysSessionSampleRate,
    replaysOnErrorSampleRate: config.replaysOnErrorSampleRate,

    integrations: config.captureConsole ? [...integrations, captureConsoleIntegration()] : integrations,
  });

  // In @sentry/browser v10, integrations such as globalHandlers, breadcrumbs and
  // captureConsole only act when `getClient() === client`, so the client must be
  // bound to the current scope. The caller still runs `client.init()` afterwards.
  setCurrentClient(client);

  // Covers callers that create a client after boot; at boot the initializer sets the user once the session exists.
  setUser(getUserData());

  return client;
}
