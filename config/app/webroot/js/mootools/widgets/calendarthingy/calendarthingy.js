var CalendarThingy = new Class({

	Implements: [Events, Options],
	
	options: {
		weekdays: 'short',
		months: 'short',
		date_initialize: function(){},
		date_mouseleave: function(){},
		date_mouseenter: function(){},
		date_mouseclick: function(){}
	},
	
	dateSelect: function(){
	},
	
	el: $empty,
	canvas: $empty,
	canvas_f: $empty,
	date: $empty,
	curdate: $empty,
	today: $empty,
	
	weekdays: [
		{short: "Sun", full: "Sunday"},
		{short: "Mon", full: "Monday"},
		{short: "Tue", full: "Tuesday"},
		{short: "Wed", full: "Wednesday"},
		{short: "Thu", full: "Thursday"},
		{short: "Fri", full: "Friday"},
		{short: "Sat", full: "Saturday"}
	],
	months: [
		{short: "Jan", full: "January"},
		{short: "Feb", full: "February"},
		{short: "Mar", full: "March"},
		{short: "Apr", full: "April"},
		{short: "May", full: "May"},
		{short: "Jun", full: "June"},
		{short: "Jul", full: "July"},
		{short: "Aug", full: "August"},
		{short: "Sep", full: "September"},
		{short: "Oct", full: "October"},
		{short: "Nov", full: "November"},
		{short: "Dec", full: "December"}
	],

	initialize: function(el, options){
		this.setOptions(options);
		this.el = $(el);
		this.today = new Date();
		this.date =  new Date();

		// create container
		this.canvas = new Element('div', {
			'class': 'calendar',
			'styles': {
				float: 'left',
				overflow: 'hidden'
			}
		});
		
		this.canvas.inject(this.el);
		
		this.el.adopt(this.canvas);

		this.canvas.empty();
		
		
		/////////////////////////////////////////////////////////////////////////////
		// create navigation
		/////////////////////////////////////////////////////////////////////////////
		var ul = new Element('ul', {
			'class': 'nav',
			'styles': {
				'float': 'left',
				'clear': 'both',
				'list-style': 'none',
				'margin':'0px',
				'padding':'0px'
			}
		});

		
		//prev year
		new Element('li', {
			'class': 'prevYear',
			'html': "&laquo;",
			'styles': {
				'float': 'left',
				'cursor': 'pointer'
			},
			'events':{
				'click': function(e){
					e.stop();
					this.prevYear();
				}.bind(this)
			}
		}).inject(ul);
		
		//prev month
		new Element('li', {
			'class': 'prevMonth',
			'html': "&#8249;",
			'styles': {
				'float': 'left',
				'cursor': 'pointer'
			},
			'events':{
				'click': function(e){
					e.stop();
					this.prevMonth();
				}.bind(this)
			}
		}).inject(ul);
		
		//display current month
		this.displayMonthYear = new Element('li', {
			'class': 'displayMonthYear',
			'html': '',
			'styles': {
				'float': 'left'
			}
		}).inject(ul);
		
		//next month
		new Element('li', {
			'class': 'nextMonth',
			'html': "&#8250;",
			'styles': {
				'float': 'left',
				'cursor': 'pointer'
			},
			'events':{
				'click': function(e){
					e.stop();
					this.nextMonth();
				}.bind(this)
			}
		}).inject(ul);
		//next year
		new Element('li', {
			'class': 'nextYear',
			'html': "&raquo;",
			'styles': {
				'float': 'left',
				'cursor': 'pointer'
			},
			'events':{
				'click': function(e){
					e.stop();
					this.nextYear();
				}.bind(this)
			}
		}).inject(ul);
		
		this.canvas.adopt(ul);
		
		
		/////////////////////////////////////////////////////////////////////////////
		// create weekdays 
		/////////////////////////////////////////////////////////////////////////////
		var ul = new Element('ul', {
			'class': 'weekdays days',
			'styles': {
				'float': 'left',
				//'clear': 'both',
				'list-style': 'none',
				'margin':'0px',
				'padding':'0px'
			}
		});
		
		this.weekdays.each(function(day){
			new Element('li', {
				'class': day.full,
				'html': day[this.options.weekdays],
				'styles': {
					'float': 'left'
				}
			}).inject(ul);
		}.bind(this));
		
		this.canvas.adopt(ul);
		
		
		/////////////////////////////////////////////////////////////////////////////
		// create calendar footer 
		/////////////////////////////////////////////////////////////////////////////
		
		this.canvas_f = new Element('div', {

			'class': 'todayis',
			'html': this.today.toDateString(),
			'styles': {
				'clear': 'both',
				'cursor': 'pointer',
				'display': 'block'
			},
			'events': {
				'click': function(){
					this.date = new Date(this.today);
					this.build();
				}.bind(this)
			}
		});
		
		this.canvas.adopt(this.canvas_f);
		
		new Element('br', {styles:{'clear':'both'}}).inject(this.el);
		
	},
	build: function(pdate){

		if ($type(pdate) != 'date') pdate = this.date;
		
		this.displayMonthYear.set('html', this.months[pdate.getMonth()][this.options.months] + " " + pdate.getFullYear());

		/////////////////////////////////////////////////////////////////////////////
		// create dates 
		/////////////////////////////////////////////////////////////////////////////
		var d = this.dateslice(pdate);
		var week = 1;
		
		// initialize start date
		var curr = new Date();
		curr.setDate(1);
		curr.setMonth(pdate.getMonth());
		curr.setFullYear(pdate.getFullYear());

		//increment week by 1 if getDay is equal or greater than 5
		//if (curr.getDay() < 5) week++;
		//console.debug(curr.toDateString() + " | " + pdate.toDateString() + ' > ' + (d.month-1) + ' > ' + ((curr.getDay()-1)*-1-1));
		// make sure that start date is saturday, because the initial loop automatically increments the date by 1 making it the sunday first day of the week
		curr.setDate(((curr.getDay()-1)*-1-1));

		this.canvas.getElements('ul.dates').destroy();
		
		while(week <= 6){
			//if ((this.date.getFullYear()+this.date.getMonth()) < (curr.getFullYear()+curr.getMonth())) break;

			var ul = new Element('ul', {
				'class': 'weekdays dates',
				'styles': {
					'float': 'left',
					'list-style': 'none',
					'margin':'0px',
					'padding':'0px',
					'position': 'relative'
				}
			});
			
			this.weekdays.each(function(day){
				
				curr.setDate(curr.getDate() + 1);

				var a = this.dateslice(curr);
				//console.debug(day.name + " "+ a.month + " " + a.date + " ["+ curr.getDay() +"] | " + curr + " | " + this.today);
				
				var li = new Element('li', {
					'class': day.full+' date'+(curr.toDateString() == this.today.toDateString()? ' today':'')+(a.month != d.month? ' xmonth':'')+(curr.toDateString() == this.date.toDateString()? ' selected':''),
					'html': a.date,
					'styles': {
						'float': 'left',
						'overflow': 'hidden'
					}
				});
				
				var curdate = new Date();
				curdate.setFullYear(a.year);
				curdate.setMonth(a.month-1);
				curdate.setDate(a.date);
				
				$try(function(){
					this.options.date_initialize.run([li, a], this);
				}.bind(this));
				
				li.addEvents({
					mouseleave: function(){
						$try(function(){
							this.options.date_mouseleave.run([li, curdate], this);
						}.bind(this));
					}.bind(this),
					
					mouseenter: function(){
						
						$try(function(){
							this.options.date_mouseenter.run([li, curdate], this);
						}.bind(this));
					}.bind(this),
					
					click: function(){
						
						this.date = new Date();
						this.date.setFullYear(a.year);
						this.date.setMonth(a.month-1);
						this.date.setDate(a.date);
						
						this.canvas.getElements('ul.dates li').removeClass('selected');
						li.addClass('selected');

						$try(function(){
							this.options.date_mouseclick.run([li, curdate], this);
						}.bind(this));
						
						this.fireEvent('dateSelect', [li, curdate]);
						
						if (d.month != a.month) this.build();

					}.bind(this)
				});
				
				li.inject(ul);

			}.bind(this));
			
			//this.canvas.adopt(ul);
			ul.inject(this.canvas_f, 'before');

			week++;
		}//end while
		
	},
	
	dateslice: function(date){
		return {
			'year': date.getFullYear(),
			'month': date.getMonth()+1,
			'date': date.getDate()
		};
	},
	
	nextMonth: function(){
		this.date.setDate(1);
		this.date = new Date(this.date.setMonth(this.date.getMonth()+1));
		this.build(this.date);
	},
	
	prevMonth: function(){
		this.date.setDate(1);
		this.date = new Date(this.date.setMonth(this.date.getMonth()-1));
		this.build(this.date);
	},
	
	nextYear: function(){
		this.date.setDate(1);
		this.date = new Date(this.date.setFullYear(this.date.getFullYear()+1));
		this.build(this.date);
	},
	
	prevYear: function(){
		this.date.setDate(1);
		this.date = new Date(this.date.setFullYear(this.date.getFullYear()-1));
		this.build(this.date);
	}

});

