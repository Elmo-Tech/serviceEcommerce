<?php

declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=service_commerce_test');
putenv('FILESYSTEM_DISK=public');
putenv('APP_URL=http://127.0.0.1:8765');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicFile = __DIR__.'/../../public'.(is_string($path) ? $path : '');

if (is_file($publicFile)) {
    return false;
}

require __DIR__.'/../../public/index.php';
