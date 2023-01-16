/**
 * Application
 *
 * Copyright (c) 2023 - Akhela
 *
 * License: GPL
 * Version: 2
 *
 * Requires:
 *   - VueJS
 *
 **/

'use strict';

// load Vuejs
import Vue from 'vue';

// load lodash
import upperFirst from 'lodash/upperFirst'
import camelCase from 'lodash/camelCase'

// load Sentry
import * as Sentry from "@sentry/vue";

// import plugins
import detect from 'browser-system-detect';
window.system = detect({bodyClass:true})

import 'regenerator-runtime/runtime'

Vue.config.productionTip = false;

// load directives
import {youtube} from './directives'
Vue.directive('youtube', youtube);

// load filters
import {hash} from './filters'
Vue.filter('hash', hash);

import Plugins from './plugins'
Vue.use(Plugins);

import store from './store'

// load design system atoms, molecules, organisms
let blocks = require.context("../../templates/block", true, /^\.\/[^.]+\.js$/);
blocks.keys().forEach(fileName => {

    const blockConfig = blocks(fileName)
    const blockName = upperFirst(camelCase(fileName.split('/').pop().replace(/\.\w+$/, '')))

    //create global block
    Vue.component(blockName, blockConfig.default || blockConfig)
});

import VueResource from 'vue-resource';
Vue.use(VueResource);

import SsrCarousel from 'vue-ssr-carousel';
Vue.component('ssr-carousel', SsrCarousel);

import SlideUpDown from 'vue-slide-up-down'
Vue.component('slide-up-down', SlideUpDown)

import Vue2TouchEvents from 'vue2-touch-events';
import eventBus from "./event-bus";
Vue.use(Vue2TouchEvents);

if( location.hostname !== '127.0.0.1')
    Sentry.init({Vue, dsn: ''});

// start app
let app = new Vue({
    store,
    el: '#root',
    delimiters: ['[[', ']]'],
    data(){
        return{
            isMobile: window.innerWidth<768,
            isTablet: window.innerWidth<=1024,
            popin: false,
            displayed: false,
            sticky: false,
            sticky_bottom: false,
            scroll: 0,
            scrolled: 0,
            scroll_down: false,
            heights: {
                footer: 0,
                header: 0
            },
        }
    },
    methods:{
        toggle(classname, e) {

            if( e ){

                e.target.classList.toggle('is-'+classname)
            }
            else{

                if( this.displayed === classname)
                    this.displayed = false;
                else
                    this.displayed = classname;

                document.body.classList.toggle('has-'+classname)
            }
        },
        catchScroll(e) {

            let scroll = document.documentElement.scrollTop || document.body.scrollTop
            let sticky = scroll>150
            let scrolled = scroll>1000

            if( scrolled && !this.scrolled )
                document.body.classList.add('has-seen-page')

            this.scrolled = scrolled;

            if( sticky !== this.sticky ){

                if( sticky ){

                    document.body.classList.add('has-scrolled')
                }
                else{

                    setTimeout(function (){
                        document.body.classList.remove('has-seen-page')
                    },300)

                    document.body.classList.remove('has-scrolled')
                    document.body.classList.remove('has-scrolled--down')
                    document.body.classList.remove('has-scrolled--up')
                }

                this.sticky = sticky;
            }

            if( sticky ){

                if( this.scroll > scroll ){

                    if( this.scroll_down === true ){

                        document.body.classList.add('has-scrolled--up')
                        document.body.classList.remove('has-scrolled--down')
                    }
                }
                else{

                    if( this.scroll_down === false ){

                        document.body.classList.add('has-scrolled--down')
                        document.body.classList.remove('has-scrolled--up')
                    }
                }

                this.scroll_down = scroll > this.scroll
            }

            if( document.documentElement.offsetHeight - window.innerHeight - scroll < this.heights.footer && document.body.offsetHeight > window.innerHeight )
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

            this.scroll = scroll;
        },
        catchResize(){

            document.documentElement.style.setProperty('--app-height', `${window.innerHeight}px`);

            this.isMobile = window.innerWidth<768;
            this.isTablet = window.innerWidth<=1024;

            this.heights.footer = this.$refs.footer.clientHeight;
            this.heights.header = this.$refs.header.clientHeight;

            window.adjustFontSize();
        },
        emit(event, params){
            eventBus.$emit(event, params);
        }
    },
    mounted(){

        document.documentElement.style.setProperty('--app-init-height', `${window.innerHeight}px`);

        this.catchScroll(false);
        this.catchResize();

        let body_classList = document.body.classList;
        body_classList.remove('loading');
        body_classList.add('loaded');
    },
    created() {

        window.addEventListener('scroll', this.catchScroll);
        window.addEventListener('resize', this.catchResize);

        eventBus.$on('popin', (html)=>{

            this.popin = html
        })
    }
});

export default app
