import lottie from 'lottie-web'

const lottiePlayer = {
	props:['path', 'loop'],
	template:'<div class="lottie-player" v-observe-visibility="{callback: visibilityChanged, intersection: {threshold: 0.2}}"><slot></slot></div>',
	data(){
		return{
			player: false,
			played: false,
			isVisible: false
		}
	},
	methods:{
		visibilityChanged(isVisible, entry){

			this.isVisible = isVisible

			if( isVisible ){

				if( this.played && !this.loop )
					return;

				this.played = true;
				setTimeout(this.play, 500)
			}
			else{

				this.player.pause()
			}
		},
		play() {
			this.player.play()
		}
	},
	mounted() {
		this.player = lottie.loadAnimation({
			container: this.$el,
			renderer: 'svg',
			loop: this.loop,
			autoplay: false,
			path: this.path
		});
	}
};

export {lottiePlayer}
