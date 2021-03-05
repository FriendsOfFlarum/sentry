import app from 'flarum/admin/app';

app.initializers.add('fof/sentry', () => {
    app.extensionData
        .for('fof-sentry')
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
            label: app.translator.trans('fof-sentry.admin.settings.monitor_performance_label'),
            setting: 'fof-sentry.monitor_performance',
            type: 'number',
            min: 0,
            max: 100,
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
            setting: 'fof-sentry.javascript.trace_sample_rate',
            type: 'number',
            min: 0,
            max: 100,
        })
        .registerSetting({
            label: app.translator.trans('fof-sentry.admin.settings.send_user_emails_label'),
            setting: 'fof-sentry.send_emails_with_sentry_reports',
            type: 'boolean',
        });
});
