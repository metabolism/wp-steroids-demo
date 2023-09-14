<?php

/**
 * Shared code across projects, defines default behaviour for WordPress
 * Add most used twig functions and filters
 */

use Timber\ImageHelper;
use Timber\Timber;

abstract class Kernel extends \Timber\Site {

    private $entrypoints;
    private $manifest;
    private $translations;

    private $options;

    /** Add timber support. */
    public function __construct() {

        $this->options = get_fields('option');

        add_filter('network_site_url', [$this, 'networkSiteURL'] );
        add_filter('option_siteurl', [$this, 'optionSiteURL'] );

        add_filter( 'timber_post_get_meta_pre', function ($post_meta, $pid){

            $post = get_post($pid);

            if( $post->post_type == 'revision' && $post->post_parent )
                return get_post_meta($post->post_parent);

            return $post_meta;

        },10, 2);

        add_action( 'init', [$this, 'redirect']);
        add_filter( 'timber/context', [$this, 'addToContext'] );
        add_filter( 'timber/twig', [$this, 'addToTwig'] );
        add_filter( 'block_render_callback', [$this,'renderBlock']);

        if( file_exists(__DIR__.'/../public/build/entrypoints.json'))
            $this->entrypoints = json_decode(file_get_contents(__DIR__.'/../public/build/entrypoints.json'), true);

        if( file_exists(__DIR__.'/../public/build/manifest.json'))
            $this->manifest = json_decode(file_get_contents(__DIR__.'/../public/build/manifest.json'), true);

        if( is_admin() )
            add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        else
            $this->get_translations();

        parent::__construct();
    }

    /**
     * @param $url
     * @return string
     */
    public function optionSiteURL($url)
    {
        return strpos($url, '/edition') === false ? $url.'/edition' : $url;
    }

    /**
     * @param $url
     * @return array|string|string[]
     */
    public function networkSiteURL($url)
    {
        if( strpos($url, '/edition') === false )
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

        $path = rtrim($_SERVER['REQUEST_URI'], '/');

        if( ($path == '/edition' || $path == '/edition/') && 'POST' !== $_SERVER['REQUEST_METHOD'] ){

            wp_redirect(is_user_logged_in() ? admin_url('index.php') : wp_login_url());
            exit;
        }
    }

    /**
     * @return void
     */
    function enqueueBlockEditorAssets() {

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

    /**
     * @param $block
     * @param $content
     * @param $is_preview
     * @return void
     */
    public static function renderBlock($block, $content = '', $is_preview = false){

        if( !($block['front']??true) && !is_admin() )
            return;

        if( $image = $block['data']['_preview_image']??false ){

            echo '<img src="'.get_home_url().$image.'" style="width:100%;height:auto" class="preview_image"/>';
            return;
        }

        $context = Timber::context();

        if( $id = get_the_ID() )
            $context['post'] = Timber::get_post($id);

        $context['props'] = get_fields();

        // Store field values.
        $context['block'] = $block;

        // Store $is_preview value.
        $context['is_preview'] = $is_preview;
        $context['is_front_page'] = is_front_page();

        // Render the block.
        $name = str_replace('_', '-', str_replace('acf/', '', $block['name']));

        Timber::render( 'block/'.$name.'/'.$name.'.twig', $context );
    }

    private function get_translations()
    {
        if( $translations = $this->options['translations']??false )
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
    public function addToContext( $context ) {

        $context['environment'] = WP_ENV;
        $context['blog'] = $this;
        $context['options'] = $this->options;

        return $context;
    }

    /**
     * @param $entryName
     * @return string
     */
    public function renderWebpackLinkTags($entryName ) {

        $entries = $this->entrypoints['entrypoints'][$entryName]['css']??[];

        $styles = '';

        foreach ($entries as $entry)
            $styles .= "<link rel='stylesheet' href='{$entry}' type='text/css' media='all' />";

        return $styles;
    }

    /**
     * @param $entryName
     * @return string
     */
    public function renderWebpackScriptTags($entryName ) {

        $entries = $this->entrypoints['entrypoints'][$entryName]['js']??[];

        $styles = '';

        foreach ($entries as $entry)
            $styles .= "<script type='text/javascript' src='{$entry}' defer></script>";

        return $styles;
    }

    /**
     * @param $entryName
     * @return false|mixed
     */
    public function asset($entryName ) {

        return $this->manifest['build/'.$entryName]??false;
    }

    /**
     * @param $src
     * @param $width
     * @param $height
     * @param $sources
     * @param $alt
     * @param $loading
     * @return string
     */
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

                    $target_width = $size[0] ?? 0;
                    $target_height = $size[1] ?? 0;

                    if ($ext == 'webp') {

                        $webp_src = ImageHelper::img_to_webp($src['url']);
                        $url = ImageHelper::resize($webp_src, $size[0] ?? 0, $size[1] ?? 0);

                        if( ($target_width > 0 && $target_width < 960) || ($target_height > 0 && $target_height < 960) ) {

                            $url_2x = ImageHelper::resize($webp_src, $target_width * 2, $target_height * 2);
                            $html .= '<source media="(' . $media . ')" srcset="' . $url . ' 1x, ' . $url_2x . ' 2x" type="' . $mime . '"/>';
                        }
                        else{

                            $html .= '<source media="(' . $media . ')" srcset="' . $url . '" type="' . $mime . '"/>';
                        }
                    }

                    $url = ImageHelper::resize($src['url'], $size[0] ?? 0, $size[1] ?? 0);

                    if( ($target_width > 0 && $target_width < 960) || ($target_height > 0 && $target_height < 960) ){

                        $url_2x = ImageHelper::resize($src['url'], $target_width*2, $target_height*2);
                        $html .= '<source media="(' . $media . ')" srcset="' . $url . ' 1x, '.$url_2x.' 2x" type="' . $src['mime_type'] . '"/>';
                    }
                    else{

                        $html .= '<source media="(' . $media . ')" srcset="' . $url . '" type="' . $src['mime_type'] . '"/>';
                    }
                }
            }

