<?php

/**
 * Write specific project code here
 * Kernel extends \Timber\Site to add useful functions
 */

use Timber\Menu;

class Site extends Kernel {

    public function addToContext($context)
    {
        $context = parent::addToContext($context);

        $context['menu'] = [
            'header'=>new Menu('header'),
            'footer'=>new Menu('footer')
        ];

        return $context;
    }
}

new Site();