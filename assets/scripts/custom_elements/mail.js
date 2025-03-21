import {html, LitElement} from 'lit';

export default class Mail extends LitElement {

    static properties = {
        name: {type: String},
        domain: {type: String},
        text: {type: String}
    };

    render() {
        return  html`<a href="mailto:${this.name}@${this.domain}"><span>${this.text?this.text:this.name+'@'+this.domain}</span></a>`;
    }
}