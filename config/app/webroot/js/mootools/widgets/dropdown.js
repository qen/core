var Dropdown = new Class({
	options: {
		fx:{
			duration: 200,
			transition: Fx.Transitions.Circ.easeOut,
			show: {},
			hide: {}
		}
	},
	container: Class.empty,
	active: Class.empty,
	
	show: function(el){
		//if (!el.fx_styles) return;
		if (el == this.active) return;	
		
		if ($type(this.active) == 'element')
			this.hide(this.active);
		
		if (el.submenu) {
			var style_show = {
				height: [0, el.submenu.height_original],
				opacity: [0, 1]
			}
			var styles = this.options.fx.show;
			$extend(styles, style_show);
			el.submenu.fx_styles.start(styles);
		}//end if
		
		el.addClass('active');
		this.active = el;
		
	},
	
	hide: function(el){
		if ($type(el) != 'element') return;

		if (el.submenu){
			var style_hide = {
				height: [el.submenu.height_original,0],
				opacity: [1,0]
			}
			var styles = this.options.fx.hide;
			
			$extend(styles, style_hide);
		
			el.submenu.fx_styles.start(styles);
		}//end if
		el.removeClass('active');
		
		this.active = Class.empty;
	},
		
	initialize: function(container){
		this.setOptions(arguments[1]||{});
		
		this.container = $(container);		
		
		//$ES('li', this.container).each(function(li, index){
		$(this.container).getElements('li').each(function(li, index){
		
			var alink = li.getElement('a');
			if (!alink) return;
			var menu = $(li);
			var submenu = $(li).getElement('div');
			
			if (!submenu) {
				li.addEvent('mouseenter', this.show.pass([menu],this));
				li.addEvent('mouseleave',this.hide.pass([menu],this));
				return;
			}//end if
			
			var init_top_pos = alink.getCoordinates();
			var init_left_pos = li.getCoordinates();
			
			submenu.fx_styles = new Fx.Morph(submenu, {duration: this.options.fx.duration, transition: this.options.fx.transition, wait:false});
			submenu.height_original = $(submenu).getSize().y;
			submenu.setStyles({
				'height': '0px',
				'overflow': 'hidden',
				'position': 'absolute',
				'opacity': '0',
				'top': (init_top_pos.top+init_top_pos.height)+'px',
				'left': init_left_pos.left+'px'
			});
			menu.submenu = submenu;
			
			li.addEvent('mouseenter', this.show.pass([menu],this));
			li.addEvent('mouseleave',this.hide.pass([menu],this));
			
		},this);

	}
});
Dropdown.implement(new Options);
Dropdown.implement(new Events);