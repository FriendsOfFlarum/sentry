import { settings } from '@fof-components';

const { SettingsModal, items: { StringItem, BooleanItem } } = settings;

app.initializers.add('fof/sentry', () => {
    app.extensionSettings['fof-sentry'] = () =>
        app.modal.show(
            new SettingsModal({
                title: 'FriendsOfFlarum Sentry',
                type: 'small',
                items: [
                    <StringItem key="fof-sentry.dsn" type="url" required>
                        {app.translator.trans('fof-sentry.admin.settings.dsn_label')}
                    </StringItem>,
                    <BooleanItem key="fof-sentry.user_feedback">{app.translator.trans('fof-sentry.admin.settings.user_feedback_label')}</BooleanItem>,
                    <BooleanItem key="fof-sentry.javascript">{app.translator.trans('fof-sentry.admin.settings.javascript_label')}</BooleanItem>,
                    <BooleanItem key="fof-sentry.javascript.console">
                        {app.translator.trans('fof-sentry.admin.settings.javascript_console_label')}
                    </BooleanItem>,
                ],
            })
        );
});
