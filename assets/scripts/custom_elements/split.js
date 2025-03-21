import {LitElement} from 'lit';
import SplitType from 'split-type';

export default class Split extends LitElement {

    static properties = {
        tag: {type: String},
        text: {type: Object}
    };

    connectedCallback() {

        super.connectedCallback();

        const value = {tagName:'span'};

        if( this.tag )
            value.tagName = this.tag;

        this.text = new SplitType(this, value);
    }
};
