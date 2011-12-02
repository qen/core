var Pagenav = new Class({
	Implements: [Options, Events],
	options: {
		page_threshold: 10,
		click:function(x, y){
			alert(x + " " + y );
		}
	},
	container: Class.empty,
	
	initialize: function(el, options){
		if ($type(el) !== 'element') return;
		this.container = el;
		this.setOptions(options);
	},
	
	create : function($current_page, $total_page){
		if ($type(this.container) !== 'element') return;
		
		this.container.setStyle('display', 'none');
		if ($total_page == 1) return;
		
		var containter  = this.container.getElement('span#pages');
		if (!containter) return;
		
		this.container.setStyle('display', 'block');
		
		var $current_page = ($current_page < 1)? 1: $current_page;		
		$current_page = ($current_page > $total_page)? $total_page: $current_page;
		
		var $center = (this.options.page_threshold / 2);
		
		var $start = $current_page - $center;
		var $end = $current_page + $center;
		
		$start = ($start > this.options.page_threshold)? this.options.page_threshold+1: $start;
		$start = ($start < 1)? 1: $start;
		
		$end = ($end < this.options.page_threshold)? this.options.page_threshold: $end-1;		
		$end = ($end > $total_page)? $total_page: $end;
		
		var $pages = [];
		var i = 0;
		this.container.getElement('#pages').empty();
		for(i = $start; i<=$end; i++){
			var page_index = i;
			var $class = '';
			
			if (page_index == $current_page)
				$class = 'pageCurrent';
				
			var rel = {
				selPage: page_index,
				curPage: $current_page
			};

			var pagelink = new Element('a', {
				'class': $class
			}).set('html',page_index)
			.injectInside(this.container.getElement('#pages'))
			.addEvents({
				'click': this.options.click.pass([page_index, $current_page], this)
			});
			
			
		}
		
		var elprev = this.container.getElement('a#previous');
		if (elprev) {
			elprev.addClass('disabled').removeEvents();
			if ($current_page.toInt() > 1) {
				elprev.removeEvents().removeClass('disabled').addEvents({
					'click': this.options.click.pass([($current_page.toInt()-1), $current_page], this)
				});

			}//end if
		}//end 
		
		var elnext = this.container.getElement('a#next');
		if (elnext) {
			elnext.addClass('disabled').removeEvents();
			if ($current_page.toInt() < $total_page.toInt()) {
				elnext.removeEvents().removeClass('disabled').addEvents({
					'click': this.options.click.pass([($current_page.toInt()+1), $current_page], this)
				});

			}//end if
		}//end 
			
	}
});