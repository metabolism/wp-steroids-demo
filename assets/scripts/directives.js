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

export {youtube}
