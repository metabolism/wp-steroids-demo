<?php

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EncoreExtension extends AbstractExtension
{
    private $entrypoints;
    private $manifest;

    static $entrypoints_file = __DIR__.'/../../public/build/entrypoints.json';
    static $manifest_file = __DIR__.'/../../public/build/manifest.json';

    static $webpack_file = __DIR__.'/../../webpack.config.js';

    public function __construct()
    {
        if( file_exists(self::$webpack_file) || file_exists(self::$manifest_file) )
            add_filter('block_editor_settings_theme_css', [$this, 'blockEditorSettingsThemeCSS']);
    }

    /**
     * @return string
     */
    function blockEditorSettingsThemeCSS() {

        $path = $this->getFromManifest("build/bundle.css");

        if( !$path ){

            if( WP_DEBUG )
                return "http://localhost:8080/build/bundle.css";
            else
                return '';
        }

        if( str_starts_with($path, 'http') )
            return $path;

        if( is_multisite() )
            return network_home_url($path);
        else
            return home_url($path);
    }

    public function getFromEntryPoints($entryName, $type)
    {
        if( file_exists(self::$entrypoints_file) && is_null($this->entrypoints) )
            $this->entrypoints = json_decode(file_get_contents(self::$entrypoints_file), true);

        return $this->entrypoints['entrypoints'][$entryName][$type]??[];
    }

    public function getFromManifest($entryName)
    {
        if( file_exists(self::$manifest_file) && is_null($this->manifest) )
            $this->manifest = json_decode(file_get_contents(self::$manifest_file), true);

        return $this->manifest[$entryName]??false;
    }

    /**
     * @param $entryName
     * @return string
     */
    public function renderLinkTags($entryName) {

        $entries = $this->getFromEntryPoints($entryName, 'css');

        $styles = [];

        foreach ($entries as $entry)
            $styles[] = "<link rel='stylesheet' href='{$entry}' type='text/css' media='all' />";

        return implode('', $styles);
    }

    /**
     * @param $entryName
     * @return string
     */
    public function renderScriptTags($entryName ) {

        $entries = $this->getFromEntryPoints($entryName, 'js');

        $scripts = [];

        foreach ($entries as $entry)
            $scripts[] = "<script type='text/javascript' src='{$entry}' defer></script>";

        return implode('', $scripts);
    }


    /**
     * @param $entryName
     * @return false|string
     */
    public function asset($entryName ) {

        return $this->getFromManifest('build/'.$entryName);
    }

    public function getFunctions(): array
    {
        if( !file_exists(self::$webpack_file) && !file_exists(self::$manifest_file) )
            return [];

        return [
            new TwigFunction('asset', [$this, 'asset'] ),
            new TwigFunction( 'encore_entry_link_tags', [$this, 'renderLinkTags'] ),
            new TwigFunction( 'encore_entry_script_tags', [$this, 'renderScriptTags'] )
        ];
    }
}