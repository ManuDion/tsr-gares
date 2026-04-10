<?php

if (! function_exists('app_icon')) {
    function app_icon(string $key): string
    {
        return app('icon.map')[$key] ?? '';
    }
}
