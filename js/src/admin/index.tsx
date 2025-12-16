import app from 'flarum/admin/app';
import SentrySettingsPage from './components/SentrySettingsPage';

app.initializers.add('fof/sentry', () => {
  app.registry.for('fof-sentry').registerPage(SentrySettingsPage);
});
