<?php
/**
 * Configuration overrides for WP_ENV === 'production'
 */

use Roots\WPConfig\Config;
use function Env\env;

Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', false);
Config::define('SCRIPT_DEBUG', false);

Config::define('WP_CACHE', true);

@ini_set('display_errors', '0');