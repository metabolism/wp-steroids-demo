import {LitElement, html} from 'lit';

export default class Burger extends LitElement {

    static properties = {
        link: {type: String},
    };

    // Disable Shadow node
    createRenderRoot() {
        return this;
    }

    render() {
        return  html`<button class="p-burger" @click="${this.toggleVisibility}">
          <span class="p-burger__link">${this.link}</span>
          <span class="p-burger__icon"><i></i></span>
        </button>`;
    }

    toggleVisibility(){

        document.body.classList.toggle('burger-is-open');
    }
}
