<?php

/**
 * A pseudo-cron daemon for scheduling WordPress tasks on Multisite.
 */

declare(strict_types=1);

ignore_user_abort( true );

if( php_sapi_name() != 'cli'){

    http_response_code(404);
    die();
}

define( 'WP_USE_THEMES', false);
define( 'DOING_CRON', true );

$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'local-cron';

require __DIR__ . '/edition/wp-load.php';

wp_raise_memory_limit( 'cron' );

if (!function_exists('switch_to_blog')) {
    require_once ABSPATH . 'wp-includes/ms-blogs.php';
}

function _get_cron_lock() {
    global $wpdb;

    $value = 0;
    if ( wp_using_ext_object_cache() ) {
        /*
         * Skip local cache and force re-fetch of doing_cron transient
         * in case another process updated the cache.
         */
        $value = wp_cache_get( 'doing_cron', 'transient', true );
    } else {
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", '_transient_doing_cron' ) );
        if ( is_object( $row ) ) {
            $value = $row->option_value;
        }
    }

    return $value;
}

function _run_crons()
{
    $crons = wp_get_ready_cron_jobs();

    if ( empty( $crons ) ) {
        return;
    }

    $gmt_time = microtime( true );

    $doing_wp_cron = get_transient( 'doing_cron' );

    foreach ( $crons as $timestamp => $cronhooks ) {

        if ( $timestamp > $gmt_time ) {
            break;
        }

        foreach ( $cronhooks as $hook => $keys ) {

            foreach ( $keys as $k => $v ) {

                $schedule = $v['schedule'];

                if ( $schedule ) {
                    $result = wp_reschedule_event( $timestamp, $schedule, $hook, $v['args'], true );

                    if ( is_wp_error( $result ) ) {
                        error_log(
                            sprintf(
                            /* translators: 1: Hook name, 2: Error code, 3: Error message, 4: Event data. */
                                __( 'Cron reschedule event error for hook: %1$s, Error code: %2$s, Error message: %3$s, Data: %4$s' ),
                                $hook,
                                $result->get_error_code(),
                                $result->get_error_message(),
                                wp_json_encode( $v )
                            )
                        );

                        /**
                         * Fires if an error happens when rescheduling a cron event.
                         *
                         * @since 6.1.0
                         *
                         * @param WP_Error $result The WP_Error object.
                         * @param string   $hook   Action hook to execute when the event is run.
                         * @param array    $v      Event data.
                         */
                        do_action( 'cron_reschedule_event_error', $result, $hook, $v );
                    }
                }

                $result = wp_unschedule_event( $timestamp, $hook, $v['args'], true );

                if ( is_wp_error( $result ) ) {
                    error_log(
                        sprintf(
                        /* translators: 1: Hook name, 2: Error code, 3: Error message, 4: Event data. */
                            __( 'Cron unschedule event error for hook: %1$s, Error code: %2$s, Error message: %3$s, Data: %4$s' ),
                            $hook,
                            $result->get_error_code(),
                            $result->get_error_message(),
                            wp_json_encode( $v )
                        )
                    );

                    /**
                     * Fires if an error happens when unscheduling a cron event.
                     *
                     * @since 6.1.0
                     *
                     * @param WP_Error $result The WP_Error object.
                     * @param string   $hook   Action hook to execute when the event is run.
                     * @param array    $v      Event data.
                     */
                    do_action( 'cron_unschedule_event_error', $result, $hook, $v );
                }

                /**
                 * Fires scheduled events.
                 *
                 * @ignore
                 * @since 2.1.0
                 *
                 * @param string $hook Name of the hook that was scheduled to be fired.
                 * @param array  $args The arguments to be passed to the hook.
                 */
                do_action_ref_array( $hook, $v['args'] );

                // If the hook ran too long and another cron process stole the lock, quit.
                if ( _get_cron_lock() !== $doing_wp_cron ) {
                    return;
                }
            }
        }
    }

    if ( _get_cron_lock() === $doing_wp_cron ) {
        delete_transient( 'doing_cron' );
    }
}

if( !is_multisite() ){

    $lock = get_option('doing_cron');
    if ($lock && is_numeric($lock) && (time() - (int)$lock) > 600) {
        delete_option('doing_cron');
    }

    _run_crons();
}
else{

    $site_ids = get_sites([
        'number' => 0,
        'fields' => 'ids',
    ]);

    global $blog_id;

    foreach ($site_ids as $site_id) {

        $blog_id = 0;
        switch_to_blog((int)$site_id);

        $lock = get_option('doing_cron');

        if ($lock && is_numeric($lock) && (time() - (int)$lock) > 600) {
            delete_option('doing_cron');
        }

        _run_crons();

        restore_current_blog();
    }
}

exit(0);
