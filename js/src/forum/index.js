import * as Sentry from '@sentry/browser';
import { CaptureConsole } from '@sentry/integrations';
import { Integrations as TracingIntegrations } from '@sentry/tracing';

window.Sentry = Sentry;
window.Sentry.Integrations.CaptureConsole = CaptureConsole;
window.Sentry.TracingIntegrations = TracingIntegrations;

window.Sentry.getUserData = (nameAttr = 'username') => {
    if (app) {
        if (app.session && app.session.user && app.session.user.id() != 0) {
            const data = {
                id: app.session.user.id(),
                email: app.session.user.email(),
            };

            data[nameAttr] = app.session.user.username();

            return data;
        } else if (app.data.session && app.data.session.userId != 0) {
            return {
                id: app.data.session.userId,
            };
        }
    }

    return {};
};
