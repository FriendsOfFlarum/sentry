import app from 'flarum/admin/app';
import SentrySettingsPage from './components/SentrySettingsPage';

app.initializers.add('fof/sentry', () => {
  app.extensionData.for('fof-sentry').registerPage(SentrySettingsPage);
});
