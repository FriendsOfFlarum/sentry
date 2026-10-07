import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import SentrySettingsPage from '../../../src/admin/components/SentrySettingsPage';

beforeAll(() => {
  bootstrapAdmin({
    extensions: {
      'fof-sentry': {
        id: 'fof-sentry',
        name: 'fof/sentry',
        version: '2.0.0',
        description: 'Sentry',
        icon: { name: 'fas fa-bug' },
        links: {},
        extra: { 'flarum-extension': { title: 'Sentry' } },
      },
    },
  });
  app.boot();
});

function render(settings: Record<string, string>, hasExcimer = true) {
  app.data.settings = { extensions_enabled: JSON.stringify(['fof-sentry']), ...settings };
  app.data.hasExcimer = hasExcimer;

  return mq(SentrySettingsPage, { id: 'fof-sentry' });
}

const DB_SECTION = 'fof-sentry.admin.sections.database_monitoring';
const PROFILE_RATE = 'fof-sentry.admin.settings.profile_rate_label';

describe('SentrySettingsPage', () => {
  it('hides the profiling slider and database monitoring when performance monitoring is 0', () => {
    const page = render({ 'fof-sentry.monitor_performance': '0' });

    expect(page).not.toContainRaw(DB_SECTION);
    expect(page).not.toContainRaw(PROFILE_RATE);
  });

  it('shows the profiling slider and database monitoring when performance monitoring is above 0', () => {
    const page = render({ 'fof-sentry.monitor_performance': '20' });

    expect(page).toContainRaw(DB_SECTION);
    expect(page).toContainRaw(PROFILE_RATE);
  });

  it('only shows the N+1 threshold when N+1 detection is on', () => {
    expect(render({ 'fof-sentry.monitor_performance': '20' })).not.toContainRaw('db_n_plus_one_threshold_label');
    expect(render({ 'fof-sentry.monitor_performance': '20', 'fof-sentry.db.n_plus_one_detection': '1' })).toContainRaw(
      'db_n_plus_one_threshold_label'
    );
  });

  it('disables the profiling slider when excimer is unavailable', () => {
    const page = render({ 'fof-sentry.monitor_performance': '20', 'fof-sentry.profile_rate': '10' }, false);
    const sliders = page.find('input.SampleRateSlider-input');

    // monitor_performance first, profile_rate second.
    expect(sliders[0].hasAttribute('disabled')).toBe(false);
    expect(sliders[1].hasAttribute('disabled')).toBe(true);
  });

  it('enables the profiling slider when excimer is available', () => {
    const page = render({ 'fof-sentry.monitor_performance': '20' }, true);

    expect(page.find('input.SampleRateSlider-input')[1].hasAttribute('disabled')).toBe(false);
  });

  it('hides the JavaScript sub-settings when JavaScript monitoring is off', () => {
    const page = render({ 'fof-sentry.javascript': '0' });

    expect(page).toContainRaw('fof-sentry.admin.settings.javascript_label');
    expect(page).not.toContainRaw('javascript_console_label');
    expect(page).not.toContainRaw('javascript_trace_sample_rate');
    expect(page).not.toContainRaw('javascript_replays_session_sample_rate');
    expect(page).not.toContainRaw('javascript_replays_error_sample_rate');
  });

  it('shows the JavaScript sub-settings when JavaScript monitoring is on', () => {
    const page = render({ 'fof-sentry.javascript': '1' });

    expect(page).toContainRaw('javascript_console_label');
    expect(page).toContainRaw('javascript_trace_sample_rate');
    expect(page).toContainRaw('javascript_replays_session_sample_rate');
    expect(page).toContainRaw('javascript_replays_error_sample_rate');
  });
});
