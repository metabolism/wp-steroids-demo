import {LitElement} from 'lit';

export default class Toggles extends LitElement {

    static properties = {
        lock: {type: Boolean},
        index: {type: Number},
        $content: {type: Array},
    };

    // Disable Shadow node
    createRenderRoot() {
        return this;
    }

    constructor() {

        super();

        this.$content = this.querySelectorAll('.js-content');
        this.lock = false;

        this.select(0, false);
    }

    connectedCallback() {

        super.connectedCallback();
        this.addEventListener('click', this.click);
    }

    disconnectedCallback() {

        super.disconnectedCallback();
        this.removeEventListener('click', this.click);
    }

    click(e){

        const target = e.target.closest('.js-cta');

        if (target) {
            e.preventDefault();

            const index = parseInt(target.dataset.index);
            this.select(index, true);
        }
    }

    /**
     * SlideUp
     *
     * @param {HTMLElement} element
     * @param {Number} duration
     * @returns {Promise<boolean>}
     */
    slideUp(element, duration = 500) {

        return new Promise(function (resolve, reject) {

            element.style.height = element.offsetHeight + 'px';
            element.style.transitionProperty = `height, margin, padding`;
            element.style.transitionDuration = duration + 'ms';
            element.offsetHeight;
            element.style.overflow = 'hidden';
            element.style.height = '0px';
            element.style.paddingTop = '0px';
            element.style.paddingBottom = '0px';
            element.style.marginTop = '0px';
            element.style.marginBottom = '0px';

            window.setTimeout(function () {
                element.style.display = 'none';
                element.style.removeProperty('height');
                element.style.removeProperty('padding-top');
                element.style.removeProperty('padding-bottom');
                element.style.removeProperty('margin-top');
                element.style.removeProperty('margin-bottom');
                element.style.removeProperty('overflow');
                element.style.removeProperty('transition-duration');
                element.style.removeProperty('transition-property');
                resolve(true);
            }, duration);
        });
    }

    /**
     * SlideDown
     *
     * @param {HTMLElement} element
     * @param {Number} duration
     * @returns {Promise<boolean>}
     */
    slideDown(element, duration = 500) {

        return new Promise(function (resolve, reject) {

            element.style.removeProperty('display');
            let display = window.getComputedStyle(element).display;

            if (display === 'none')
                display = 'block';

            element.style.display = display;
            const height = element.offsetHeight;
            element.style.overflow = 'hidden';
            element.style.height = '0px';
            element.style.paddingTop = '0px';
            element.style.paddingBottom = '0px';
            element.style.marginTop = '0px';
            element.style.marginBottom = '0px';
            element.offsetHeight;
            element.style.transitionProperty = `height, margin, padding`;
            element.style.transitionDuration = duration + 'ms';
            element.style.height = height + 'px';
            element.style.removeProperty('padding-top');
            element.style.removeProperty('padding-bottom');
            element.style.removeProperty('margin-top');
            element.style.removeProperty('margin-bottom');

            window.setTimeout(function () {
                element.style.removeProperty('height');
                element.style.removeProperty('overflow');
                element.style.removeProperty('transition-duration');
                element.style.removeProperty('transition-property');
                resolve(false);
            }, duration);
        });
    }

    select(index, animate = true){

        if( this.index === index || this.lock )
            return;

        this.lock = true;

        const oldIndex = this.index;
        this.index = index;

        this.$content.forEach((el, index) => {

            if(this.index === index)
                this.slideDown(el, animate ? 500 : 0).then(()=> this.lock = false );
            else if( index === oldIndex )
                this.slideUp(el, animate ? 500 : 0).then(()=> this.lock = false );
        });

        if( !animate )
            this.lock = false;
    }
}
