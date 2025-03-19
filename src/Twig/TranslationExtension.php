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

    public function __construct(){

        add_action( 'init', [$this, 'getTranslations']);
    }

    /**
     * @return void
     */
    public function getTranslations()
    {
        $options = new Options();

        if( $translations = $options->get('translations') )
        {
            $this->translations = [];

            foreach ($translations as $translation)
            {
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

            $debug = ($_GET['debug']??false) == 'translation' && defined('WP_DEBUG') && WP_DEBUG;

            if( $debug )
                return '{{'.htmlspecialchars($text).'}}';

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