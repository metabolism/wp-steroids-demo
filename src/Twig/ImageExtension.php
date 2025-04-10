<?php

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Timber\Image;
use Timber\ImageHelper;
use kornrunner\Blurhash\Blurhash;

final class ImageExtension extends AbstractExtension
{
    /**
     * @param $image
     * @param $width
     * @param int $height
     * @param bool $ext
     * @return string
     */
    public function cropImage($image, $width, $height=0, $ext=false) {

        $post_id = false;
        $debug = ($_GET['debug']??false) == 'image' && defined('WP_DEBUG') && WP_DEBUG;
        $crop = 'center';

        if( $image instanceof Image )
            $post_id = $image->id;
        elseif( is_array($image) )
            $post_id = $image['ID']??false;
        elseif( is_int($image) )
            $post_id = $image;
        elseif( is_string($image) )
            $post_id = attachment_url_to_postid($image);

        if( $post_id ){

            $src = get_attached_file($post_id);

            if( !file_exists($src) )
                return '';

            if( $_crop = get_post_meta($post_id, 'crop', true) )
                $crop = $_crop;

            if( !is_array($image) or !isset($image['path'], $image['alt'], $image['mime_type']) ){

                $attachment = get_post( $post_id );

                $image = ['url'=>wp_get_attachment_url( $attachment->ID )];
            }
        }

        if( !$image )
            return '';

        $src = $ext == 'webp' && function_exists('imagewebp') ? ImageHelper::img_to_webp($image['path']) : $image['path'];

        return $debug ? $this->generatePlaceholder($width, $height) : $this->resizeImage($src, $width, $height, $crop);
    }

    /**
     * @param $src
     * @param $width
     * @param int $height
     * @param string $crop
     * @return string
     */
    public function resizeImage($src, $width, $height=0, $crop='center') {

        $debug = ($_GET['debug']??false) == 'image' && defined('WP_DEBUG') && WP_DEBUG;
        $upload_dir = wp_upload_dir();

        if( $debug ){

            return $this->generatePlaceholder($width, $height);
        }
        else{

            $path = ImageHelper::resize($src, $width, $height, $crop);
            $url = str_replace($upload_dir['relative'], $upload_dir['baseurl'], $path);

            return apply_filters('wp_get_attachment_url', $url);
        }
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
    public function generateFigure($src, $width, $height=0, $sources=[], $alt=false, $loading='lazy') {

        $post_id = false;
        $html = '';

        if( $src instanceof Image )
            $post_id = $src->id;
        elseif( is_array($src) )
            $post_id = $src['ID']??false;
        elseif( is_int($src) )
            $post_id = $src;

        if( $post_id ){

            $post = get_post($post_id);

            $image = $this->generatePicture($src, $width, $height, $sources, $alt, $loading);
            $html = '<figure class="figure'.(strlen($post->post_excerpt)?' has-caption':'').'">';
            $html .= $image;

            if( strlen($post->post_excerpt) )
                $html .= '<figcaption>'.$post->post_excerpt.'</figcaption>';

            $html .= '</figure>';
        }

        return $html;
    }

    /**
     * @param $width
     * @param int $height
     * @return string
     */
    public function generatePlaceholder($width, $height=0) {

        $params = '';

        if( !$width ){

            $params = '?text=0x'.$height;
            $width = $height;
        }

        if( !$height ){

            $params = '?text='.$width.'x0';
            $height = $width;
        }

        $height = !$height?$width:$height;
        $width = !$width?$height:$width;

        return 'https://placehold.co/'.$width.'x'.$height.$params;
    }



    /**
     * @param $image
     * @param $width
     * @param int $height
     * @param int $scale
     * @return string
     */
    public function getBlurhashImage64($image, $width, $height=0, $scale=40) {

        if( is_bool($scale) )
            $scale = 40;

        if( !$blurhash = $this->generateBlurhash($image['ID']??false) )
            return '';

        if( (!$width || !$height) && !isset($image['metadata']) )
            return '';

        $ratio = $image['metadata']['width']/$image['metadata']['height'];

        if( !$width )
            $width = round($height*$ratio);

        if( !$height || !is_numeric($height) )
            $height = round($width/$ratio);

        $width = round($width/$scale);
        $height = round($height/$scale);

        if( !$pixels = Blurhash::decode($blurhash, $width, $height) )
            return '';

        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                [$r, $g, $b] = $pixels[$y][$x];
                imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
            }
        }

        if( !$tmpfile = @tempnam("/tmpdir", "img") )
            return '';

        imagepng($image, $tmpfile);
        $string = file_get_contents($tmpfile);
        unlink($tmpfile);

