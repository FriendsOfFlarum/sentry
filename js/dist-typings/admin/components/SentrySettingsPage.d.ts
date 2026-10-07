import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { SettingsComponentOptions } from 'flarum/admin/components/AdminPage';
import type Mithril from 'mithril';
/**
 * Renders the settings registered in `extend.tsx`, so they stay searchable from the admin
 * header. Core's ExtensionPage ignores `visible`; this page honours it against live values.
 */
export default class SentrySettingsPage extends ExtensionPage {
    liveValue: (key: string) => string | undefined;
    buildSettingComponent(entry: ((this: this) => Mithril.Children) | SettingsComponentOptions): Mithril.Children;
}
