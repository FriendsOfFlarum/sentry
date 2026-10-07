import app from 'flarum/forum/app';
import { getClient, setUser, showReportDialog } from '@sentry/browser';
import { createClient, getUserData, type SentryConfig } from './sentry';

declare global {
  interface Window {
    /** Kept for backwards compatibility with code that reached Sentry through this global. */
    Sentry: {
      createClient: typeof createClient;
      getClient: typeof getClient;
      setUser: typeof setUser;
      showReportDialog: typeof showReportDialog;
      getUserData: typeof getUserData;
    };
  }
}

window.Sentry = { createClient, getClient, setUser, showReportDialog, getUserData };

app.initializers.add(
  'fof/sentry',
  () => {
    const config = app.data['fof-sentry'] as SentryConfig | undefined;

    if (!config?.dsn) {
      return;
    }

    const client = createClient(config);
    client.init();

    // Initializers run before the session and forum are loaded. The user needs the session, and
    // lazy chunks need the forum, which supplies their URL.
    app.beforeMount(() => {
      setUser(getUserData());

      if ((config.tracesSampleRate ?? 0) > 0) {
        import('./integrations/tracing').then(({ default: tracing }) => client.addIntegration(tracing()));
      }
    });
  },
  // Ahead of other extensions' initializers, so errors thrown in them are captured.
  1000
);
