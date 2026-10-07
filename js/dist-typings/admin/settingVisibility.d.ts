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
export declare const savedValue: SettingValue;
export declare const performanceMonitoringEnabled: (value?: SettingValue) => boolean;
export declare const nPlusOneDetectionEnabled: (value?: SettingValue) => boolean;
export declare const javascriptEnabled: (value?: SettingValue) => boolean;
