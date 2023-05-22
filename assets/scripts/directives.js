const youtube = {
	inserted(el, binding, vnode) {

		const youtube_id = binding.value

		const replaceHtml = function (){

			let div = document.createElement("div")
			div.classList.add('youtube')
			div.innerHTML = '<iframe src="//www.youtube-nocookie.com/embed/'+youtube_id+'?autoplay=1&rel=0&showinfo=0&modestbranding=1&playsinline=1&mute=1" allow="encrypted-media; autoplay;" allowfullscreen></iframe>'
			el.append(div);

			el.removeEventListener('click', replaceHtml)
		}

		el.addEventListener('click', replaceHtml)
	}
};


const table = {
	inserted(el, binding, vnode) {

		let tables = el.querySelectorAll('table')
		tables.forEach((e) => {
			let wrapper = document.createElement('div');
			wrapper.classList.add('table-wrapper')
			e.parentNode.insertBefore(wrapper, e);
			wrapper.appendChild(e);
		})
	}
};

const link = {
	inserted(el, binding, vnode) {

		let links = el.querySelectorAll('a');

		links.forEach(node=>{

			if( node.href.indexOf(window.location.host) === -1 )
				node.target = '_blank'
		})
	}
};

const anchor = {
	inserted(el, binding, vnode) {

		let parent = el.parentNode;

		if( parent ){

			parent.classList.add('has-anchor')
			parent.setAttribute('id', el.id)
			parent.setAttribute('data-title', el.getAttribute('title'))

			el.remove()
		}
	}
};

export {youtube, anchor, link, table}
