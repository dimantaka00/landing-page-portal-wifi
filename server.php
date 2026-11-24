<?php

// Router untuk preview lokal menggunakan server bawaan PHP.
$public = realpath(__DIR__.'/public');
$path = realpath($public.'/'.urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'));
if ($path !== false && str_starts_with($path, $public.DIRECTORY_SEPARATOR) && is_file($path)) {
    return false;
}
require $public.'/index.php';
