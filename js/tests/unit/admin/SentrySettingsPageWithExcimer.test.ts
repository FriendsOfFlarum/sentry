import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import mq from 'mithril-query';
import flatten from 'flat';
import jsYaml from 'js-yaml';
import fs from 'fs';
import path from 'path';
import * as adminModule from '../../../src/admin';
import SentrySettingsPage from '../../../src/admin/components/SentrySettingsPage';

// Kept apart from SentrySettingsPage.test.ts: Excimer availability is fixed for the whole boot.
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
    hasExcimer: true,
  });

  const locale = path.resolve(process.cwd(), '../resources/locale/en.yml');
  app.translator.addTranslations(flatten(jsYaml.load(fs.readFileSync(locale, 'utf8'))));

  app.bootExtensions({ 'fof-sentry': adminModule });
});

describe('SentrySettingsPage with Excimer installed', () => {
  it('boots the admin and enables the profiling slider', () => {
    expect(() => app.boot()).not.toThrow();

    app.data.settings = { extensions_enabled: JSON.stringify(['fof-sentry']), 'fof-sentry.monitor_performance': '20' };
    const page = mq(SentrySettingsPage, { id: 'fof-sentry' });

    // monitor_performance first, profile_rate second.
    expect(page.find('input.SampleRateSlider-input')[1].hasAttribute('disabled')).toBe(false);
  });
});
