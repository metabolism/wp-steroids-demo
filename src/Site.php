<?php

use Timber\ImageHelper;
use Timber\Menu;
use Timber\Timber;

class Site extends \Timber\Site {

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
        $name = str_replace('_', '-', str_replace('acf/', '', $block['name']));

        Timber::render( 'block/'.$name.'/'.$name.'.twig', $context );
    }


    /** This is where you add some context
     *
     * @param array $context context['this'] Being the Twig's {{ this }}.
     */
    public function add_to_context( $context ) {

        $context['menu'] = [
            'header'=>new Menu('header'),
            'footer'=>new Menu('footer')
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

    public function generatePicture($src, $width, $height=0, $sources=[], $alt=false, $loading='lazy') {

        if( $src instanceof \Timber\Image )
            $src = acf_get_attachment($src->id);
        elseif( is_int($src) )
            $src = acf_get_attachment($src);

        if( !$src )
            return '';

        $ext = function_exists('imagewebp') ? 'webp' : null;
        $mime = function_exists('imagewebp') ? 'image/webp' : $src['mime_type'];

        $html = '<picture>';

        if($src['mime_type'] == 'image/svg+xml' || $src['mime_type'] == 'image/svg' || $src['mime_type'] == 'image/gif' ){

            $html .= '<img src="'.$src['url'].'" alt="'.$src['alt'].'" loading="'.$loading.'" '.($width?'width="'.$width.'"':'').' '.($height?'height="'.$height.'"':'').'/>';
        }
        else {

            if ($sources && is_array($sources)) {

                foreach ($sources as $media => $size) {

                    if (is_int($media))
                        $media = 'max-width: ' . $media . 'px';

                    if ($ext == 'webp') {

                        $webp_src = ImageHelper::img_to_webp($src['url']);
                        $url = ImageHelper::resize($webp_src, $size[0], $size[1] ?? 0);

                        $html .= '<source media="(' . $media . ')" srcset="' . $url . '" type="' . $mime . '"/>';
                    }

                    $url = ImageHelper::resize($src['url'], $size[0], $size[1] ?? 0);
                    $html .= '<source media="(' . $media . ')" srcset="' . $url . '" type="' . $src['mime_type'] . '"/>';
                }
            }

            if ($ext == 'webp') {

                $webp_src = ImageHelper::img_to_webp($src['url']);
                $url = ImageHelper::resize($webp_src, $width, $height);
                $html .= '<source srcset="' . $url . '" type="image/webp"/>';
            }

            $url = ImageHelper::resize($src['url'], $width, $height);

            $au = ImageHelper::analyze_url($url);
            $upload_dir = wp_upload_dir();

            $image_info = getimagesize($upload_dir['basedir'].$au['subdir'].'/'.$au['basename']);

            $html .= '<img src="' . $url . '" alt="' . ($alt ?: $src['alt']) . '" loading="' . $loading . '" '.($image_info[0]?'width="'.$image_info[0].'"':'').' '.($image_info[1]?'height="'.$image_info[1].'"':'').'/>';
        }

        $html .='</picture>';

        return $html;
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

        $twig->addFilter( new Twig\TwigFilter( 'picture', [$this, 'generatePicture'] ) );

        return $twig;
    }

}

new Site();
