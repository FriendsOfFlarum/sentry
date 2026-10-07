import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { SettingsComponentOptions } from 'flarum/admin/components/AdminPage';
import type Mithril from 'mithril';
import type { SentrySettingConfig } from '../settingVisibility';

/**
 * Renders the settings registered in `extend.tsx`, so they stay searchable from the admin
 * header. Core's ExtensionPage ignores `visible`; this page honours it against live values.
 */
export default class SentrySettingsPage extends ExtensionPage {
  liveValue = (key: string): string | undefined => this.setting(key)();

  buildSettingComponent(entry: ((this: this) => Mithril.Children) | SettingsComponentOptions): Mithril.Children {
    if (typeof entry === 'function') {
      return super.buildSettingComponent(entry);
    }

    const { visible, tree, ...config } = entry as SentrySettingConfig;

    if (visible && !visible(this.liveValue)) {
      return null;
    }

    return super.buildSettingComponent(config as SettingsComponentOptions);
  }
}
