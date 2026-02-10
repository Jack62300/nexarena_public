<?php

use App\Kernel;

// Fix SSL certificates on Windows (WAMP)
if (PHP_OS_FAMILY === 'Windows' && !ini_get('curl.cainfo')) {
    $caFile = 'C:\\wamp64\\bin\\php\\php8.3.6\\extras\\ssl\\cacert.pem';
    if (file_exists($caFile)) {
        putenv('SSL_CERT_FILE=' . $caFile);
        putenv('CURL_CA_BUNDLE=' . $caFile);
    }
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
