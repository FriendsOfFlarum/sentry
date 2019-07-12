@extends('flarum.forum::error.default')

<script src="https://browser.sentry-cdn.com/5.5.0/bundle.min.js" crossorigin="anonymous"></script>

<script>
    Sentry.init({ dsn: '{{ app('flarum.settings')->get('fof-sentry.dsn') }}' });
    Sentry.showReportDialog({
        title: '{{ $message }}',
        lang: '{{ app('translator')->getLocale() }}',
        eventId: '{{ app('sentry')->getLastEventId() }}',
        @isset ($user)
            user: {
                email: '{{ $user->email }}',
                name: '{{ $user->username }}'
            },
        @endisset
    });
</script>