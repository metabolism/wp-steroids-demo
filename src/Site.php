<?php

class Site extends Timber\Site {

    private $entrypoints;
    private $manifest;

    /** Add timber support. */
    public function __construct() {

        add_filter( 'timber/context', array( $this, 'add_to_context' ) );
        add_filter( 'timber/twig', array( $this, 'add_to_twig' ) );
        add_filter( 'block_render_callback', function (){ return [self::class, 'renderBlock']; });

        if( file_exists(__DIR__.'/../public/build/entrypoints.json'))
            $this->entrypoints = json_decode(file_get_contents(__DIR__.'/../public/build/entrypoints.json'), true);

        if( file_exists(__DIR__.'/../public/build/manifest.json'))
            $this->manifest = json_decode(file_get_contents(__DIR__.'/../public/build/manifest.json'), true);

        if( is_admin() )
            add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);

        parent::__construct();
    }


    /**
     * @return void
     */
    function enqueue_block_editor_assets() {

        if ( in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1')) ){

            wp_enqueue_style('block_editor_style','http://localhost:8080/build/bundle.css');
        }
        else{

            $path = $this->manifest['build/bundle.css']??false;

            if( is_multisite() )
                wp_enqueue_style('block_editor_style', network_home_url($path));
            else
                wp_enqueue_style('block_editor_style', home_url($path));
        }
    }

    public static function renderBlock($block, $content = '', $is_preview = false){

        $context = Timber::context();

        $block['custom_fields'] = get_fields();

        // Store field values.
        $context['block'] = $block;

        // Store $is_preview value.
        $context['is_preview'] = $is_preview;

        // Render the block.
        $name = str_replace('acf/', '', $block['name']);

        Timber::render( 'block/'.$name.'/'.$name.'.twig', $context );
    }


    /** This is where you add some context
     *
     * @param array $context context['this'] Being the Twig's {{ this }}.
     */
    public function add_to_context( $context ) {

        $context['menu'] = [
            'header'=>new Timber\Menu('header'),
            'footer'=>new Timber\Menu('footer')
        ];

        $context['environment'] = WP_ENV;
        $context['blog'] = $this;

        return $context;
    }

    public function renderWebpackLinkTags( $entryName ) {

        $entries = $this->entrypoints['entrypoints'][$entryName]['css']??[];

        $styles = '';

        foreach ($entries as $entry)
            $styles .= "<link rel='stylesheet' href='{$entry}' type='text/css' media='all' />";

        return $styles;
    }

    public function renderWebpackScriptTags( $entryName ) {

        $entries = $this->entrypoints['entrypoints'][$entryName]['js']??[];

        $styles = '';

        foreach ($entries as $entry)
            $styles .= "<script type='text/javascript' src='{$entry}' defer></script>";

        return $styles;
    }

    /** This is where you can add your own functions to twig.
     *
     * @param Twig_Environment $twig get extension.
     */
    public function add_to_twig( $twig ) {

        $twig->addExtension( new Twig\Extension\StringLoaderExtension() );

        $twig->addFunction( new Twig\TwigFunction( 'wp_head', 'wp_head' ) );
        $twig->addFunction( new Twig\TwigFunction( 'wp_footer', 'wp_footer' ) );
        $twig->addFunction( new Twig\TwigFunction( 'encore_entry_link_tags', [$this, 'renderWebpackLinkTags'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'encore_entry_script_tags', [$this, 'renderWebpackScriptTags'] ) );

        return $twig;
    }

}

new Site();
