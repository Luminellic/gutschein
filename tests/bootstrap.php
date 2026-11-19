<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/helpers.php';

date_default_timezone_set('Europe/Zurich');
App\Core\Env::set('APP_URL', 'https://gutscheine.test');
App\Core\Env::set('PAYMENT_PROVIDER', 'demo');
App\Core\Env::set('MAIL_DRIVER', 'log');
