import app from 'flarum/forum/app';
import * as Sentry from '@sentry/browser';
import { CaptureConsole } from '@sentry/integrations';
import { Integrations as TracingIntegrations } from '@sentry/tracing';

window.Sentry = Sentry;
window.Sentry.Integrations.CaptureConsole = CaptureConsole;
window.Sentry.TracingIntegrations = TracingIntegrations;

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
