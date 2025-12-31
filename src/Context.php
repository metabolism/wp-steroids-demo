<?php

class Context
{
    /**
     * @param $props
     * @return array
     */
    public function blockHero($props){


        $props['lorem'] = 'ipsum';

        return $props;
    }
}