        return base64_encode($string);
    }

    /**
     * @param $attachment_id
     * @return mixed|string
     */
    public function generateBlurhash($attachment_id) {

        if( !is_numeric($attachment_id) )
            return false;

        $attachment_metadata = maybe_unserialize(get_post_meta( $attachment_id, '_wp_attachment_metadata', true ));

        if( isset($attachment_metadata['blurhash']) )
            return $attachment_metadata['blurhash'];

        $src = get_attached_file($attachment_id);
        $upload_dir = wp_upload_dir();

        $path = str_replace($upload_dir['basedir'], $upload_dir['relative'], $src);

        if( !isset($attachment_metadata['width']) || empty($path) )
            return false;

        $src_ratio = $attachment_metadata['width']/$attachment_metadata['height'];
        $width = round($attachment_metadata['width'] >= $attachment_metadata['height'] ? 64 : 64*$src_ratio);
        $height = round($attachment_metadata['width'] >= $attachment_metadata['height'] ? 64/$src_ratio : 64);

        $path64 = ImageHelper::resize($path, $width, $height, false);

        if( empty($path64) || $path64 == $path )
            return false;

        $src64 = str_replace($upload_dir['relative'], $upload_dir['basedir'], $path64);

        $image = imagecreatefromstring(file_get_contents($src64));

        unlink($src64);

        $width = imagesx($image);
        $height = imagesy($image);

        $pixels = [];

        for ($y = 0; $y < $height; ++$y) {
            $row = [];
            for ($x = 0; $x < $width; ++$x) {
                $index = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $index);

                $row[] = [$colors['red'], $colors['green'], $colors['blue']];
            }
            $pixels[] = $row;
        }

        $components_x = 4;
        $components_y = 3;

        $attachment_metadata['blurhash'] = Blurhash::encode($pixels, $components_x, $components_y);
        update_post_meta($attachment_id, '_wp_attachment_metadata', $attachment_metadata);

        return $attachment_metadata['blurhash'];
    }


    /**
     * @param $image
     * @param $width
     * @param $height
     * @param $sources
     * @param $alt
     * @param $loading
     * @return string
     */
    public function generatePicture($image, $width, $height=0, $sources=[], $alt=false, $loading='lazy') {

        $post_id = false;
        $debug = ($_GET['debug']??false) == 'image' && defined('WP_DEBUG') && WP_DEBUG;
        $crop = 'center';
        $blurhash = true;
        $max_retina = 960;
        $class = '';

        if( $image instanceof Image )
            $post_id = $image->id;
        elseif( is_array($image) )
            $post_id = $image['ID']??false;
        elseif( is_int($image) )
            $post_id = $image;
        elseif( is_string($image) )
            $post_id = attachment_url_to_postid($image);

        if( isset($sources['lazy']) ){

            if( !$sources['lazy'] )
                $loading = 'eager';

            unset( $sources['lazy'] );
        }

        if( isset($sources['loading']) ){

            $loading = $sources['loading'];
            unset( $sources['loading'] );
        }

        if( isset($sources['retina']) ){

            $max_retina = 1920;
            unset( $sources['max_retina'] );
        }

        if( isset($sources['alt']) ){

            $alt = $sources['alt'];
            unset( $sources['alt'] );
        }

        if( isset($sources['class']) ){

            $class = $sources['class'];
            unset( $sources['class'] );
        }

        if( isset($sources['blurhash']) ){

            $blurhash = $sources['blurhash'];
            unset( $sources['blurhash'] );
        }

        if( !$post_id )
            return '';

        $src = get_attached_file($post_id);

        if( !file_exists($src) )
            return WP_DEBUG?'<error>File not found</error>':'';

        if( !is_numeric($width) )
            return WP_DEBUG?'<error>Width is not valid</error>':'';

        if( !is_numeric($height) )
            return WP_DEBUG?'<error>Height is not valid</error>':'';

        if( $_crop = get_post_meta($post_id, 'crop', true) )
            $crop = $_crop;

        if( !is_array($image) or !isset($image['url'], $image['alt'], $image['mime_type']) ){

            if( !$attachment = get_post( $post_id ) )
                return '';

            $image = [
                'ID' => $attachment->ID,
                'url' => wp_get_attachment_url( $attachment->ID ),
                'alt' => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
                'mime_type' => $attachment->post_mime_type
            ];

        }

        $upload_dir = wp_upload_dir();

        $image['path'] = str_replace($upload_dir['basedir'], $upload_dir['relative'], $src);
        $image['metadata'] = maybe_unserialize(get_post_meta( $post_id, '_wp_attachment_metadata', true ));
        $image['alt'] = htmlspecialchars($alt?:$image['alt'], ENT_QUOTES, 'UTF-8');

        $ext = function_exists('imagewebp') ? 'webp' : null;
        $mime = function_exists('imagewebp') ? 'image/webp' : $image['mime_type'];
        $lazy_img = $blurhash && $loading != 'eager' && ($image['mime_type'] == 'image/jpg' || $image['mime_type'] == 'image/jpeg');

        if( $lazy_img )
            $html = '<lazy-img class="responsive-picture"><picture class="has-blurhash" style="background-image: url(data:image/png;base64,'.$this->getBlurhashImage64($image, $width, $height, $blurhash).')">';
        else
            $html = '<picture class="responsive-picture">';

        if( $image['mime_type'] == 'image/svg+xml' || $image['mime_type'] == 'image/svg' || $image['mime_type'] == 'image/gif' ){

            $img_src = $debug ? $this->generatePlaceholder($width, $height) : $image['url'];
            $html .= '<img loading="' . $loading . '" class="' . $class . '" src="' . $img_src . '" alt="' . $image['alt'] . '" '.($width?'width="'.$width.'"':'').' '.($height?'height="'.$height.'"':'').'/>';
        }
        else {

            $webp_src = $ext ? ImageHelper::img_to_webp($image['path']) : false;

            if( !$sources || !is_array($sources) )
                $sources = [];

            $sources = array_combine(array_map(function ($key){ return str_replace(' ','', $key); }, array_keys($sources)), $sources);

            if( !isset($sources['max-width:1440px']) )
                $sources = array_merge($sources, ['max-width:1440px'=>[round($width/1.3333), round($height/1.33333)]]);
            
            foreach ($sources as $media => $size) {

                if (is_int($media))
                    $media = 'max-width:' . $media . 'px';

                $target_width = $size[0] ?? 0;
                $target_height = $size[1] ?? 0;

                if ( $webp_src ) {

                    $url = $this->resizeImage($webp_src, $size[0] ?? 0, $size[1] ?? 0, $crop);

                    if( ($target_width > 0 && $target_width < $max_retina && $target_height < $max_retina) || ($target_height > 0 && $target_height < $max_retina && $target_width < $max_retina) ) {

                        $url_2x = $this->resizeImage($webp_src, $target_width * 2, $target_height * 2, $crop);
                        $html .= '<source media="(' . $media . ')" srcset="' . $url . ' 1x, ' . $url_2x . ' 2x" type="' . $mime . '"/>';
                    }
                    else{

                        $html .= '<source media="(' . $media . ')" srcset="' . $url . '" type="' . $mime . '"/>';
                    }
                }
                else{

                    $url = $this->resizeImage($image['path'], $size[0] ?? 0, $size[1] ?? 0, $crop);

                    if( ($target_width > 0 && $target_width < $max_retina && $target_height < $max_retina) || ($target_height > 0 && $target_height < $max_retina && $target_width < $max_retina) ){

                        $url_2x = $this->resizeImage($image['path'], $target_width*2, $target_height*2, $crop);
                        $html .= '<source media="(' . $media . ')" srcset="' . $url . ' 1x, '.$url_2x.' 2x" type="' . $image['mime_type'] . '"/>';
                    }
                    else{

                        $html .= '<source media="(' . $media . ')" srcset="' . $url . '" type="' . $image['mime_type'] . '"/>';
                    }
                }
            }

            if ( $webp_src ) {

                $url = $this->resizeImage($webp_src, $width, $height, $crop);

                if( ( $width> 0 && $width < $max_retina && $height < $max_retina ) || ( $height > 0 && $height < $max_retina && $width < $max_retina ) ){

                    $url_2x = $this->resizeImage($webp_src, $width*2, $height*2, $crop);
                    $html .= '<source srcset="' . $url . ' 1x, '.$url_2x.' 2x" type="image/webp"/>';
                }
                else{

                    $html .= '<source srcset="' . $url . '" type="image/webp"/>';
                }
            }

            $url = $this->resizeImage($image['path'], $width, $height, $crop);

            $ratio = ($image['metadata']['width']??1)/($image['metadata']['height']??1);

            $height = $height ?: round($width/$ratio);
            $width = $width ?: round($height*$ratio);

            $html .= '<img loading="' . $loading . '"  class="' . $class . ' object-'.$crop . '" src="' . $url . '" alt="' . $image['alt'] . '" width="'.$width.'" height="'.$height.'"/>';
        }

        if( $lazy_img )
            $html .='</picture></lazy-img>';
        else
            $html .='</picture>';

        return $html;
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter( 'crop', [$this, 'cropImage'] ),
            new TwigFilter( 'picture', [$this, 'generatePicture'] ),
            new TwigFilter( 'figure', [$this, 'generateFigure'] )
        ];
    }
}