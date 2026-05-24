<?php

function app_base_path()
{
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = trim($script, '/');

    if (in_array(basename($script), ['admin', 'instructor', 'student'], true)) {
        return '..';
    }

    return '.';
}

function asset_path($path)
{
    return app_base_path() . '/' . ltrim($path, '/');
}
