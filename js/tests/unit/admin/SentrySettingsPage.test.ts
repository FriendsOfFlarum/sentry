import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import GeneralSearchSource from 'flarum/admin/components/GeneralSearchSource';
import mq from 'mithril-query';
import flatten from 'flat';
import jsYaml from 'js-yaml';
import fs from 'fs';
import path from 'path';
import * as adminModule from '../../../src/admin';
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
        extra: { 'flarum-extension': { title: 'FoF Sentry' } },
      },
    },
    hasExcimer: false,
  });

  const locale = path.resolve(process.cwd(), '../resources/locale/en.yml');
  app.translator.addTranslations(flatten(jsYaml.load(fs.readFileSync(locale, 'utf8'))));

  // Runs the real Admin extender, so the page renders exactly what gets registered.
  app.bootExtensions({ 'fof-sentry': adminModule });
  app.boot();
});

function useSettings(settings: Record<string, string>) {
  app.data.settings = { extensions_enabled: JSON.stringify(['fof-sentry']), ...settings };
}

function render(settings: Record<string, string>) {
  useSettings(settings);

  return mq(SentrySettingsPage, { id: 'fof-sentry' });
}

function search(query: string): string[][] {
  const source = new GeneralSearchSource() as any;

  return source.lookup(app.registry.getData(), query.toLowerCase()).map((result: { tree: string[] }) => result.tree);
}

const DB_SECTION = 'Database Query Monitoring';
const PROFILE_RATE = 'Backend Profiling Sample Rate';
const N_PLUS_ONE_THRESHOLD = 'N+1 Detection Threshold';

describe('SentrySettingsPage', () => {
  it('registers every setting with the Admin extender', () => {
    const registered = app.registry
      .getSettings('fof-sentry')
      .filter((entry) => typeof entry !== 'function')
      .map((entry: any) => entry.setting);

    expect(registered).toEqual([
      'fof-sentry.dsn',
      'fof-sentry.dsn_backend',
      'fof-sentry.environment',
      'fof-sentry.user_feedback',
      'fof-sentry.send_emails_with_sentry_reports',
      'fof-sentry.monitor_performance',
      'fof-sentry.profile_rate',
      'fof-sentry.db.slow_query_threshold',
      'fof-sentry.db.n_plus_one_detection',
      'fof-sentry.db.n_plus_one_threshold',
      'fof-sentry.db.track_bindings',
      'fof-sentry.db.query_sample_rate',
      'fof-sentry.javascript',
      'fof-sentry.javascript.console',
      'fof-sentry.javascript.trace_sample_rate',
      'fof-sentry.javascript.replays_session_sample_rate',
      'fof-sentry.javascript.replays_error_sample_rate',
    ]);
  });

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

  it('reveals dependent settings as soon as the parent changes, before saving', () => {
    const page = render({ 'fof-sentry.monitor_performance': '0' });

    // The first slider on the page is the backend performance monitoring rate.
    page.setValue('input.SampleRateSlider-input', '20');

    expect(page).toContainRaw(DB_SECTION);
    expect(page).toContainRaw(PROFILE_RATE);
  });

  it('only shows the N+1 threshold when N+1 detection is on', () => {
    expect(render({ 'fof-sentry.monitor_performance': '20' })).not.toContainRaw(N_PLUS_ONE_THRESHOLD);
    expect(render({ 'fof-sentry.monitor_performance': '20', 'fof-sentry.db.n_plus_one_detection': '1' })).toContainRaw(N_PLUS_ONE_THRESHOLD);
  });

  it('disables the profiling slider when excimer is unavailable', () => {
    const page = render({ 'fof-sentry.monitor_performance': '20', 'fof-sentry.profile_rate': '10' });
    const sliders = page.find('input.SampleRateSlider-input');

    // monitor_performance first, profile_rate second.
    expect(sliders[0].hasAttribute('disabled')).toBe(false);
    expect(sliders[1].hasAttribute('disabled')).toBe(true);
  });

  it('hides the JavaScript sub-settings when JavaScript monitoring is off', () => {
    const page = render({ 'fof-sentry.javascript': '0' });

    expect(page).toContainRaw('Enable JavaScript Error Reporting');
    expect(page).not.toContainRaw('Capture Console Messages as Breadcrumbs');
    expect(page).not.toContainRaw('Frontend Performance Monitoring Sample Rate');
    expect(page).not.toContainRaw('Session Replay Sample Rate');
    expect(page).not.toContainRaw('Error-Triggered Replay Sample Rate');
  });

  it('shows the JavaScript sub-settings when JavaScript monitoring is on', () => {
    const page = render({ 'fof-sentry.javascript': '1' });

    expect(page).toContainRaw('Capture Console Messages as Breadcrumbs');
    expect(page).toContainRaw('Frontend Performance Monitoring Sample Rate');
    expect(page).toContainRaw('Session Replay Sample Rate');
    expect(page).toContainRaw('Error-Triggered Replay Sample Rate');
  });
});

describe('admin search', () => {
  it('finds settings by label, under their section', () => {
    useSettings({});

    expect(search('sentry dsn')).toContainEqual(['General Settings', 'Sentry DSN']);
    expect(search('session replay')).toContainEqual(['Frontend (JavaScript) Monitoring', 'Session Replay Sample Rate']);
  });

  it('finds settings by their help text', () => {
    useSettings({});

    expect(search('relay')).toContainEqual(['General Settings', 'Sentry DSN (Backend Only)']);
  });

  it('only finds dependent settings while they are visible', () => {
    useSettings({ 'fof-sentry.monitor_performance': '0' });
    expect(search('slow query')).toEqual([]);

    useSettings({ 'fof-sentry.monitor_performance': '20' });
    expect(search('slow query')).toContainEqual([DB_SECTION, 'Slow Query Threshold (milliseconds)']);
  });
});
