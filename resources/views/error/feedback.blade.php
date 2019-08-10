@extends($errorView)

<script src="https://browser.sentry-cdn.com/5.6.1/bundle.min.js" integrity="sha384-pGTFmbQfua2KiaV2+ZLlfowPdd5VMT2xU4zCBcuJr7TVQozMO+I1FmPuVHY3u8KB" crossorigin="anonymous"></script>

<script>
    Sentry.init({ dsn: '{{ app('flarum.settings')->get('fof-sentry.dsn') }}' });
    Sentry.showReportDialog({
        title: '{{ $message }}',
        lang: '{{ app('translator')->getLocale() }}',
        eventId: '{{ app('sentry')->getLastEventId() }}',
        @if ($user != null && $user->id != 0)
            user: {
                email: '{{ $user->email }}',
                name: '{{ $user->username }}'
            },
        @endif
    });
</script>
