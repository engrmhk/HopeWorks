@php
    $compiled = '';
    try {
        $compiled = app(\App\Services\Themes\ThemeCompiler::class)->compileCss();
    } catch (\Throwable) {
        $compiled = '';
    }
    $base = file_get_contents(resource_path('css/hopeworks-tokens.css'));
@endphp
<style>
{!! $base !!}
{!! $compiled !!}
</style>
