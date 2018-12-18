import SettingsModal from '@fof/components/admin/settings/SettingsModal';
import StringItem from '@fof/components/admin/settings/items/StringItem';

app.initializers.add('fof/sentry', () => {
    app.extensionSettings['fof-sentry'] = () => app.modal.show(
        new SettingsModal({
            title: 'FriendsOfFlarum Sentry',
            type: 'small',
            items: [
                <StringItem key="fof-sentry.dsn" type="url">
                    {app.translator.trans('fof-sentry.admin.settings.dsn_label')}
                </StringItem>
            ],
        })
    )
});
