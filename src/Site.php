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

    private $context;

    public function __construct()
    {
        parent::__construct();

        $this->loadClasses('Service');

        $this->context = $this->loadClass('Context', true);

        // Update context based on filename
        add_filter('timber/render/data', [$this, 'updateContext'],10, 2);

        // Add custom twig functions
        add_filter('timber/twig', [$this, 'addToTwig'] );

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

    /**
     * @param string $path
     * @return string
     */
    private function pathToFunctionName(string $path) {

        $path = preg_replace('/\.twig$/', '', $path);

        $path = str_replace('/', ' ', $path);

        $parts = array_unique(explode(' ', $path));

        $parts = array_map(function($part) {
            $words = explode('-', $part);
            return implode('', array_map('ucfirst', $words));
        }, $parts);

        return lcfirst(implode('', $parts));
    }

    /**
     * @param $context
     * @param $file
     * @return mixed
     */
    public function updateContext($context, $file){

        if( !is_array( $context['props']??false ) )
            $context['props'] = [];

        $method = $this->pathToFunctionName($file);

        if( method_exists($this->context, $method) )
            $context['props'] = $this->context->$method($context['props'], $context['block']);

        return $context;
    }

    /** This is where you can add your own functions to twig.
     *
     * @param Twig_Environment $twig get extension.
     */
    public function addToTwig( $twig ) {

        $twig->addFilter( new Twig\TwigFilter( 'protect', [$this,'protectEmail'] ) );

        return $twig;
    }

    /**
     * Email string verification.
     *
     * @param        $text
     * @return mixed
     */
    public function protectEmail($text)
    {
        if( !$text )
            return;

        preg_match_all( '/<a (.*)href="mailto:([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})"(.*)>(.*)<\/a>/', $text, $potentialEmails, PREG_SET_ORDER );

        $potentialEmailsCount = count( $potentialEmails );

        for ( $i = 0; $i < $potentialEmailsCount; $i++ )
        {
            $potentialEmail = $potentialEmails[$i];

            if ( filter_var( $potentialEmail[2], FILTER_VALIDATE_EMAIL ) )
            {
                $email = $potentialEmail[2];
                $email = explode( '@', $email );

                if( filter_var( $potentialEmail[4], FILTER_VALIDATE_EMAIL ) )
                    $text = str_replace( $potentialEmail[0], '<email ' . $potentialEmail[1] .$potentialEmail[3] . ' name="' . $email[0] . '" domain="' . $email[1] . '" text="">@</email>', $text );
                else
                    $text = str_replace( $potentialEmail[0], '<email ' . $potentialEmail[1] .$potentialEmail[3] . ' name="' . $email[0] . '" domain="' . $email[1] . '" text="'.$potentialEmail[4].'">@' . $potentialEmail[4] . '</email>', $text );
            }
        }

        preg_match_all( '/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})/', $text, $potentialEmails, PREG_SET_ORDER );

        $potentialEmailsCount = count( $potentialEmails );

        for ( $i = 0; $i < $potentialEmailsCount; $i++ )
        {
            if ( filter_var( $potentialEmails[$i][0], FILTER_VALIDATE_EMAIL ) )
            {
                $email = $potentialEmails[$i][0];
                $email = explode( '@', $email );

                $text = str_replace( $potentialEmails[$i][0], '<email name="' . $email[0] . '" domain="' . $email[1] . '" text="">@' . $email[0] . '</email>', $text );
            }
        }

        return new \Twig\Markup($text, 'UTF-8');
    }
}

new Site();