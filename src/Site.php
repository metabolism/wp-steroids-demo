<?php

/**
 * Activate Timber theme to use this file
 *
 * Write specific project code here
 * Kernel extends \Timber\Site to add useful functions
 *
 */

use Timber\Timber;

class Site extends Kernel {

    public function addToContext($context)
    {
        $context = parent::addToContext($context);

        $context['menu'] = [
            'header'=>Timber::get_menu('header'),
            'footer'=>Timber::get_menu('footer')
        ];

        return $context;
    }
}

new Site();