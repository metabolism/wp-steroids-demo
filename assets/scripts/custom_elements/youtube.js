import {html, LitElement} from 'lit';

export default class Youtube extends LitElement {

    static properties = {
        id: {type: String},
        loaded: {type: Boolean}
    };

    constructor() {
        super();
        this.loaded = false;
    }

    createRenderRoot() {
        return this;
    }

    render() {
        return  html`<a>
            ${this.loaded ?html`<iframe src="//www.youtube-nocookie.com/embed/${this.id}?autoplay=1&rel=0&showinfo=0&modestbranding=1&playsinline=1&mute=1" allow="encrypted-media; autoplay;" allowfullscreen></iframe>` : ''}
        </a>`
    }

    load(){

        this.loaded = true
        this.removeEventListener('click', this.load)
    }

    connectedCallback() {

        super.connectedCallback();
        this.addEventListener('click', this.load)
    }

    disconnectedCallback() {

        super.disconnectedCallback()
        this.removeEventListener('click', this.load)
    }
}