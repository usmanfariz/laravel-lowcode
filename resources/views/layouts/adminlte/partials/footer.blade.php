<footer class="main-footer">
    <strong>{{ setting('app_name', config('app.name')) }}</strong>
    @if ($perusahaan = setting('company_name'))
        <span class="text-muted">&mdash; {{ $perusahaan }}</span>
    @endif
    <div class="float-right d-none d-sm-inline">
        {{ setting('footer_text') ?: 'Laravel '.app()->version() }}
    </div>
</footer>
