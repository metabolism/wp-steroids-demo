<?php

use Timber\Factory\PostFactory;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Runtime\EscaperRuntime;

final class AppExtension extends AbstractExtension
{
    /**
     * @param $object
     * @param $property
     * @param $value
     * @return mixed
     */
    public function assign($object, $property, $value) {

        if( is_object($object) )
            $object->$property = $value;

        return $object;
    }

    /**
     * @param $picture
     * @return string
     */
    public function placeholder($picture){

        if( empty($picture) )
            $picture = '<span class="image-placeholder"></span>';

        return $picture;
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

    /**
     * @param $text
     * @return bool
     */
    public function a11y($text){

        if( !is_string($text) )
            return $text;

        return preg_replace_callback('/[()\[\]|]/', function($matches) {
            return '<span aria-hidden="true">' . htmlspecialchars($matches[0]) . '</span>';
        }, $text);
    }

    /**
     * @param $post
     * @param bool $field
     * @return mixed
     */
    public function getFirstBlock($post, $field=false){

        $blocks = $this->getBlocks($post);

        if( !$field )
            return $blocks[0]??null;

        return $blocks[0][$field]??null;
    }

    /**
     * @param WP_Post $post
     * @return array
     */
    public function getBlocks($post){

        if( !$post || !$post->post_content || !has_blocks($post) )
            return [];

        $parsed_blocks = parse_blocks($post->post_content);

        $blocks = [];

        foreach ($parsed_blocks as $block){

            $block = new WP_Block( $block, ['postId'=>$post->ID, 'postType'=>$post->post_type]  );
            $attributes = $block->attributes;

            $attributes['id'] = acf_get_block_id( $attributes, $block->context );

            if( $block = acf_prepare_block( $attributes ) ){

                $block = acf_add_block_meta_values( $block, $post->ID );
                acf_setup_meta( $block['data'], $block['id'] );

                $blocks[] = get_fields($block['id']);
            }
        }

        return $blocks;
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
     * @param $text
     * @return mixed
     */
    public function encode($text)
    {
        return substr($text, 0,1).base64_encode(str_replace('@','$', $text));
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
     * Returns the video ID of a Youtube video.
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
     * Returns the instagram ID of an Instagram post
     *
     * @param $url
     * @return string
     */
    public function instagramId($url)
    {
        preg_match( '/(?:https?:\/\/www\.)?instagram\.com\S*?\/(?:p|reel)\/([a-zA-Z0-9-_]{11})\/?/', $url, $matches );

        return count( $matches ) > 1 ? $matches[1] : '';
    }


    /**
     * Returns the video ID of a vimeo video.
     *
     * @param $url
     * @return string
     */
    public function vimeoID($url)
    {
        preg_match( "/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:player\.)?vimeo\.com\/([0-9]{6,11})[?]?.*/", $url, $matches );
        return count( $matches ) > 1 ? $matches[1] : '';
    }

    /**
     * @return string
     */
    public function formatPhone($text)
    {
        return chunk_split($text, 2, ' ');
    }

    /**
     * @return string
     * @throws \Twig\Error\RuntimeError
     */
    public function clean($text)
    {
        if( !is_string($text) )
            return "";

        $text = trim(strip_tags(str_replace("\n"," ", str_replace("\r"," ", str_replace("\n\n"," ", $text)))));
        $escaper = new EscaperRuntime();

        return $escaper->escape($text);
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
     * @param $text
     * @return \Twig\Markup
     */
    public function lineBreakToSpan($text)
    {
        $text = explode("\n", $text);
        $html = '<span>'.implode('</span><span>', array_filter($text)).'</span>';
        $html = str_replace("<span>\r</span>", '', $html);

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
     * @return string
     */
    public function highlight($input, $search, $tag='b') {

        $textNormalized = mb_strtolower(remove_accents($input));
        $searchWordNormalized = mb_strtolower(remove_accents($search));

        $position = mb_stripos($textNormalized, $searchWordNormalized);

        if ($position !== false) {

            $originalWord = mb_substr($input, $position, mb_strlen($search));
            return str_replace($originalWord, "<$tag>$originalWord</$tag>", $input);
        }

        return $input;
    }

    /**
     * @return string
     */
    public function getExtension($input) {

        if( is_string($input) )
            return pathinfo($input, PATHINFO_EXTENSION);
        return
            false;
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
     * @param $state
     * @return object|bool
     */
    public function getPageByState($state){

        $postFactory = new PostFactory();

        if( $post = get_page_by_state($state) )
            return $postFactory->from($post);

        return false;
    }

    /**
     * @return false|mixed
     */
    public function getArchivePostType(){

        global $wp_query;

        return $wp_query->query['post_type']??false;
    }


    /**
     * @param WPS_Post $post
     * @param $taxonomy
     * @return int|string|null
     */
    function getPostPositionInTaxonomy($post, $taxonomy ) {

        if( !$post instanceof \Timber\Post )
            return null;

        $terms = wp_get_post_terms( $post->id, $taxonomy );

        if ( !empty( $terms ) && !is_wp_error( $terms ) ) {

            $term_id = $terms[0]->term_id;

            $args = array(
                'post_type' => $post->post_type,
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => $taxonomy,
                        'field'    => 'term_id',
                        'terms'    => $term_id,
                    ),
                ),
                'fields'  => 'ids'
            );

            $post_ids = get_posts( $args );

            $position = array_search( $post->id, $post_ids );

            if ( $position !== false )
                return $position + 1;
        }

        return null;
    }



    /**
     * @param $hex
     * @return bool
     */
    function isColorDark($hex)
    {
        $average = 381; // range 1 - 765

        if(strlen(trim($hex)) == 4)
            $hex = "#" . substr($hex,1,1) . substr($hex,1,1) . substr($hex,2,1) . substr($hex,2,1) . substr($hex,3,1) . substr($hex,3,1);

        return hexdec(substr($hex,1,2))+hexdec(substr($hex,3,2))+hexdec(substr($hex,5,2)) < $average;
    }

    public function enqueueContactFormScripts(){

        if ( function_exists( 'wpcf7_enqueue_scripts' ) )
            wpcf7_enqueue_scripts();

        if ( function_exists( 'wpcf7_enqueue_styles' ) )
            wpcf7_enqueue_styles();
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('assign', [$this, 'assign']),
            new TwigFilter('placeholder', [$this, 'placeholder']),
            new TwigFilter('has_block', [$this, 'hasBlock']),
            new TwigFilter('a11y', [$this, 'a11y']),
            new TwigFilter('first_block', [$this, 'getFirstBlock']),
            new TwigFilter('intval', 'intval'),
            new TwigFilter('handle', 'sanitize_title'),
            new TwigFilter('blocks', [$this, 'getBlocks']),
            new TwigFilter('lottie_placeholder', [$this, 'generateLottiePlaceholder']),
            new TwigFilter('table', [$this, 'generateTable']),
            new TwigFilter( 'ucfirst', 'ucfirst' ),
            new TwigFilter( 'encrypt', [$this,'encrypt'] ),
            new TwigFilter( 'encode', [$this,'encode'] ),
            new TwigFilter( 'bind', [$this,'bind'] ),
            new TwigFilter( 'nl2p', [$this,'lineBreakToP'] ),
            new TwigFilter( 'nl2span', [$this,'lineBreakToSpan'] ),
            new TwigFilter( 'space2span', [$this,'spaceToSpan'] ),
            new TwigFilter( 'parse_url', [$this,'parseUrl'] ),
            new TwigFilter( 'phone', [$this,'formatPhone'] ),
            new TwigFilter( 'youtube_id', [$this, 'youtubeId'] ),
            new TwigFilter( 'instagram_id', [$this, 'instagramId'] ),
            new TwigFilter( 'vimeo_id', [$this, 'vimeoID'] ),
            new TwigFilter( 'clean', [$this, 'clean'] ),
            new TwigFilter( 'highlight', [$this, 'highlight'] ),
            new TwigFilter( 'ext', [$this, 'getExtension'] )
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('wp_head', 'wp_head'),
            new TwigFunction('wp_footer', 'wp_footer'),
            new TwigFunction('nonce', 'wp_create_nonce'),
            new TwigFunction('assign', [$this, 'assign']),
            new TwigFunction('pixel', [$this, 'pixel']),
            new TwigFunction( 'archive_url', 'get_post_type_archive_link' ),
            new TwigFunction( 'search_url', 'get_search_link' ),
            new TwigFunction( 'post_query', function ($query){ return Timber::get_posts($query); }),
            new TwigFunction( 'term_query', function ($query){ return Timber::get_terms($query); }),
            new TwigFunction( 'get_object_terms', 'wp_get_object_terms'),
            new TwigFunction( 'enqueue_contact_form_scripts',  [$this, 'enqueueContactFormScripts']),
            new TwigFunction( 'post_url',  [$this, 'getPermalink']),
            new TwigFunction( 'permalink', 'get_permalink' ),
            new TwigFunction( 'is_front_page',  'is_front_page' ),
            new TwigFunction( 'is_404',  'is_404' ),
            new TwigFunction( 'is_privacy_policy',  'is_privacy_policy' ),
            new TwigFunction( 'archive_post_type',  [$this, 'getArchivePostType'] ),
            new TwigFunction( 'is_archive',  'is_archive' ),
            new TwigFunction( 'is_sticky',  'is_sticky' ),
            new TwigFunction( 'archive_title',  'get_the_archive_title' ),
            new TwigFunction( 'is_singular',  'is_singular' ),
            new TwigFunction( 'get_page_by_state',  [$this, 'getPageByState'] ),
            new TwigFunction( 'get_position_in_tax',  [$this, 'getPostPositionInTaxonomy'] ),
            new TwigFunction( 'is_dark',  [$this, 'isColorDark'] ),
        ];
    }
}