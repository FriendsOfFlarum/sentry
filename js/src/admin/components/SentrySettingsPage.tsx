import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import SampleRateSlider from './SampleRateSlider';

export default class SentrySettingsPage extends ExtensionPage {
  content() {
    const hasExcimer = app.data['hasExcimer'] as boolean;

    // Check current setting values
    const monitorPerformance = Number(this.setting('fof-sentry.monitor_performance')()) || 0;
    const nPlusOneDetection = this.setting('fof-sentry.db.n_plus_one_detection')() === '1';
    const javascriptEnabled = this.setting('fof-sentry.javascript')() !== '0';

    return (
      <div className="SentrySettingsPage">
        <div className="container">
          <div className="SentrySettingsPage--content">
            <h3>{app.translator.trans('fof-sentry.admin.sections.general')}</h3>
            <p className="helpText">{app.translator.trans('fof-sentry.admin.sections.general_help')}</p>
            <div className="Section">
              {this.buildSettingComponent({
                type: 'url',
                setting: 'fof-sentry.dsn',
                label: app.translator.trans('fof-sentry.admin.settings.dsn_label'),
                help: app.translator.trans('fof-sentry.admin.settings.dsn_help'),
                required: true,
              })}
              {this.buildSettingComponent({
                type: 'url',
                setting: 'fof-sentry.dsn_backend',
                label: app.translator.trans('fof-sentry.admin.settings.dsn_backend_label'),
                help: app.translator.trans('fof-sentry.admin.settings.dsn_backend_help'),
              })}
              {this.buildSettingComponent({
                type: 'text',
                setting: 'fof-sentry.environment',
                label: app.translator.trans('fof-sentry.admin.settings.environment_label'),
                help: app.translator.trans('fof-sentry.admin.settings.environment_help'),
              })}
            </div>

            <h3>{app.translator.trans('fof-sentry.admin.sections.user_context')}</h3>
            <p className="helpText">{app.translator.trans('fof-sentry.admin.sections.user_context_help')}</p>
            <div className="Section">
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'fof-sentry.user_feedback',
                label: app.translator.trans('fof-sentry.admin.settings.user_feedback_label'),
                help: app.translator.trans('fof-sentry.admin.settings.user_feedback_help'),
              })}
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'fof-sentry.send_emails_with_sentry_reports',
                label: app.translator.trans('fof-sentry.admin.settings.send_user_emails_label'),
                help: app.translator.trans('fof-sentry.admin.settings.send_user_emails_help'),
              })}
            </div>

            <h3>{app.translator.trans('fof-sentry.admin.sections.backend_performance')}</h3>
            <p className="helpText">{app.translator.trans('fof-sentry.admin.sections.backend_performance_help')}</p>
            <div className="Section">
              <SampleRateSlider
                value={this.setting('fof-sentry.monitor_performance')}
                label={app.translator.trans('fof-sentry.admin.settings.monitor_performance_label')}
                help={app.translator.trans('fof-sentry.admin.settings.monitor_performance_help')}
                min={0}
                max={100}
              />
              {monitorPerformance > 0 && (
                <SampleRateSlider
                  value={this.setting('fof-sentry.profile_rate')}
                  label={app.translator.trans('fof-sentry.admin.settings.profile_rate_label')}
                  help={app.translator.trans('fof-sentry.admin.settings.profile_rate_help', {
                    bold: hasExcimer ? null : <b />,
                    icon: hasExcimer ? '✔' : '✖',
                    a: <a href="https://docs.sentry.io/platforms/php/profiling/#improve-response-time" target="_blank" />,
                  })}
                  min={0}
                  max={100}
                  disabled={!hasExcimer}
                />
              )}
            </div>

            {monitorPerformance > 0 && (
              <>
                <h3>{app.translator.trans('fof-sentry.admin.sections.database_monitoring')}</h3>
                <p className="helpText">{app.translator.trans('fof-sentry.admin.sections.database_monitoring_help')}</p>
                <div className="Section">
                  {this.buildSettingComponent({
                    type: 'number',
                    setting: 'fof-sentry.db.slow_query_threshold',
                    label: app.translator.trans('fof-sentry.admin.settings.db_slow_query_threshold_label'),
                    help: app.translator.trans('fof-sentry.admin.settings.db_slow_query_threshold_help'),
                    min: 100,
                    max: 10000,
                  })}
                  {this.buildSettingComponent({
                    type: 'boolean',
                    setting: 'fof-sentry.db.n_plus_one_detection',
                    label: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_detection_label'),
                    help: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_detection_help'),
                  })}
                  {nPlusOneDetection &&
                    this.buildSettingComponent({
                      type: 'number',
                      setting: 'fof-sentry.db.n_plus_one_threshold',
                      label: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_threshold_label'),
                      help: app.translator.trans('fof-sentry.admin.settings.db_n_plus_one_threshold_help'),
                      min: 5,
                      max: 100,
                    })}
                  {this.buildSettingComponent({
                    type: 'boolean',
                    setting: 'fof-sentry.db.track_bindings',
                    label: app.translator.trans('fof-sentry.admin.settings.db_track_bindings_label'),
                    help: app.translator.trans('fof-sentry.admin.settings.db_track_bindings_help'),
                  })}
                  <SampleRateSlider
                    value={this.setting('fof-sentry.db.query_sample_rate')}
                    label={app.translator.trans('fof-sentry.admin.settings.db_query_sample_rate_label')}
                    help={app.translator.trans('fof-sentry.admin.settings.db_query_sample_rate_help')}
                    min={0}
                    max={100}
                  />
                </div>
              </>
            )}

            <h3>{app.translator.trans('fof-sentry.admin.sections.frontend_monitoring')}</h3>
            <p className="helpText">{app.translator.trans('fof-sentry.admin.sections.frontend_monitoring_help')}</p>
            <div className="Section">
              {this.buildSettingComponent({
                type: 'boolean',
                setting: 'fof-sentry.javascript',
                label: app.translator.trans('fof-sentry.admin.settings.javascript_label'),
                help: app.translator.trans('fof-sentry.admin.settings.javascript_help'),
              })}
              {javascriptEnabled &&
                this.buildSettingComponent({
                  type: 'boolean',
                  setting: 'fof-sentry.javascript.console',
                  label: app.translator.trans('fof-sentry.admin.settings.javascript_console_label'),
                  help: app.translator.trans('fof-sentry.admin.settings.javascript_console_help'),
                })}
              {javascriptEnabled && (
                <SampleRateSlider
                  value={this.setting('fof-sentry.javascript.trace_sample_rate')}
                  label={app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate')}
                  help={app.translator.trans('fof-sentry.admin.settings.javascript_trace_sample_rate_help')}
                  min={0}
                  max={100}
                />
              )}
              {javascriptEnabled && (
                <SampleRateSlider
                  value={this.setting('fof-sentry.javascript.replays_session_sample_rate')}
                  label={app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate')}
                  help={app.translator.trans('fof-sentry.admin.settings.javascript_replays_session_sample_rate_help')}
                  min={0}
                  max={100}
                />
              )}
              {javascriptEnabled && (
                <SampleRateSlider
                  value={this.setting('fof-sentry.javascript.replays_error_sample_rate')}
                  label={app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate')}
                  help={app.translator.trans('fof-sentry.admin.settings.javascript_replays_error_sample_rate_help')}
                  min={0}
                  max={100}
                />
              )}
            </div>

            {this.submitButton()}
          </div>
        </div>
      </div>
    );
  }
}
