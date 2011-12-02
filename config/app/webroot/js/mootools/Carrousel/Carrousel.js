/*
---
description: A carrousel class that makes the carrousel actually GO ROUND, insead of flipping back to the front when it reaches the end.
 
license:
 - MIT-style

authors:
  - Doeke Norg (doeke@disain.nl)

version: 1.1

requires:
  core/1.3: '*'

provides:
 - Carrousel
...
*/

var Carrousel = new Class(
{
	
	Implements: [Events, Options],
	
	options: 
	{
		id:				'carrousel', // element which contains [itemsWrapper]
		itemsWrapper:	'listItems', //element which contains the items with [itemClass] 
		itemClass:		'.item', //selector for [items] within [itemsWrapper]
		mode:			'horizontal', //speaks for itself
		
		buttonPrev:		'butPrev', //element that fires previous function
		buttonNext:		'butNext', //element that fires next function
		
		fadeIn:			true, //fade [itemsWrapper] in ; or not
		hideControls:	true,  //hide [buttonPrev] and [buttonNext] if there is just one item
		
		duration:       'normal' //'short', 'normal', 'long' or integer
	},
	
	items:				new Array(),
	itemDimensions:		null,
	clone: 				null,
	offset: 			{top: null, left: null},
	wait:				false,	
	FX:					null,
	currentID:			0,
	
	initialize: function(options)
	{
		this.setOptions(options);
		this.getItems();
		
		
		this.itemDimensions = this.items[0].getSize();
				
		if(this.items.length > 1)
		{
			if(this.options.mode == "vertical")
			{
				this.offset.top = this.itemDimensions.y*-1;
			}
			else
			{
				this.offset.left = this.itemDimensions.x*-1;
				
				this.items.each(function(el)
				{
					el.set('styles',{float: 'left'} );
				});
				document.id(this.options.itemsWrapper).set('styles',{width: (this.items.length+1)*this.itemDimensions.x} );
			}
			

			this.clone = this.items[this.items.length-1].clone();
			this.clone.inject(document.id(this.options.itemsWrapper),'top');
			this.getItems(); //refresh list;
			
			document.id(this.options.id).set('styles',{position:'relative'} );
			document.id(this.options.itemsWrapper).set('styles', {position: 'absolute', left: this.offset.left, top:this.offset.top});

      try {
        document.id(this.options.buttonNext).addEvent('click',(this.next).bind(this));
      } catch (e) {};

			try {
        document.id(this.options.buttonPrev).addEvent('click',(this.previous).bind(this));
      } catch (e) {};
		}
		else
		{
			if(this.options.hideControls)
			{
        try {
          document.id(this.options.buttonPrev).fade('hide');
        } catch (e) {};
        try {
          document.id(this.options.buttonNext).fade('hide');
        } catch (e) {};
			}			
		}
		
		this[this.options.mode]();

		if(this.options.fadeIn) document.id(this.options.id).fade('hide').fade('in');
	},
	
	horizontal: function()
	{
		this.layout = 'margin-left';
		this.offsetMargin = '-'+this.itemDimensions.x+'px';
	},
	vertical: function()
	{
		this.layout = 'margin-top';
		this.offsetMargin = '-'+this.itemDimensions.y+'px';
	},
	
	getItems: function()
	{
		this.items = document.id(this.options.id).getElements(this.options.itemClass);
		//this.items.length;
	},
	
	setCurrent:function(mode)
	{
		switch(mode)
		{
			case '+':
				if(this.currentID+1 == this.items.length - 1) this.currentID = 0;
				else this.currentID++;
				break;
			case '-':
				if(this.currentID == 0) this.currentID = this.items.length - 2;
				else this.currentID--;
				break;
			default:
				return;
				break;
				
		}
	},
	
	previous: function()
	{
		if(!this.wait)
		{
			this.wait = true;
			this.setCurrent("-");
			
			this.items[this.items.length-1].destroy(); //remove last item
			this.clone = this.items[this.items.length-2].clone(); 		
			this.clone.setStyle(this.layout,this.offsetMargin);
			this.clone.inject(document.id(this.options.itemsWrapper),'top');
			
			this.fireEvent('previous');
			
			this.FX = new Fx.Tween(this.clone,{
				duration:this.options.duration,
				onComplete:(function()
				{
					this.wait = false;
					this.getItems();
					this.fireEvent('complete');
				}).bind(this)
			}).start(this.layout,0);

		}
	},
	
	next: function()
	{
		if(!this.wait)
		{
			this.wait = true;
			this.setCurrent("+");

			this.FX = new Fx.Tween(this.items[0],{
				duration:this.options.duration,
				onComplete:(function()
				{
					this.items[0].destroy();
					
					this.clone = this.items[1].clone();
					this.clone.inject(document.id(this.options.itemsWrapper));
					this.getItems(); //refresh itemslist
					this.wait = false;
					this.fireEvent('complete');
				}).bind(this)			
			}).start(this.layout,this.offsetMargin);
			
			this.fireEvent('next');
		}
	}
});