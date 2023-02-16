<?php

use Timber\ImageHelper;
use Timber\Menu;
use Timber\Timber;

class Site extends \Timber\Site {

    private $entrypoints;
    private $manifest;
    private $translations;

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

        $context['props'] = get_fields();

        // Store field values.
        $context['block'] = $block;

        // Store $is_preview value.
        $context['is_preview'] = $is_preview;

        // Render the block.
        $name = str_replace('_', '-', str_replace('acf/', '', $block['name']));

        Timber::render( 'block/'.$name.'/'.$name.'.twig', $context );
    }

    private function get_translations()
    {
        $options = get_fields('option');

        if( $translations = $options['translations']??false )
        {
            $this->translations = [];
            foreach ($translations as $translation)
            {
                $key = sanitize_title($translation['key']);
                $this->translations[$key] = $translation['translation'];
            }
        }
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
        $context['options'] = get_fields('option');

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

    /**
     * @param $text
     * @param array $params
     * @return string
     */
    public function translate($text, $params=[])
    {
        $key = sanitize_title($text);
        $params = (array)$params;

        if( isset($this->translations[$key]) ){

            return vsprintf($this->translations[$key], $params);
        }
        else{

            if( $_GET['debug']??'' == 'translation' && $_ENV['APP_ENV'] == 'dev' )
                return '¿@ '.htmlspecialchars($text).' @?';

            return vsprintf($text, $params);
        }
    }

    /**
     * Email string verification.
     *
     * @param        $text
     * @return mixed
     */
    public function protectEmail($text)
    {
        preg_match_all( '/<a (.*)href="mailto:([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6})"(.*)>(.*)<\/a>/', $text, $potentialEmails, PREG_SET_ORDER );

        $potentialEmailsCount = count( $potentialEmails );

        for ( $i = 0; $i < $potentialEmailsCount; $i++ )
        {
            $potentialEmail = $potentialEmails[$i];

            if ( filter_var( $potentialEmail[2], FILTER_VALIDATE_EMAIL ) )
            {
                $email = $potentialEmail[2];
                $email = explode( '@', $email );

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

                $text = str_replace( $potentialEmails[$i][0], '<email name="' . $email[0] . '" domain="' . $email[1] . '">@' . $email[0] . '</email>', $text );
            }
        }

        return new \Twig\Markup($text, 'UTF-8');;
    }


    /**
     * Returns the video ID of a youtube video.
     *
     * @param $url
     * @return string
     */
    public function youtubeID($url)
    {
        preg_match( '/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&">]+)/', $url, $matches );

        return count( $matches ) > 1 ? $matches[1] : '';
    }


    /**
     * @param $text
     * @return mixed
     */
    public function encode($text)
    {
        return substr($text, 0,1).base64_encode(str_replace('@','$', $text));
    }

    /**
     * @return string
     */
    public function formatPhone($text)
    {
        return chunk_split($text, 2, ' ');
    }

    /**
     * @param $text
     * @return \Twig\Markup
     */
    public function spaceToSpan($text)
    {
        $text = explode(' ', $text);
        $html = '<span>'.implode('</span><span>', $text).'</span>';

        return new \Twig\Markup($html, 'UTF-8');
    }

    /**
     * @param $text
     * @return \Twig\Markup
     */
    public function lineBreakToP($text)
    {
        $text = explode("\n", $text);
        $html = '<p>'.implode('</p><p>', array_filter($text)).'</p>';
        $html = str_replace("<p>\r</p>", '', $html);

        return new \Twig\Markup($html, 'UTF-8');
    }


    /**
     * Returns a proper url
     *
     * @param $url
     * @param bool $full
     * @return string
     */
    public function parseUrl($url, $full=true)
    {
        $parsed_url = parse_url($url);

        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : 'https://';
        $host     = $parsed_url['host'] ?? '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = $parsed_url['user'] ?? '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = $parsed_url['path'] ?? '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        if( $full )
            return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
        else
            return str_replace('www.', '', empty($host)?$path:$host);
    }

    /**
     * @param $objects
     * @param $attrs
     * @return mixed
     * @internal param $text
     */
    public function bind($objects, $attrs)
    {
        $binded_objects = [];
        $objects = (array)$objects;

        foreach ($objects as $object)
        {
            if( is_array($attrs) )
            {
                $binded_object = [];
                foreach ($attrs as $dest=>$source)
                {
                    if( is_object($object)){

                        $method = 'get'.ucfirst($attrs);
                        $binded_objects[$dest] = method_exists($object,$method)?$object->$method(): false;
                    }
                    else{

                        $binded_object[$dest] = isset($object[$source]) ? $object[$source] : false;
                    }
                }

                $binded_objects[] = array_filter($binded_object);
            }
            else
            {
                if( is_object($object)){

                    $method = 'get'.ucfirst($attrs);
                    $binded_objects[] = method_exists($object,$method)?$object->$method(): false;
                }
                else
                    $binded_objects[] = isset($object[$attrs]) ? $object[$attrs] : false;
            }
        }

        return array_filter($binded_objects);
    }

    /**
     * @param $string
     * @return false|string
     */
    public function encrypt($string){

        return openssl_encrypt($string, "AES-128-CTR", getenv('APP_SECRET'), 0, '1234567891011121');
    }

    /** This is where you can add your own functions to twig.
     *
     * @param Twig_Environment $twig get extension.
     */
    public function add_to_twig( $twig ) {

        $twig->addExtension( new Twig\Extension\StringLoaderExtension() );

        $twig->addFunction( new Twig\TwigFunction( 'encore_entry_link_tags', [$this, 'renderWebpackLinkTags'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'encore_entry_script_tags', [$this, 'renderWebpackScriptTags'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'archive_url', 'get_post_type_archive_link' ) );
        $twig->addFunction( new Twig\TwigFunction( 'post_query', function ($query){ return Timber::get_posts($query); }) );
        $twig->addFunction( new Twig\TwigFunction( 'term_query', function ($query){ return Timber::get_terms($query); }) );
        $twig->addFunction( new Twig\TwigFunction( 'get_posts', 'get_posts') );
        $twig->addFunction( new Twig\TwigFunction( 'get_object_terms', 'wp_get_object_terms') );

        $twig->addFilter( new Twig\TwigFilter( 'picture', [$this, 'generatePicture'] ) );
        $twig->addFilter( new Twig\TwigFilter( 't', [$this,'translate'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'ucfirst', 'ucfirst' ) );
        $twig->addFilter( new Twig\TwigFilter( 'encrypt', [$this,'encrypt'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'protect_email', [$this,'protectEmail'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'youtube_id', [$this,'youtubeID'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'encode', [$this,'encode'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'bind', [$this,'bind'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'nl2p', [$this,'lineBreakToP'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'space2span', [$this,'spaceToSpan'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'parse_url', [$this,'parseUrl'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'phone', [$this,'formatPhone'] ) );

        return $twig;
    }
}

new Site();
