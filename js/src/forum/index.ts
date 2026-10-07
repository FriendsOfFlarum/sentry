import { getClient, setUser, showReportDialog } from '@sentry/browser';
import { createClient, getUserData } from './sentry';

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
