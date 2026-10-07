import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import type Mithril from 'mithril';
import SentrySettingsPage from './components/SentrySettingsPage';
import { SAMPLE_RATE_FIELD } from './components/SampleRateSlider';
import {
  javascriptEnabled,
  nPlusOneDetectionEnabled,
  performanceMonitoringEnabled,
  type SentrySettingConfig,
  type SettingValue,
} from './settingVisibility';

type SectionText = () => { title: Mithril.Children; help: Mithril.Children };

const section = (text: SectionText, visible?: (value: SettingValue) => boolean) =>
  function (this: SentrySettingsPage) {
    if (visible && !visible(this.liveValue)) {
      return null;
    }

    const { title, help } = text();

    return (
      <div className="SentrySettingsPage-section">
        <h3>{title}</h3>
        <p className="helpText">{help}</p>
      </div>
    );
  };

// Search result breadcrumbs: "FoF Sentry › <section> › <setting>".
const generalTree = () => [app.translator.trans('fof-sentry.admin.sections.general')];
const userContextTree = () => [app.translator.trans('fof-sentry.admin.sections.user_context')];
const backendPerformanceTree = () => [app.translator.trans('fof-sentry.admin.sections.backend_performance')];
const databaseMonitoringTree = () => [app.translator.trans('fof-sentry.admin.sections.database_monitoring')];
const frontendMonitoringTree = () => [app.translator.trans('fof-sentry.admin.sections.frontend_monitoring')];

