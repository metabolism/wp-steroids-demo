<?php
/**
 * Your base production configuration goes in this file. Environment-specific
 * overrides go in their respective config/environments/{{WP_ENV}}.php file.
 *
 * A good default policy is to deviate from the production config as little as
 * possible. Try to define as much of your configuration in this file as you
 * can.
 */

use Roots\WPConfig\Config;
use function Env\env;

// USE_ENV_ARRAY + CONVERT_* + STRIP_QUOTES
Env\Env::$options = 31;

/**
 * Directory containing all of the site's files
 *
 * @var string
 */
$root_dir = dirname(__DIR__);

/**
 * Document Root
 *
 * @var string
 */
$webroot_dir = $root_dir . '/public';

/**
 * Use Dotenv to set required environment variables and load .env file in root
 * .env.local will override .env if it exists
 */
if (file_exists($root_dir . '/.env')) {
$env_files = file_exists($root_dir . '/.env.local')
    ? ['.env', '.env.local']
    : ['.env'];

    $dotenv = Dotenv\Dotenv::createImmutable($root_dir, $env_files, false);

    $dotenv->load();
    $dotenv->required(['WP_HOME', 'WP_SITEURL']);
    if (!env('DATABASE_URL')) {
        $dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD']);
    }
}

/**
 * Load Bugsnag error reporter
 */
if( env('BUGSNAG_API_KEY') ){

    define('BUGSNAG_API_KEY', env('BUGSNAG_API_KEY'));

    global $bugsnag;

    $bugsnag = Bugsnag\Client::make(env('BUGSNAG_API_KEY'));
    Bugsnag\Handler::register($bugsnag);
}

/**
 * Set up our global environment constant and load its config first
 * Default: production
 */
define('WP_ENV', env('WP_ENV') ?: 'production');

/**
 * Infer WP_ENVIRONMENT_TYPE based on WP_ENV
 */
if (!env('WP_ENVIRONMENT_TYPE') && in_array(WP_ENV, ['production', 'staging', 'development', 'local'])) {
    Config::define('WP_ENVIRONMENT_TYPE', WP_ENV);
}

/**
 * URLs
 */
Config::define('WP_HOME', env('WP_HOME'));
Config::define('WP_SITEURL', env('WP_SITEURL'));

if( env('WP_DEFAULT_DOMAIN') )
    Config::define('WP_DEFAULT_DOMAIN', env('WP_DEFAULT_DOMAIN'));

/**
 * Custom Content Directory
 */
Config::define('CONTENT_DIR', '/app');
Config::define('WP_CONTENT_DIR', $webroot_dir . Config::get('CONTENT_DIR'));
Config::define('WP_CONTENT_URL', Config::get('WP_HOME') . Config::get('CONTENT_DIR'));

/**
 * DB settings
 */
if (env('DB_SSL')) {

    Config::define('MYSQL_CLIENT_FLAGS', MYSQLI_CLIENT_SSL);

    if (env('DB_SSL_CA'))
        Config::define('MYSQL_SSL_CERT', env('DB_SSL_CA'));
}

Config::define('DB_NAME', env('DB_NAME'));
Config::define('DB_USER', env('DB_USER'));
Config::define('DB_PASSWORD', env('DB_PASSWORD'));
Config::define('DB_HOST', env('DB_HOST') ?: 'localhost');
Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');

$table_prefix = env('DB_PREFIX') ?: 'wps_';

if (env('DATABASE_URL')) {

    $dsn = (object) parse_url(env('DATABASE_URL'));

    Config::define('DB_NAME', substr($dsn->path, 1));
    Config::define('DB_USER', $dsn->user);
    Config::define('DB_PASSWORD', $dsn->pass ?? null);
    Config::define('DB_HOST', isset($dsn->port) ? "{$dsn->host}:{$dsn->port}" : $dsn->host);
}

/**
 * Using managed identity to fetch MySQL access token
 */
if ( env('ENABLE_MYSQL_MANAGED_IDENTITY') ) {

    try {

        if( !file_exists($root_dir . '/.azure/EntraID_Database_Token_Utilities.php') )
            throw new Exception('EntraID_Database_Token_Utilities.php not found');

        require_once($root_dir . '/.azure/EntraID_Database_Token_Utilities.php');

        if (strtolower(getenv('CACHE_MYSQL_ACCESS_TOKEN')) !== 'true')
            $dbpassword = EntraID_Database_Token_Utilities::getAccessToken();
        else
            $dbpassword = EntraID_Database_Token_Utilities::getOrUpdateAccessTokenFromCache();
    }
    catch (Exception $e) {

        $dbpassword = '<dummy-value>';

        error_log($e->getMessage());
    }

    Config::define('DB_PASSWORD', $dbpassword);
}

/**
 * Authentication Unique Keys and Salts
 */
