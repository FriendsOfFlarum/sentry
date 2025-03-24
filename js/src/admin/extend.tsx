import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';

const hasExcimer = () => app.data['hasExcimer'];

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'fof-sentry.dsn',
      type: 'text',
      label: app.translator.trans('fof-sentry.admin.settings.dsn_label'),
      help: app.translator.trans('fof-sentry.admin.settings.dsn_help'),
      required: true,
    }))
    .setting(() => ({
      setting: 'fof-sentry.dsn_backend',
      type: 'url',
      label: app.translator.trans('fof-sentry.admin.settings.dsn_backend_label'),
      help: app.translator.trans('fof-sentry.admin.settings.dsn_backend_help'),
    }))
    .setting(() => ({
      setting: 'fof-sentry.environment',
      type: 'string',
      label: app.translator.trans('fof-sentry.admin.settings.environment_label'),
    }))
    .setting(() => ({
      setting: 'fof-sentry.user_feedback',
      type: 'boolean',
      label: app.translator.trans('fof-sentry.admin.settings.user_feedback_label'),
    }))
    .setting(() => ({
      setting: 'fof-sentry.send_emails_with_sentry_reports',
      type: 'boolean',
      label: app.translator.trans('fof-sentry.admin.settings.send_user_emails_label'),
    }))
    .setting(() => ({
      setting: 'fof-sentry.monitor_performance',
      type: 'number',
      label: app.translator.trans('fof-sentry.admin.settings.monitor_performance_label'),
      min: 0,
      max: 100,
    }))
    .setting(() => ({
      setting: 'fof-sentry.profile_rate',
      type: 'number',
      label: app.translator.trans('fof-sentry.admin.settings.profile_rate_label'),
      help: app.translator.trans('fof-sentry.admin.settings.profile_rate_help', {
        br: <br />,
        bold: hasExcimer() ? '' : <b />,
        icon: hasExcimer() ? '✔' : '✖',
        a: <a href="https://docs.sentry.io/platforms/php/profiling/#improve-response-time" target="_blank" />,
      }),
      min: 0,
      max: 100,
      disabled: !hasExcimer(), // requires PHP extension
    }))
    .setting(() => ({
      setting: 'fof-sentry.javascript',
      type: 'boolean',
      label: app.translator.trans('fof-sentry.admin.settings.javascript_label'),
    }))
    .setting(() => ({
      setting: 'fof-sentry.javascript.console',
      type: 'boolean',
      label: app.translator.trans('fof-sentry.admin.settings.javascript_console_label'),
    }))
    .setting(() => ({
      setting: 'fof-sentry.javascript.trace_sample_rate',
      type: 'number',
      label: app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate'),
      help: app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate_help', { br: <br /> }),
      min: 0,
      max: 100,
    }))
    .setting(() => ({
      setting: 'fof-sentry.javascript.replays_session_sample_rate',
      type: 'number',
      label: app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate'),
      help: app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate_help', { br: <br /> }),
      min: 0,
      max: 100,
    }))
    .setting(() => ({
      setting: 'fof-sentry.javascript.replays_error_sample_rate',
      type: 'number',
      label: app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate'),
      help: app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate_help', { br: <br /> }),
      min: 0,
      max: 100,
    }))
];
