let supportsPassive = false;

try {
    let opts = Object.defineProperty({}, 'passive', {
        get: function() {
            supportsPassive = true;
        }
    });
    window.addEventListener("testPassive", null, opts);
    window.removeEventListener("testPassive", null, opts);
} catch (e) {}

let aosPrefixAnimation = (function(){

    function lowerCaseEventTypes(prefix) {
        prefix = prefix || '';

        return {
            fn: prefix.length?prefix.toLowerCase()+'Animation':'animation',
            start: prefix + 'animationstart',
            end: prefix + 'animationend',
            iteration: prefix + 'animationiteration'
        };
    }

    function camelCaseEventTypes(prefix) {
        prefix = prefix || '';

        return {
            fn: prefix.length?prefix.toLowerCase()+'Animation':'animation',
            start: prefix + 'AnimationStart',
            end: prefix + 'AnimationEnd',
            iteration: prefix + 'AnimationIteration'
        };
    }
    let prefixes = ['webkit', 'Moz', 'O', ''];
    let style = document.documentElement.style;

    if(style.animationName !== undefined)
        return lowerCaseEventTypes();

    for(let i = 0, len = prefixes.length, prefix; i < len; i++) {
        prefix = prefixes[i];

        if(style[prefix + 'AnimationName'] !== undefined) {
            if(i === 0) {
                return camelCaseEventTypes(prefix.toLowerCase());
            } else if(i === 1) {
                return lowerCaseEventTypes();
            } else if(i === 2) {
                return lowerCaseEventTypes(prefix.toLowerCase());
            }
        }
    }

    return {};
})();

