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

    createClient(config).init();

    // Initializers run before the session is loaded; identify the user once it is.
    app.beforeMount(() => setUser(getUserData()));
  },
  // Ahead of other extensions' initializers, so errors thrown in them are captured.
  1000
);
