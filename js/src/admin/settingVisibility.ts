import app from 'flarum/admin/app';
import type Mithril from 'mithril';
import type { SettingsComponentOptions } from 'flarum/admin/components/AdminPage';

export type SettingValue = (key: string) => string | undefined;

/**
 * `visible` and `tree` are read by core's admin search (GeneralSearchSource). The search calls
 * `visible()` without arguments, so it sees saved values; SentrySettingsPage passes its live,
 * unsaved values so dependent settings appear as soon as their parent is changed.
 */
export type SentrySettingConfig = SettingsComponentOptions & {
  visible?: (value?: SettingValue) => boolean;
  tree?: Mithril.Children[];
};

export const savedValue: SettingValue = (key) => app.data.settings[key];

export const performanceMonitoringEnabled = (value: SettingValue = savedValue) => (Number(value('fof-sentry.monitor_performance')) || 0) > 0;

export const nPlusOneDetectionEnabled = (value: SettingValue = savedValue) =>
  performanceMonitoringEnabled(value) && value('fof-sentry.db.n_plus_one_detection') === '1';

export const javascriptEnabled = (value: SettingValue = savedValue) => value('fof-sentry.javascript') !== '0';
