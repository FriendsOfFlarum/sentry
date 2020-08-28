import { settings } from '@fof-components';

const {
    SettingsModal,
    items: { StringItem, BooleanItem },
} = settings;

app.initializers.add('fof/sentry', () => {
    app.extensionSettings['fof-sentry'] = () =>
        app.modal.show(SettingsModal, {
            title: 'FriendsOfFlarum Sentry',
            type: 'small',
            items: (s) => [
                <StringItem name="fof-sentry.dsn" type="url" required setting={s}>
                    {app.translator.trans('fof-sentry.admin.settings.dsn_label')}
                </StringItem>,
                <BooleanItem name="fof-sentry.user_feedback" setting={s}>
                    {app.translator.trans('fof-sentry.admin.settings.user_feedback_label')}
                </BooleanItem>,
                <BooleanItem name="fof-sentry.javascript" setting={s}>
                    {app.translator.trans('fof-sentry.admin.settings.javascript_label')}
                </BooleanItem>,
                <BooleanItem name="fof-sentry.javascript.console" setting={s}>
                    {app.translator.trans('fof-sentry.admin.settings.javascript_console_label')}
                </BooleanItem>,
            ],
        });
});
