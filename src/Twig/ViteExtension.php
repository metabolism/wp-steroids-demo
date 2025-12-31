<?php

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ViteExtension extends AbstractExtension
{
    private $manifest;

    private static $manifest_file = __DIR__.'/../../public/build/.vite/manifest.json';

    private static $vite_file = __DIR__.'/../../vite.config.js';

    public function getFromManifest($entry)
    {
        if( !$this->manifest && is_file(self::$manifest_file))
            $this->manifest = json_decode(file_get_contents(self::$manifest_file), true);

        return $this->manifest[$entry]['file']??false;
    }

    /**
     * @param $entryName
     * @return string
     */
    public function renderLinkTags($entryName) {

        $entry = $this->getFromManifest("assets/styles/".$entryName.".scss");

        if( empty($entry) ){

            if( WP_DEBUG )
                return "<link rel='stylesheet' href='http://localhost:8080/build/assets/styles/{$entryName}.scss' type='text/css' />";
            else
                return '';
        }

        $url = $this->makeAbsoluteUrl("/build/{$entry}");

        return "<link rel='stylesheet' href='{$url}' type='text/css' />";
    }

    /**
     * @param $entryName
     * @return string
     */
    public function renderScriptTags($entryName ) {

        $entry = $this->getFromManifest("assets/scripts/".$entryName.".js");

        if( !$entry && WP_DEBUG){

            return "<script type='module' src='http://localhost:8080/build/@vite/client'></script>".
                "<script type='module' src='http://localhost:8080/build/assets/scripts/{$entryName}.js'></script>";
        }

        $url = $this->makeAbsoluteUrl("/build/{$entry}");

        return "<script type='text/javascript' src='{$url}' defer></script>";
    }

    /**
     * @return string
     */
    public function blockEditorSettingsThemeCSS($url) {

        if( !is_file(self::$manifest_file) && !is_file(self::$vite_file) )
            return $url;

        $entry = $this->getFromManifest("assets/styles/app.scss");

        if( !$entry ){

            if( WP_DEBUG )
                return "http://localhost:8080/build/assets/styles/app.scss";
            else
                return '';
        }

        $path = '/build/'.$entry;

        return $this->makeAbsoluteUrl($path);
    }


    /**
     * @param $entryName
     * @param $version
     * @return false|mixed
     */
    public function asset($entryName, $version=0) {

        if( str_starts_with($entryName, 'http') )
            return $entryName;

        $url = '/static/' . $entryName;

        if( !is_file(__DIR__.'/../public'.$url) )
            return '';

        if( $version )
            $url .= (str_contains($url, '?') ? '&v=' : '?v=' ).$version;

        return $this->makeAbsoluteUrl($url);
    }


    /**
     * @param $url
     * @return string
     */
    public function makeAbsoluteUrl($url) {

        if( str_starts_with($url, 'http') )
            return $url;

        if( is_multisite() )
            return network_home_url($url);
        else
            return home_url($url);
    }

    public function getFunctions(): array
    {
        if( !is_file(self::$manifest_file) && !is_file(self::$vite_file) )
            return [];

        return [
            new TwigFunction('asset', [$this, 'asset'] ),
            new TwigFunction( 'vite_entry_link_tags', [$this, 'renderLinkTags'] ),
            new TwigFunction( 'vite_entry_script_tags', [$this, 'renderScriptTags'] )
        ];
    }
}