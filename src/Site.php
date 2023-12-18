<?php

/**
 * Activate Timber theme to use this file
 *
 * Write specific project code here
 * Kernel extends \Timber\Site to add useful functions
 *
 */

use Timber\Timber;

class Site extends Kernel {

    public function __construct()
    {
        parent::__construct();

        // Disable dashboard for non admin/editor
        add_action('admin_init', function() {

            if ( !is_user_logged_in() )
                return null;

            if ( !current_user_can('editor') && !current_user_can('administrator') && !wp_doing_ajax() ) {

                wp_redirect(home_url(), 301);
                exit;
            }
        });

        // Hide toolbar for non admin/editor
        add_action('init', function() {

            if ( !current_user_can('edit_posts') )
                add_filter('show_admin_bar', '__return_false');
        });
    }

    public function addToContext($context)
    {
        $context = parent::addToContext($context);

        $context['menu'] = [
            'header'=>Timber::get_menu('header'),
            'footer'=>Timber::get_menu('footer')
        ];

        return $context;
    }
}

new Site();