CalendarThingy.extend({
	popup: $empty,
	
	createPopUp: function(el, options){
		
		// create container
		var poopup = new Element('div', {
			'id': 'poopUpCalendar',
			'styles': {
				'position': 'absolute',
				'top': '0px',
				'left': '0px',
				'opacity': 0
				//'display': 'none'
			}
		});
		poopup.isShown = false;
		poopup.selDate = new Date($(el).get('value'));
		if ($type(poopup.selDate) != 'date') poopup.selDate = new Date();
		if ( poopup.selDate.toDateString() == "Invalid Date" || poopup.selDate.toDateString() == 'NaN' ) poopup.selDate = new Date();
		
		poopup.set('morph',{
			'duration': 'short',
			'link': 'ignore',
			'onStart': function(){
				poopup.isShown = !poopup.isShown;
			},
			'onComplete': function(){
			}
		});
		
		poopup.inject(document.body);
		
		options.onDateSelect = function(el, curdate){
			poopup.selDate = curdate;
			poopup.fireEvent('close');
		};
		
		var cal = new CalendarThingy(poopup, options);
		var coord = el.getCoordinates();
		var calclose = function(){ poopup.fireEvent('close'); };
		cal.date = poopup.selDate || new Date();
		
		el.addEvents({
			'click': function(e){
				e.stop();
				poopup.fireEvent('open');
			},
			'blur': calclose
		});
		
		poopup.addEvents({
			'mouseenter': function(){
				el.removeEvent('blur', calclose);
			},
			'mouseleave': function(){
				this.fireEvent('close');
				el.addEvent('blur', calclose);
			},
			'close': function(){
				if (!poopup.isShown) return;
				//poopup.setStyles({'display': 'none'});
				//poopup.isShown = false;
				poopup.morph({opacity: [1,0]});
			},
			'open': function(){
				if (poopup.isShown) return;
				
				cal.build(poopup.selDate);
				poopup.setStyles({
					'top': (coord.top+coord.height)+'px',
					'left': coord.left+'px'
					//,'display': 'block'
				});
				//poopup.isShown = true;
				poopup.morph({'opacity': [0,1]});

			}
		});
		
		
	}
});