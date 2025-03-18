import app from 'flarum/admin/app';

app.initializers.add('fof/sentry', () => {
  const hasExcimer = app.data['hasExcimer'];

  app.registry
    .for('fof-sentry')
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.dsn_label'),
      help: app.translator.trans('fof-sentry.admin.settings.dsn_help'),
      setting: 'fof-sentry.dsn',
      type: 'url',
      required: true,
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.dsn_backend_label'),
      help: app.translator.trans('fof-sentry.admin.settings.dsn_backend_help'),
      setting: 'fof-sentry.dsn_backend',
      type: 'url',
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.environment_label'),
      setting: 'fof-sentry.environment',
      type: 'string',
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.user_feedback_label'),
      setting: 'fof-sentry.user_feedback',
      type: 'boolean',
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.send_user_emails_label'),
      setting: 'fof-sentry.send_emails_with_sentry_reports',
      type: 'boolean',
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.monitor_performance_label'),
      setting: 'fof-sentry.monitor_performance',
      type: 'number',
      min: 0,
      max: 100,
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.profile_rate_label'),
      help: app.translator.trans('fof-sentry.admin.settings.profile_rate_help', {
        br: <br />,
        bold: hasExcimer ? null : <b />,
        icon: hasExcimer ? '✔' : '✖',
        a: <a href="https://docs.sentry.io/platforms/php/profiling/#improve-response-time" target="_blank" />,
      }),
      setting: 'fof-sentry.profile_rate',
      type: 'number',
      min: 0,
      max: 100,
      disabled: !hasExcimer, // requires PHP extension
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
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate'),
      help: app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate_help', { br: <br /> }),
      setting: 'fof-sentry.javascript.trace_sample_rate',
      type: 'number',
      min: 0,
      max: 100,
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate'),
      help: app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate_help', { br: <br /> }),
      setting: 'fof-sentry.javascript.replays_session_sample_rate',
      type: 'number',
      min: 0,
      max: 100,
    })
    .registerSetting({
      label: app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate'),
      help: app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate_help', { br: <br /> }),
      setting: 'fof-sentry.javascript.replays_error_sample_rate',
      type: 'number',
      min: 0,
      max: 100,
    });
});
