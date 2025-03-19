<?php

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ViteExtension extends AbstractExtension
{
    private $manifest;

    public function __construct()
    {
        if( file_exists(__DIR__.'/../../public/build/.vite/manifest.json'))
            $this->manifest = json_decode(file_get_contents(__DIR__.'/../../public/build/.vite/manifest.json'), true);

        add_filter('block_editor_settings_theme_css', [$this, 'blockEditorSettingsThemeCSS']);
    }

    public function getFromManifest($entry)
    {
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

        return "<link rel='stylesheet' href='/build/{$entry}' type='text/css' />";
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

        return "<script type='text/javascript' src='/build/{$entry}' defer></script>";
    }

    /**
     * @return string
     */
    function blockEditorSettingsThemeCSS() {

        $entry = $this->manifest["assets/styles/app.scss"]['file']??false;

        if( !$entry ){

            if( WP_DEBUG )
                return "http://localhost:8080/build/assets/styles/app.scss";
            else
                return '';
        }

        $path = '/build/'.$entry;

        if( str_starts_with($path, 'http') )
            return $path;

        if( is_multisite() )
            return network_home_url($path);
        else
            return home_url($path);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction( 'vite_entry_link_tags', [$this, 'renderLinkTags'] ),
            new TwigFunction( 'vite_entry_script_tags', [$this, 'renderScriptTags'] )
        ];
    }
}