            if ($ext == 'webp') {

                $webp_src = ImageHelper::img_to_webp($src['url']);
                $url = ImageHelper::resize($webp_src, $width, $height);

                if( ( $width> 0 && $width < 960 ) || ( $height > 0 && $height < 960 ) ){

                    $url_2x = ImageHelper::resize($webp_src, $width*2, $height*2);
                    $html .= '<source srcset="' . $url . ' 1x, '.$url_2x.' 2x" type="image/webp"/>';
                }
                else{

                    $html .= '<source srcset="' . $url . '" type="image/webp"/>';
                }
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

            if( $_GET['debug']??'' == 'translation' && is_user_logged_in() )
                return '{{'.htmlspecialchars($text).'}}';

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
     * @param $object
     * @return string
     */
    public function generateTable($object){

        $html = '<table>';

        if( !empty($object['caption']) )
            $html .= '<caption>'.$object['caption'].'</caption>';

        if( !empty($object['header']) ){

            $html .= '<thead><tr>';

            foreach ($object['header'] as $col){
                $html .= '<th>'.$col['c'].'</th>';
            }

            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';

        foreach ($object['body'] as $row){

            $html .= '<tr>';

            foreach ($row as $col)
                $html .= '<td>'.$col['c'].'</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param $string
     * @return false|string
     */
    public function encrypt($string){

        return openssl_encrypt($string, "AES-128-CTR", getenv('APP_SECRET'), 0, '1234567891011121');
    }

    /**
     * @param $string
     * @return false|string
     */
    public function nonce($string){

        return wp_create_nonce($string);
    }

    /**
     * @param $page
     * @param $by
     * @return false|string
     */
    public function getPermalink($page, $by=false )
    {
        switch ( $by ){

            case 'state':

                if( !function_exists('get_page_by_state') )
                    return false;

                $page = get_page_by_state($page);
                break;

            case 'path':

                $page = get_page_by_path($page);
                break;

            case 'title':

                $page = get_page_by_title($page);
                break;

            case 'slug':

                if( !is_array($page) or count($page) != 2 )
                    return false;

                $post_ids = get_posts([
                    'name'   => $page[0],
                    'post_type'   => $page[1],
                    'numberposts' => 1,
                    'fields' => 'ids'
                ]);

                if( count($post_ids) )
                    $page = $post_ids[0];
        }

        if( $page ){

            $link = get_permalink($page);

            if( !is_string($link) )
                return false;

            return $link;
        }
        else
            return false;
    }

    /**
     * @param $post
     * @param $name
     * @return bool
     */
    public function hasBlock($post, $name=false){

        if( !$post || !$post->post_content || !has_blocks($post) )
            return false;

        if( !$name )
            return true;

        $blocks = parse_blocks($post->post_content);

        foreach ($blocks as $block){

            if( $block['blockName'] == $name || $block['blockName'] == 'acf/'.$name)
                return true;
        }

        return false;
    }

    public function enqueue_contact_form_scripts(){

        if ( function_exists( 'wpcf7_enqueue_scripts' ) )
            wpcf7_enqueue_scripts();

        if ( function_exists( 'wpcf7_enqueue_styles' ) )
            wpcf7_enqueue_styles();
    }

    /**
     * Generate transparent pixel base64 image
     * @param $w
     * @param $h
     * @return string
     */
    public function generatePixel($w = 1, $h = 1) {

        ob_start();

        if( $h == 0 )
            $h = $w;
        elseif( $w == 0 )
            $w = $h;

        $img = imagecreatetruecolor($w, $h);
        imagetruecolortopalette($img, false, 1);
        imagesavealpha($img, true);
        $color = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $color);
        imagepng($img, null, 9);
        imagedestroy($img);

        $imagedata = ob_get_contents();
        ob_end_clean();

        return 'data:image/png;base64,' . base64_encode($imagedata);
    }

    /**
     * @param $file
     * @param int $max_w
     * @param int $max_h
     * @return string
     */
    public function generateLottiePlaceholder($file, $max_w=0, $max_h=0){

        $json = json_decode(file_get_contents($file), true);
        $w = $json['w']??800;
        $h = $json['h']??600;

        return '<img src="'.$this->generatePixel($w, $h).'" style="'.($max_h?'max-height:'.$max_h.'px':'').($max_w?';max-width:'.$max_w.'px':'').'"/>';
    }

    /** This is where you can add your own functions to twig.
     *
     * @param Twig_Environment $twig get extension.
     */
    public function addToTwig( $twig ) {

        $twig->addExtension( new Twig\Extension\StringLoaderExtension() );

        $twig->addFunction( new Twig\TwigFunction( 'encore_entry_link_tags', [$this, 'renderWebpackLinkTags'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'encore_entry_script_tags', [$this, 'renderWebpackScriptTags'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'enqueue_contact_form_scripts', [$this, 'enqueue_contact_form_scripts'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'asset', [$this, 'asset'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'nonce', [$this, 'nonce'] ) );
        $twig->addFunction( new Twig\TwigFunction( 'archive_url', 'get_post_type_archive_link' ) );
        $twig->addFunction( new Twig\TwigFunction( 'post_query', function ($query){ return Timber::get_posts($query); }) );
        $twig->addFunction( new Twig\TwigFunction( 'term_query', function ($query){ return Timber::get_terms($query); }) );
        $twig->addFunction( new Twig\TwigFunction( 'get_posts', 'get_posts') );
        $twig->addFunction( new Twig\TwigFunction( 'get_object_terms', 'wp_get_object_terms') );
        $twig->addFunction( new Twig\TwigFunction( 'post_url',  [$this, 'getPermalink']) );
        $twig->addFunction( new Twig\TwigFunction( 'permalink', 'get_permalink' ) );
        $twig->addFunction( new Twig\TwigFunction( 'shortcode', 'do_shortcode' ) );
        $twig->addFunction( new Twig\TwigFunction( 'calculated_carbon', 'get_calculated_carbon' ) );
        $twig->addFunction( new Twig\TwigFunction( 'pixel', [$this, 'generatePixel'] ) );

        $twig->addFilter( new Twig\TwigFilter( 'has_block', [$this, 'hasBlock'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'lottie_placeholder', [$this, 'generateLottiePlaceholder'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'handle', 'sanitize_title' ) );
        $twig->addFilter( new Twig\TwigFilter( 'table', [$this, 'generateTable'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'picture', [$this, 'generatePicture'] ) );
        $twig->addFilter( new Twig\TwigFilter( 't', [$this,'translate'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'ucfirst', 'ucfirst' ) );
        $twig->addFilter( new Twig\TwigFilter( 'encrypt', [$this,'encrypt'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'protect_email', [$this,'protectEmail'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'encode', [$this,'encode'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'bind', [$this,'bind'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'nl2p', [$this,'lineBreakToP'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'space2span', [$this,'spaceToSpan'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'parse_url', [$this,'parseUrl'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'phone', [$this,'formatPhone'] ) );
        $twig->addFilter( new Twig\TwigFilter( 'youtube_id', [$this, 'youtubeId'] ) );

        return $twig;
    }
}
