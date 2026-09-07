<?php

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

/** To set under maintenance status */
const UNDER_MAINTENANCE = false;
const MAINTENANCE_ALLOWED_IPS = [];
const MAINTENANCE_TRUSTED_PROXIES = [];

if (UNDER_MAINTENANCE) {
    require_once __DIR__.'/maintenance/maintenance.php';
    if (!Maintenance\Gate::isAllowedIp(MAINTENANCE_ALLOWED_IPS, MAINTENANCE_TRUSTED_PROXIES)) {
        Maintenance\Gate::render();
    }
}

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