Config::define('AUTH_KEY', env('AUTH_KEY'));
Config::define('SECURE_AUTH_KEY', env('SECURE_AUTH_KEY'));
Config::define('LOGGED_IN_KEY', env('LOGGED_IN_KEY'));
Config::define('NONCE_KEY', env('NONCE_KEY'));
Config::define('AUTH_SALT', env('AUTH_SALT'));
Config::define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT'));
Config::define('LOGGED_IN_SALT', env('LOGGED_IN_SALT'));
Config::define('NONCE_SALT', env('NONCE_SALT'));

/**
 * Define proxy for http request
 */
if( $proxy_host = env('WP_PROXY_HOST') ) {

    Config::define('WP_PROXY_HOST', $proxy_host);
    Config::define('WP_PROXY_PORT', env('WP_PROXY_PORT')?:'8080');

    if( $proxy_username = env('WP_PROXY_USERNAME') ){

        Config::define('WP_PROXY_USERNAME', $proxy_username);
        Config::define('WP_PROXY_PASSWORD', env('WP_PROXY_PASSWORD')?:'');
    }

    if( $proxy_bypass_hosts = env('WP_PROXY_BYPASS_HOSTS') )
        Config::define('WP_PROXY_BYPASS_HOSTS', $proxy_bypass_hosts);
}

/**
 * Custom Settings
 */

if( $multisite = env('MULTISITE') ){

    $multisite = is_bool($multisite) ? env('WP_HOME') : $multisite;
    $domain = preg_replace( '|https?://|', '', $multisite );
    $slash  = strpos( $domain, '/' );

    if ( $slash )
        $domain = substr( $domain, 0, $slash );

    Config::define( 'MULTISITE', true );
    Config::define( 'SUBDOMAIN_INSTALL', false );
    Config::define( 'DOMAIN_CURRENT_SITE', $domain );
    Config::define( 'PATH_CURRENT_SITE', '/' );
    Config::define( 'SITE_ID_CURRENT_SITE', 1 );
    Config::define( 'BLOG_ID_CURRENT_SITE', 1 );
}
else{

    Config::define('WP_ALLOW_MULTISITE', true);
}

// Disable auto update, only use composer
Config::define('AUTOMATIC_UPDATER_DISABLED', true);
// Disable cron execution on front
Config::define('DISABLE_WP_CRON', env('DISABLE_WP_CRON') ?: false);
// Disable the plugin and theme file editor in the admin
Config::define('DISALLOW_FILE_EDIT', true);
// Disable plugin and theme updates and installation from the admin
Config::define('DISALLOW_FILE_MODS', WP_ENV != 'development');
// Limit the number of post revisions that WordPress stores (true (default WP): store every revision)
Config::define('WP_POST_REVISIONS', env('WP_POST_REVISIONS') ?: 10);
// Increase memory limit
Config::define('WP_MEMORY_LIMIT', env('WP_MEMORY_LIMIT') ?: '128M');
Config::define('WP_MAX_MEMORY_LIMIT', env('WP_MAX_MEMORY_LIMIT') ?: '256M');
// Allow cache
Config::define('WP_CACHE', env('WP_CACHE') ?? false);
// Define file system method
Config::define('FS_METHOD', env('FS_METHOD') ?? 'direct');

/**
 * Redefine cookie name without WordPress
 */
Config::define( 'COOKIEHASH', md5( config::get('WP_SITEURL') )  );

if( $cookie_prefix = env('COOKIE_PREFIX') ) {

    Config::define('USER_COOKIE', $cookie_prefix . '_user_' . config::get('COOKIEHASH'));
    Config::define('PASS_COOKIE', $cookie_prefix . '_pass_' . config::get('COOKIEHASH'));
    Config::define('AUTH_COOKIE', $cookie_prefix . '_' . config::get('COOKIEHASH'));
    Config::define('SECURE_AUTH_COOKIE', $cookie_prefix . '_sec_' . config::get('COOKIEHASH'));
    Config::define('LOGGED_IN_COOKIE', $cookie_prefix . '_logged_in_' . config::get('COOKIEHASH'));
    Config::define('TEST_COOKIE', 'test_cookie_' . config::get('COOKIEHASH'));
}

/**
 * Allow WordPress to detect HTTPS when used behind a reverse proxy or a load balancer
 * See https://codex.wordpress.org/Function_Reference/is_ssl#Notes
 */
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

$env_config = __DIR__ . '/environments/' . WP_ENV . '.php';

if (file_exists($env_config))
    require_once $env_config;

/**
 * WP Steroids specifics
 */
Config::define('WPS_YAML_FILE', __DIR__.'/app.yml');
Config::define('GOOGLE_MAP_API_KEY', env('GOOGLE_MAP_API_KEY') ?: false);
Config::define('DEEPL_KEY', env('DEEPL_KEY') ?: false);

Config::apply();

/**
 * Bootstrap WordPress
 */
if (!defined('ABSPATH'))
    define('ABSPATH', $webroot_dir . '/edition/');
