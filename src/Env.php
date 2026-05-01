<?php

class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) return;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            putenv(trim($k) . '=' . trim($v));
        }
    }
}
