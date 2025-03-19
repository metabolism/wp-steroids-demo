import {LitElement} from "lit";
import AOSInterface from "../plugins/aos.js";

export default class OnScroll extends LitElement {

    static properties = {
        animation: {type: String},
        small: {type: String},
        tablet: {type: String},
        phone: {type: String},
        invert: {type: Boolean},
        center: {type: Boolean},
        loop: {type: Boolean},
        delay: {type: Number},
        offset: {type: Number},
        strength: {type: Number},
        duration: {type: Number},
    };

    // Disable Shadow node
    createRenderRoot() {
        return this;
    }

    constructor() {

        super();

        this.animation = 'slide-up' ;
        this.delay = 0;
        this.offset = 150;
        this.strength = 200;
        this.duration = 0.5;
        this.invert = false;
        this.center = false;
        this.loop = false;
        this.small = 'active';
        this.tablet = 'active';
        this.phone = 'disabled';
    }

    connectedCallback() {

        super.connectedCallback();

        let props = {};

        for (const key in this.constructor.properties)
            props[key] = this[key];

        this.aos = new AOSInterface(this, props);
        this.aos.mounted();
    }

    disconnectedCallback() {

        super.disconnectedCallback()
        this.aos.destroyed();
    }
};
