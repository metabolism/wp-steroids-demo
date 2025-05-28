<?php

use Timber\Factory\PostFactory;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\Runtime\EscaperRuntime;
use Timber\Image;
use Timber\ImageHelper;
use kornrunner\Blurhash\Blurhash;

final class TranslationExtension extends AbstractExtension
{
    private $translations;
    private static $missing_translations=[];

    public function __construct(){

        $this->getTranslations();

        add_action('wp_footer', [$this, 'printMissingTranslations'], 10000);;
    }

    /**
     * @return void
     */
    public function printMissingTranslations(){

        $debug = ($_GET['debug']??false) == 'translation' && current_user_can('manage_options');

        if( $debug && !empty(self::$missing_translations) ){

            $missing_translations = array_unique(self::$missing_translations);
            echo '<div style="background:#ff0f0f;color:white;padding:20px;margin-top:20px"><b>Missing translations</b><br/><br/>'.implode('<br/>', $missing_translations).'</div>';
        }

        self::$missing_translations = [];
    }

    public function getTranslations()
    {
        $options = new Options();

        if( $translations = $options->get('translations') ) {

            $this->translations = [];

            foreach ($translations as $translation) {

                $key = sanitize_title($translation['key']);
                $this->translations[$key] = $translation['translation'];
            }
        }
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

            $debug = ($_GET['debug']??false) == 'translation' && current_user_can('manage_options');

            if( $debug ){

                self::$missing_translations[] = $text;
                return '{{'.htmlspecialchars($text).'}}';
            }

            return vsprintf($text, $params);
        }
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('t', [$this, 'translate'])
        ];
    }
}