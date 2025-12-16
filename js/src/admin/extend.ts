import Extend from 'flarum/common/extenders';
import SentrySettingsPage from './components/SentrySettingsPage';

export default [
    new Extend.Admin() //
        .page(SentrySettingsPage),
];