export default function AOSInterface($el, props){

    let data = {
        clientY: 0,
        clientX: 0,
        onShow: false,
        onHide: false,
        setAttribute: false,
        disabled: false,
        init: false,
        bounding: {},
        interval: false,
        timeout: false,
        current: false,
        shown: false,
        locked: false,
        strengthPercent : String(props.strength).indexOf('%') !==-1,
        offsetPercent : String(props.offset).indexOf('%') !==-1
    };

    data.strength = parseInt(String(props.strength).replace('%',''));
    data.offset = parseInt(String(props.offset).replace('%',''));
    data.delay = parseFloat(String(props.delay).replace('ms','').replace('s',''));
    data.duration = parseFloat(String(props.duration).replace('ms','').replace('s',''));

    let methods = {
        mounted() {
            if(
                (props.phone !== "disabled" && window.innerWidth <= 640) ||
                (props.tablet !== "disabled" && window.innerWidth <= 768 && window.innerWidth > 640) ||
                (props.small !== "disabled" && window.innerWidth <= 1024 && window.innerWidth > 768) ||
                (window.innerWidth > 1024)
            ) {

                $el.classList.add('on-scroll');

                if( props.animation && props.animation !== 'increment' )
                    $el.classList.add('on-scroll--wait');

                if( props.animation === 'increment' ){

                    $el.setAttribute('data-increment', $el.textContent.replace(/ /g, '').replace(',', '.'))
                    $el.textContent = 0
                }

                if( props.setAttribute )
                    $el.setAttribute('data-'+props.attribute, $el.getAttribute(props.attribute))

                methods.listen();
                data.init = true;
            }
            else{
                data.disabled = true;
            }
        },
        update(){

            if( data.disabled )
                return;

            let rect = $el.getBoundingClientRect(),
                scrollLeft = window.pageXOffset || document.documentElement.scrollLeft,
                scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            data.bounding = { top: rect.top + scrollTop, left: rect.left + scrollLeft, height: rect.height, width: rect.width, bottom: rect.bottom + scrollTop};

            methods.scroll();
        },
        updateClient(event){

            data.clientX = event.clientX;
            data.clientY = event.clientY;

            if( props.animation === 'follow'){

                const targetPosition = {
                    left:
                        $el.getBoundingClientRect().left +
                        $el.getBoundingClientRect().width / 2,
                    top:
                        $el.getBoundingClientRect().top +
                        $el.getBoundingClientRect().height / 2
                };

                const distance = {
                    x: event.clientX-targetPosition.left,
                    y: event.clientY-targetPosition.top
                };

                $el.animate({
                    transform: `translate(${distance.x/20}px, ${distance.y/20}px`
                }, {duration: 1000, fill: "forwards"})
            }
        },
        listen(){
            document.addEventListener('mousemove', methods.updateClient);
            document.addEventListener('resize', methods.update);
            document.addEventListener('scroll', methods.update, supportsPassive?{passive:true}:false );
            methods.update();
        },
        destroyed(){
            clearInterval(data.interval);
            document.removeEventListener('scroll', methods.update);
            document.removeEventListener('resize', methods.update);
        },
        end(){

            if( data.delay )
                $el.style[aosPrefixAnimation.fn+'Delay'] = '';

            if( data.duration )
                $el.style[aosPrefixAnimation.fn+'Duration'] = '';

            $el.classList.remove('on-scroll--'+props.animation);
            $el.classList.remove('on-scroll');
        },
        startCounter(el) {

            data.locked = true;

            const duration = (data.duration||1)*1000;

            const start = 0 // Get start and end values
            const end = parseFloat(el.getAttribute('data-increment'));
            const isInt = end % 1 === 0;

            if (start === end) return; // If equal values, stop here.

            const range = end - start; // Get the range
            let curr = start; // Set current at start position

            const timeStart = Date.now();

            const ease = {
                linear: t => t,
                inOutQuad: t => t<.5 ? 2*t*t : -1+(4-2*t)*t,
            };

            const countDecimals = Math.floor(end) === end ? 0 :(end.toString().split(".")[1].length || 0);
            const formater = new Intl.NumberFormat(document.documentElement.lang)

            const loop = () => {
                let elaps = Date.now() - timeStart;
                if (elaps > duration) elaps = duration; // Stop the loop
                const norm = ease.inOutQuad(elaps / duration); // normalised value + easing
                const step = norm * range; // Calculate the value step
                curr = start + step; // Increment or Decrement current value
                const increment = isInt?Math.trunc(curr):curr.toFixed(countDecimals);
                el.textContent = formater.format(increment)
                if (elaps < duration) requestAnimationFrame(loop);
                else data.locked = false
            };

            requestAnimationFrame(loop); // Start the loop!
        },
        increment(pos){

            if (pos > data.bounding.top && !data.locked && document.documentElement.lang.length)
                methods.startCounter($el)
        },
        follow(pos){

            if (pos > data.bounding.top && data.bounding.bottom > window.scrollY)
                data.locked = false
            else
                data.locked = true
        },
        rotate(pos){

            let offset = 0;

            if (pos > data.bounding.top && data.bounding.bottom > window.scrollY) {

                if (data.bounding.top < window.innerHeight) {
                    offset = window.scrollY / data.bounding.bottom;
                } else {
                    offset = (pos - data.bounding.top) / (data.bounding.bottom + window.innerHeight - data.bounding.top);
                }
            }
            else {

                offset = pos > data.bounding.top ? 1 : 0;
            }

            offset = Math.max(0, Math.min(1, offset));
            offset = props.invert ? 1 - offset : offset;
            offset = props.center ? offset - 0.5 : offset;

            if( data.current !== offset)
            {
                data.current = offset;

                let value = data.strengthPercent ? Math.round(offset*data.strength*1000)/1000 : Math.round(offset*data.strength*10)/10;
                let strength = 0;

                strength = value+'deg';
                $el.style.transform = 'rotate('+strength+')';
                $el.style.WebkitTransform = 'rotate('+strength+')';
            }
        },
        parallax(pos){

            let offset = 0;

            if (pos > data.bounding.top && data.bounding.bottom > window.scrollY) {

                if (data.bounding.top < window.innerHeight) {
                    offset = window.scrollY / data.bounding.bottom;
                } else {
                    offset = (pos - data.bounding.top) / (data.bounding.bottom + window.innerHeight - data.bounding.top);
                }
            }
            else {

                offset = pos > data.bounding.top ? 1 : 0;
            }

            offset = Math.max(0, Math.min(1, offset));
            offset = props.invert ? 1 - offset : offset;
            offset = props.center ? offset - 0.5 : offset;

            if( data.current !== offset)
            {
                data.current = offset;

                let value = data.strengthPercent ? Math.round(offset*data.strength*1000)/1000 : Math.round(offset*data.strength*10)/10;
                let strength = 0;

                strength = data.strengthPercent ? value+'%' : value+'px';
                $el.style.transform = 'translateY('+strength+')';
                $el.style.WebkitTransform = 'translateY('+strength+')';

                if( !data.strengthPercent ){

                    clearTimeout(data.timeout);

                    data.timeout = setTimeout(function(){

                        value = Math.round(value);

                        strength = data.strengthPercent ? value+'%' : value+'px';
                        $el.style.transform = 'translateY('+strength+')';
                        $el.style.WebkitTransform = 'translateY('+strength+')';

                    },100);
                }
            }
        },
        getLastRealChild(el) {
            let child = el.lastChild;
            while (child && child.nodeType === Node.TEXT_NODE && child.textContent.trim() === '') {
                child = child.previousSibling;
            }
            return child;
        },
        scroll(){

            let pos = 0;

            if( props.animation === 'parallax')
                pos = window.scrollY + window.innerHeight;
            else if( data.offsetPercent )
                pos = window.scrollY + window.innerHeight*data.offset/100;
            else
                pos = window.scrollY + window.innerHeight - data.offset;

            if ( (data.bounding.top <= pos && !data.shown) || props.animation === 'parallax' || props.animation === 'rotate') {

                if( props.animation === 'parallax') {
                    methods.parallax(pos);
                }
                else if( props.animation === 'rotate') {
                    methods.rotate(pos);
                }
                else if( props.animation === 'increment') {

                    methods.increment(pos);
                }
                else if( props.animation === 'follow') {

                    methods.follow();
                }
                else {

                    if (data.delay)
                        $el.style[aosPrefixAnimation.fn + 'Delay'] = data.delay + (data.delay < 10 ? 's' : 'ms');

                    if (data.duration)
                        $el.style[aosPrefixAnimation.fn + 'Duration'] = data.duration + (data.duration < 10 ? 's' : 'ms');

                    if( props.animation === 'stack' ){

                        let lastChild = methods.getLastRealChild($el);
                        lastChild.addEventListener(aosPrefixAnimation.end, methods.end, false);
                    }
                    else{

                        $el.addEventListener(aosPrefixAnimation.end, methods.end, false);
                    }
                }

                if( props.setAttribute && !data.shown )
                    $el.setAttribute(props.setAttribute, props.value)

                if( props.onShow && !data.shown )
                    props.onShow()

                if( !data.shown ){

                    if( props.animation !== 'increment' && props.animation.length ){

                        $el.classList.remove('on-scroll--wait');
                        $el.classList.add('on-scroll--'+props.animation);
                    }

                    data.shown = true;
                }
            }

            if( data.bounding.top > window.scrollY + window.innerHeight && data.shown && props.animation !== 'parallax'){

                if( props.animation !== 'increment' ){

                    if( props.animation.length ){

                        $el.classList.add('on-scroll--wait');
                        $el.classList.remove('on-scroll--'+props.animation);
                    }
                }
                else{

                    $el.textContent = 0
                }

                data.shown = false;

                if( props.setAttribute)
                    $el.setAttribute(props.setAttribute, $el.getAttribute('data-'+props.setAttribute))

                if( props.onHide )
                    props.onHide()
            }
        }
    };

    return methods;
}
