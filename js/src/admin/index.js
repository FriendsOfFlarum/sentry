import SettingsModal from '@fof/components/admin/settings/SettingsModal';
import StringItem from '@fof/components/admin/settings/items/StringItem';
import BooleanItem from '@fof/components/admin/settings/items/BooleanItem';

app.initializers.add('fof/sentry', () => {
    app.extensionSettings['fof-sentry'] = () => app.modal.show(
        new SettingsModal({
            title: 'FriendsOfFlarum Sentry',
            type: 'small',
            items: [
                <StringItem key="fof-sentry.dsn" type="url">
                    {app.translator.trans('fof-sentry.admin.settings.dsn_label')}
                </StringItem>,
                <BooleanItem key="fof-sentry.user_feedback">
                    {app.translator.trans('fof-sentry.admin.settings.user_feedback_label')}
                </BooleanItem>
            ],
        })
    )
});
