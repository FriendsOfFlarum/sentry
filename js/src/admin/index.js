import app from 'flarum/app';

app.initializers.add('fof/sentry', () => {
  app.extensionData.for('fof-sentry')
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.dsn_label'),
      setting: 'fof-sentry.dsn',
      type: 'url',
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.user_feedback_label'),
      setting: 'fof-sentry.user_feedback',
      type: 'boolean',
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.javascript_label'),
      setting: 'fof-sentry.javascript',
      type: 'boolean',
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.javascript_console_label'),
      setting: 'fof-sentry.javascript.console',
      type: 'boolean',
    })
});
