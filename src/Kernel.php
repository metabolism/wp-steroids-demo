<?php

/**
 * Shared code across projects, defines default behaviour for WordPress
 * Add most used twig functions and filters
 * v1.2
 */


use Timber\Timber;
use Timber\URLHelper;
use Twig\Extra\Intl\IntlExtension;

abstract class Kernel extends \Timber\Site {

    private $options;

    /** Add timber support. */
    public function __construct() {

        $this->options = new Options();

        add_filter('network_site_url', [$this, 'networkSiteURL'] );
        add_filter('option_siteurl', [$this, 'optionSiteURL'] );

        add_filter('timber/post/pre_meta', function ($post_meta, $pid){

            $post = get_post($pid);

            if( $post->post_type == 'revision' && $post->post_parent )
                return get_post_meta($post->post_parent);

            return $post_meta;

        },10, 2);

        add_action( 'init', [$this, 'maintenance']);
        add_action( 'init', [$this, 'redirect']);

        add_filter( 'timber/context', [$this, 'addToContext'] );
        add_filter( 'timber/loader/twig', [$this, 'addTwigExtensions'] );
        add_filter( 'block_render_callback', [$this, 'renderBlock']);

        parent::__construct();
    }

    public function maintenance()
    {
        $path = rtrim($_SERVER['REQUEST_URI'], '/');

        if( function_exists('wp_maintenance_mode') && wp_maintenance_mode() && !is_admin() && !is_login() && !($path == '/edition' || $path == '/edition/') ){

            $templates = array( 'maintenance.twig' );
            $context = Timber::context();

            $context['wp_title'] = __( 'Maintenance' );

            if( $post_id = get_page_by_state('maintenance') )
                $context['post'] = \Timber::get_post($post_id);

            if( $html = Timber::compile( $templates, $context ) ){

                status_header(503);
                nocache_headers();

                echo $html;
                exit;
            }
            else{

                wp_die(__( 'Briefly unavailable for scheduled maintenance. Check back in a minute.' ), $context['wp_title'],503);
            }
        }
    }

    /**
     * @param $url
     * @return string
     */
    public function optionSiteURL($url)
    {
        return !str_contains($url, '/edition') ? $url.'/edition' : $url;
    }

    /**
     * @param $url
     * @return array|string|string[]
     */
    public function networkSiteURL($url)
    {
        if(!str_contains($url, '/edition'))
        {
            $url = str_replace('/wp-login', '/edition/wp-login', $url);
            return str_replace('/wp-admin', '/edition/wp-admin', $url);
        }
        else{

            return $url;
        }
    }

    public function redirect()
    {
        if( defined('DOING_CRON') && DOING_CRON )
            return;

        if( isset($_REQUEST['s']) && !is_admin() ){

            $permalink = get_search_link(sanitize_text_field($_REQUEST['s']));
            wp_redirect($permalink);
            exit;
        }

        $path = rtrim($_SERVER['REQUEST_URI'], '/');

        if( ($path == '/edition' || $path == '/edition/') && 'POST' !== $_SERVER['REQUEST_METHOD'] ){

            wp_redirect(is_user_logged_in() ? admin_url('index.php') : wp_login_url());
            exit;
        }
    }

    /**
     * @return false|int|mixed|string|null
     */
    public static function getPostId() {

        if ( $post_id = get_the_ID() )
            return $post_id;

        $post_id = isset( $_GET['post'] ) && (int) $_GET['post'] > 0 ? (int) $_GET['post'] : null;

        if ( ! empty( $post_id ) )
            return $post_id;

        $admin_url = isset( $_SERVER['HTTP_REFERER'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null;

        if ( ! empty( $admin_url ) ) {

            $parsed_url = parse_url( $admin_url );

            if ( isset( $parsed_url['query']) && ! empty( $parsed_url['query'] ) ) {

                $params = explode( '&', $parsed_url['query'] );

                foreach ( $params as $param ) {

                    $param = explode( '=', $param );

                    if ( $param[0] === 'post' )
                        $post_id = $param[1];
                }
            }
        }

        return $post_id;
    }

    /**
     * @param $block
     * @param $content
     * @param $is_preview
     * @return void
     */
    public static function renderBlock($block, $content = '', $is_preview = false){

        if( !($block['front']??true) && !is_admin() )
            return;

        $is_preview = $_REQUEST['query']['preview']??false;

        if( $is_preview && $image = $block['data']['_preview_image']??false ){

            echo '<img src="'.get_home_url().$image.'" style="width:100%;height:auto" class="preview_image"/>';
            return;
        }

        $context = Timber::context();
        $block_context = self::getBlockContext( $block, self::getPostId() );

        $context = array_merge( $context, $block_context );

        // Render the block.
        $name = str_replace('_', '-', str_replace('acf/', '', $block['name']??''));

        Timber::render( 'block/'.$name.'/'.$name.'.twig', $context );
    }

    /**
     * @param $block
     * @param $post_id
     * @param $is_preview
     * @return array
     */
    public static function getBlockContext($block, $post_id=false, $is_preview=false)
    {
        $context = [];

        if( $post_id )
            $context['post'] = Timber::get_post($post_id);

        $context['props'] = get_fields($block['id']);

        // Store field values.
        $context['block'] = $block;

        // Store $is_preview value.
        $context['is_preview'] = $is_preview;
        $context['is_admin'] = is_admin();
        $context['is_front_page'] = is_front_page();

        return $context;
    }

    /** This is where you add some context
     *
     * @param array $context context['this'] Being the Twig's {{ this }}.
     */
    public function addToContext( $context ) {

        global $_config;

        $context['environment'] = WP_ENV;
        $context['current_url'] = URLHelper::get_current_url();
        $context['blog']        = $this;
        $context['options']     = $this->options;
        $context['paged']       = get_query_var('paged', 1);

        $context['menu'] = [];

        $menus = $_config->get('menu.register',[]);

        foreach ($menus as $key=>$config){

            $context['menu'][$key] = Timber::get_menu($key);
        }

        if( is_archive() ){

            $archive_type = $this->getArchivePostType();
            $context['archive'] = $this->options->get($archive_type);

            if( is_array($context['archive']) ){

                $context['archive']['link'] = get_post_type_archive_link($archive_type);

                if( isset($context['archive']['title']) )
                    $context['wp_title'] = $context['archive']['title'];
            }
        }

        return $context;
    }

    /** This is where you can add your own functions to twig.
     *
     * @param Twig_Environment $twig get extension.
     */
    public function addTwigExtensions( $twig ) {

        $twig->addExtension( new IntlExtension());
        $twig->addExtension( new Twig\Extension\StringLoaderExtension() );

        $folder = __DIR__.'/Twig/';
        $files = scandir($folder);

        foreach($files as $file){

            if( !in_array($file, ['.','..']) )
            {
                $classname = str_replace('.php', '', $file);
                include_once $folder.'/'.$file;
                $twig->addExtension( new $classname() );
            }
        }

        return $twig;
    }
}