export default [
  new Extend.Admin()
    .page(SentrySettingsPage)

    .customSetting(
      section(() => ({
        title: app.translator.trans('fof-sentry.admin.sections.general'),
        help: app.translator.trans('fof-sentry.admin.sections.general_help'),
      })),
      1000
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.dsn',
        type: 'url',
        label: app.translator.trans('fof-sentry.admin.settings.dsn_label'),
        help: app.translator.trans('fof-sentry.admin.settings.dsn_help'),
        required: true,
        tree: generalTree(),
      }),
      990
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.dsn_backend',
        type: 'url',
        label: app.translator.trans('fof-sentry.admin.settings.dsn_backend_label'),
        help: app.translator.trans('fof-sentry.admin.settings.dsn_backend_help'),
        tree: generalTree(),
      }),
      980
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.environment',
        type: 'text',
        label: app.translator.trans('fof-sentry.admin.settings.environment_label'),
        help: app.translator.trans('fof-sentry.admin.settings.environment_help'),
        tree: generalTree(),
      }),
      970
    )

    .customSetting(
      section(() => ({
        title: app.translator.trans('fof-sentry.admin.sections.user_context'),
        help: app.translator.trans('fof-sentry.admin.sections.user_context_help'),
      })),
      900
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.user_feedback',
        type: 'boolean',
        label: app.translator.trans('fof-sentry.admin.settings.user_feedback_label'),
        help: app.translator.trans('fof-sentry.admin.settings.user_feedback_help'),
        tree: userContextTree(),
      }),
      890
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.send_emails_with_sentry_reports',
        type: 'boolean',
        label: app.translator.trans('fof-sentry.admin.settings.send_user_emails_label'),
        help: app.translator.trans('fof-sentry.admin.settings.send_user_emails_help'),
        tree: userContextTree(),
      }),
      880
    )

    .customSetting(
      section(() => ({
        title: app.translator.trans('fof-sentry.admin.sections.backend_performance'),
        help: app.translator.trans('fof-sentry.admin.sections.backend_performance_help'),
      })),
      800
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.monitor_performance',
        type: SAMPLE_RATE_FIELD,
        label: app.translator.trans('fof-sentry.admin.settings.monitor_performance_label'),
        help: app.translator.trans('fof-sentry.admin.settings.monitor_performance_help'),
        min: 0,
        max: 100,
        tree: backendPerformanceTree(),
      }),
      790
    )
    .setting((): SentrySettingConfig => {
      // Excimer availability is fixed for the lifetime of the page, so it is safe to resolve once here.
      const hasExcimer = !!app.data.hasExcimer;

      return {
        setting: 'fof-sentry.profile_rate',
        type: SAMPLE_RATE_FIELD,
        label: app.translator.trans('fof-sentry.admin.settings.profile_rate_label'),
        help: app.translator.trans('fof-sentry.admin.settings.profile_rate_help', {
          // Rich parameters must never be null: the translator reads `.attrs` from any object value.
          bold: ({ children }: { children: Mithril.Children }) => (hasExcimer ? children : <b>{children}</b>),
          icon: hasExcimer ? '✔' : '✖',
          a: ({ children }: { children: Mithril.Children }) => (
            <a href="https://docs.sentry.io/platforms/php/profiling/#improve-response-time" target="_blank">
              {children}
            </a>
          ),
        }),
        min: 0,
        max: 100,
        disabled: !hasExcimer,
        visible: performanceMonitoringEnabled,
        tree: backendPerformanceTree(),
      };
    }, 780)

    .customSetting(
      section(
        () => ({
          title: app.translator.trans('fof-sentry.admin.sections.database_monitoring'),
          help: app.translator.trans('fof-sentry.admin.sections.database_monitoring_help'),
        }),
        performanceMonitoringEnabled
      ),
      700
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.db.slow_query_threshold',
        type: 'number',
        label: app.translator.trans('fof-sentry.admin.settings.db_slow_query_threshold_label'),
        help: app.translator.trans('fof-sentry.admin.settings.db_slow_query_threshold_help'),
        min: 100,
        max: 10000,
        visible: performanceMonitoringEnabled,
        tree: databaseMonitoringTree(),
      }),
      690
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.db.n_plus_one_detection',
        type: 'boolean',
        label: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_detection_label'),
        help: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_detection_help'),
        visible: performanceMonitoringEnabled,
        tree: databaseMonitoringTree(),
      }),
      680
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.db.n_plus_one_threshold',
        type: 'number',
        label: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_threshold_label'),
        help: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_threshold_help'),
        min: 5,
        max: 100,
        visible: nPlusOneDetectionEnabled,
        tree: databaseMonitoringTree(),
      }),
      670
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.db.track_bindings',
        type: 'boolean',
        label: app.translator.trans('fof-sentry.admin.settings.db_track_bindings_label'),
        help: app.translator.trans('fof-sentry.admin.settings.db_track_bindings_help'),
        visible: performanceMonitoringEnabled,
        tree: databaseMonitoringTree(),
      }),
      660
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.db.query_sample_rate',
        type: SAMPLE_RATE_FIELD,
        label: app.translator.trans('fof-sentry.admin.settings.db_query_sample_rate_label'),
        help: app.translator.trans('fof-sentry.admin.settings.db_query_sample_rate_help'),
        min: 0,
        max: 100,
        visible: performanceMonitoringEnabled,
        tree: databaseMonitoringTree(),
      }),
      650
    )

    .customSetting(
      section(() => ({
        title: app.translator.trans('fof-sentry.admin.sections.frontend_monitoring'),
        help: app.translator.trans('fof-sentry.admin.sections.frontend_monitoring_help'),
      })),
      600
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.javascript',
        type: 'boolean',
        label: app.translator.trans('fof-sentry.admin.settings.javascript_label'),
        help: app.translator.trans('fof-sentry.admin.settings.javascript_help'),
        tree: frontendMonitoringTree(),
      }),
      590
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.javascript.console',
        type: 'boolean',
        label: app.translator.trans('fof-sentry.admin.settings.javascript_console_label'),
        help: app.translator.trans('fof-sentry.admin.settings.javascript_console_help'),
        visible: javascriptEnabled,
        tree: frontendMonitoringTree(),
      }),
      580
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.javascript.trace_sample_rate',
        type: SAMPLE_RATE_FIELD,
        label: app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate'),
        help: app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate_help'),
        min: 0,
        max: 100,
        visible: javascriptEnabled,
        tree: frontendMonitoringTree(),
      }),
      570
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.javascript.replays_session_sample_rate',
        type: SAMPLE_RATE_FIELD,
        label: app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate'),
        help: app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate_help'),
        min: 0,
        max: 100,
        visible: javascriptEnabled,
        tree: frontendMonitoringTree(),
      }),
      560
    )
    .setting(
      (): SentrySettingConfig => ({
        setting: 'fof-sentry.javascript.replays_error_sample_rate',
        type: SAMPLE_RATE_FIELD,
        label: app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate'),
        help: app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate_help'),
        min: 0,
        max: 100,
        visible: javascriptEnabled,
        tree: frontendMonitoringTree(),
      }),
      550
    ),
] satisfies object[];
