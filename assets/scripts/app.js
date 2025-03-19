/**
 * Application
 *
 * Copyright (c) 2024 - Metabolism
 *
 * License: GPL
 * Version: 2
 *
 * Requires:
 *   - VueJS3
 *
 **/

'use strict';

// load browser detection
import Bowser from "bowser";
import {LitElement} from 'lit';

// register Swiper custom elements
import { register } from 'swiper/element/bundle';
register();

import defineCustomElements from './customElements.js';
defineCustomElements();

class App extends LitElement {

    static properties = {
        $footer: {type: Node},
        $header: {type: Node},
        sticky: {type: Boolean},
        sticky_bottom: {type: Boolean},
        scroll_position: {type: Number},
        scroll_timer: {type: Number},
        scrolled: {type: Number},
        scroll_down: {type: Boolean},
        elements_height: {type: Object},
    };

    createRenderRoot() {
        return this;
    }

    constructor() {

        super();

        this.popin = false;
        this.sticky = false;
        this.sticky_bottom = false;
        this.scroll_position = 0;
        this.scroll_timer = 0;
        this.scrolled = 0;
        this.scroll_down = false;
        this.elements_height = {
            footer: 0,
            header: 0
        }

        this.$header = this.querySelector('header');
        this.$footer = this.querySelector('footer');
    }

    toggle(classname, e) {

        if( e ){

            e.target.classList.toggle('is-'+classname)
        }
        else{

            if( this.popin === classname)
                this.popin = false;
            else
                this.popin = classname;

            document.body.classList.toggle('has-'+classname)
        }
    }

    catchScroll() {

        clearTimeout(this.scroll_timer);

        if(!document.body.classList.contains('disable-hover'))
            document.body.classList.add('disable-hover')

        this.scroll_timer = setTimeout(function(){
            document.body.classList.remove('disable-hover')
        }, 200);

        let scroll = document.documentElement.scrollTop || document.body.scrollTop
        let sticky = scroll>150
        let scrolled = scroll>1000
        let timeout = false;

        if( scrolled && !this.scrolled )
            document.body.classList.add('has-seen-page')

        this.scrolled = scrolled;

        if( sticky && sticky !== this.sticky ){

            document.body.classList.add('has-scrolled')

            this.sticky = sticky;
        }

        if( !scroll ){

            if( timeout )
                clearTimeout(timeout);

            timeout = setTimeout(function (){
                document.body.classList.remove('has-seen-page')
            },300)

            document.body.classList.remove('has-scrolled')
            document.body.classList.remove('has-scrolled--down')
            document.body.classList.remove('has-scrolled--up')
            document.body.classList.remove('has-scrolled--changed')

            this.sticky = false;
        }

        if( sticky ){

            if( this.scroll_position > scroll ){

                if( this.scroll_down === true ){

                    document.body.classList.add('has-scrolled--up')

                    if( document.body.classList.contains('has-scrolled--down') ){

                        document.body.classList.add('has-scrolled--changed')
                        document.body.classList.remove('has-scrolled--down')
                    }
                }
            }
            else{

                if( this.scroll_down === false ){

                    document.body.classList.add('has-scrolled--down')

                    if( document.body.classList.contains('has-scrolled--up') ){

                        document.body.classList.add('has-scrolled--changed')
                        document.body.classList.remove('has-scrolled--up')
                    }
                }
            }

            this.scroll_down = scroll > this.scroll_position
        }

        if( document.documentElement.offsetHeight - window.innerHeight - scroll < this.elements_height.footer && document.body.offsetHeight > window.innerHeight )
        {
            if( !this.sticky_bottom ){

                document.body.classList.add('has-scrolled--bottom')
                this.sticky_bottom = true;
            }
        }
        else{

            if( this.sticky_bottom ){

                document.body.classList.remove('has-scrolled--bottom')
                this.sticky_bottom = false;
            }
        }

        this.scroll_position = scroll;
    }

    catchResize(){

        document.documentElement.style.setProperty('--app-height', `${window.innerHeight}px`);

        this.elements_height.footer = this.$footer ? this.$footer.clientHeight : 0;
        this.elements_height.header = this.$header ? this.$header.clientHeight : 0;
    }

    handleHash(){

        //todo
    }

    addBrowserClasses(){

        const browser = Bowser.getParser(window.navigator.userAgent);

        if( !browser.satisfies({"internet explorer": ">11", safari: '>=13', chrome: ">=85", firefox: ">=83", edge: ">=84"}) ){

            document.body.classList.add('unsupported-browser')
        }
        else{

            document.body.classList.add(browser.getPlatformType(true))
            document.body.classList.add(browser.getOSName(true))
        }
    }

    connectedCallback() {

        super.connectedCallback();

        document.documentElement.style.setProperty('--app-init-height', `${window.innerHeight}px`);

        this.catchScroll();
        this.catchResize();
        this.handleHash();

        this.addBrowserClasses();

        window.addEventListener('scroll', ()=>this.catchScroll());
        window.addEventListener('resize', ()=>this.catchResize());
        window.addEventListener('hashchange', ()=>this.handleHash());

        document.body.classList.remove('loading');
        document.body.classList.add('loaded');
    }
}

customElements.define('x-app', App);