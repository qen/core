window.addEvents({
	domready: function(){

		if ($$('div.swapimages img'))
			$$('div.swapimages img').addClass('swapimage');
		
		if ($$('img.swapimage'))
			$$('img.swapimage').each(function(el){
	
				if (!$defined(el.get('src_swap')) || !el.get('src_swap').test('\.(jpg|gif)$') )
					el.set('src_swap', el.get('src').substitute({'.jpg':'-swap.jpg', '.gif': '-swap.gif'}, (/(\.(jpg|gif))$/g)));
				
				var src = el.get('src');
				var src_swap = el.get('src_swap');
				new Asset.images(el.get('src_swap'), {
					onComplete:(function(){
						el.addEvents({
							mouseenter:function(){
								el.set('src', src_swap);
							},
							mouseleave:function(){
								el.set('src', src);
							}
						});
					}).bind(el)
				});
				
			});
		
		
	}
});