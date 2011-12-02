/*

Example:
var foobar = new Slidey();
foobar.show($('foobar'));
		
		
var foobar = new Slidey({			
	showPos: function(e,w){
		var totop = w.scroll.y;
		var toleft = 0;
		return {start:{top:(totop-24), left:toleft}, end:{top:totop, left:toleft} };
	}
});
foobar.show($('foobar'));	

Options:

showPos: function(boxSize, windowSize)
* should return {start:{top, left}, end:{top, left}}

hidePos: function(currentTop, currentLeft)
* should return {start:{top, left}, end:{top, left}}

*/
var Slidey = new Class({
	Implements: [Events, Options],
	
	options: {
		pause: 3000,
		disable_window_click_event: false,
		fx:{
			duration: 500,
			transition: Fx.Transitions.Quad.easeOut
		},
		styles:{
			position: 'absolute',
			top: '0px',
			left: '0px',
			opacity: 0	
		},
		styles_modal: {
			'background-color': '#000000',
			'opacity': .8
		},
		onComplete: function(visible){},
		onStart: function(visible){},
		
		showPos: function(boxSize, windowSize){			
			var toleft = (windowSize.scrollSize.x / 2) - (boxSize.size.x / 2);
			var totop = (windowSize.scroll.y) + 100;
			
			return {start:{top:(totop-24), left:toleft}, end:{top:totop, left:toleft} };
		},
		hidePos: function(currentTop, currentLeft){
			return {start:{top:currentTop, left:currentLeft}, end:{top:(currentTop-54), left:currentLeft} };
		},
		id: "Slidey"
	},
	
	box: Class.empty,
	modal: Class.empty,
	fx: Class.empty,
	visible: false,
	close_button: false,
	timer: 0,
	is_modal: false,
	window_click_event: {},
	window_resize_event: {},
	
	initialize: function(options){
		this.setOptions(options);
		
		this.window_click_event = this.hide.bind(this);
		
		this.window_resize_event = function(){
			if (!this.is_modal) return;
			
			var s = window.getScrollSize();
			this.modal.setStyles({
				'height': s.y+'px',
				'width': s.x+'px'
			});
			
		}.bind(this);
		
		// init MODAL
		this.modal = new Element('div', {
			'id': 'slidey_modal',
			'styles': {
				width: window.getSize().x,
				height: window.getSize().y,
				position: 'absolute',
				top: '0px',
				left: '0px',
				display: 'none'
			}
		})
		.setStyles(this.options.styles_modal)
		.injectInside(document.body);
		
		
		this.box = new Element('div', {
			'id': this.options.id
		})
		.setStyles(this.options.styles)
		.injectAfter(this.modal);
		
		this.box.set('morph', {
			duration: this.options.fx.duration, 
			transition: this.options.fx.transition,
			wait: true,
			onComplete: this.autohide.bind(this)
		});
		
	},
	
	show: function(el){
		//window.addEvent('click', this.window_click_event);
		
		this.is_modal = arguments[1] || false;

		if ($type(el) != "element") return;

		var clone = el.clone();
		if ($type(clone) != "element") return;

		var e = this.box.empty().adopt(clone).getSize();
		var w = window.getSize();

		var pos = this.options.showPos({
			scrollSize: this.box.getScrollSize(),
			size: this.box.getSize(),
			scroll: this.box.getScroll()
		},{
			scrollSize: window.getScrollSize(),
			size: window.getSize(),
			scroll: window.getScroll()
		});

		this.box.setStyles({
			'top': pos.start.top,
			'left': pos.start.left
		});

		this.close_button = false;
		this.visible = true;
		this.fireEvent('start', this.visible);
		
		$clear(this.timer);
		
		this.box.morph({
			'opacity': [.3,1],
			'top': [pos.start.top, pos.end.top],
			'left': [pos.start.left, pos.end.left]
		});
		
		var close = $(this.box).getElement('a.close');
		if (close) {
			this.close_button = true;
			close.addEvent('click', this.hide.bind(this));
		}
		
		if (this.is_modal) {
			var s = window.getScrollSize();
			this.modal.setStyles({
				'display': 'block',
				'height': s.y+'px',
				'width': s.x+'px'
			});
			
			this.modal.removeEvent('click', this.window_click_event);
			if (!this.options.disable_window_click_event)
				this.modal.addEvent('click', this.window_click_event);
				
			window.addEvent('resize', this.window_resize_event);
		}
		
		
	},
	
	autohide: function(){
		
		if (this.visible && !this.close_button) {
			this.timer = (function(){
				this.hide();
			}).bind(this).delay(this.options.pause, this);	
		}
		
		this.fireEvent('complete', this.visible);
		
	},
	
	hide: function(){

		if (!this.visible) return;
		
		this.visible = false;
		this.fireEvent('start', this.visible);
		
		var pos = this.box.getPosition();

		var pos = this.options.hidePos(pos.y, pos.x);

		this.box.setStyles({
			'top': pos.start.top,
			'left': pos.start.left
		});
		this.box.morph({
			opacity: [1,0],
			top:[pos.start.top, pos.end.top],
			left:[pos.start.left, pos.end.left]
		});
		
		if (this.is_modal) {
			this.modal.setStyle('display','none');
			window.removeEvent('resize', this.window_resize_event);
		}

	}
});
