var DatePicker = new Class({
 
 
	dom_source : null,
	datepicker : null,
	year : 0,
	month : 0,
	day : 0,
	day_of_month : 0,
	month_arr : ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"],
 
	initialize : function(){
	},
 
	focusInput : function(item) {
		this.dom_source = $(item);
		this.createDatePicker(this.dom_source.value);
	},
 
	createDatePicker : function(dateval) {
		var now = new Date();
		var today = new Date((window.ie)?now.getYear():(now.getYear()+1900), now.getMonth(), now.getDate());
		if (dateval && dateval!='noset') {
			var datearr = dateval.split("-");
			this.year = datearr[0]*1;
			this.month = datearr[1]*1 - 1;
			this.day = datearr[2]*1;
		}
		if (dateval=='') {
			this.year = (Browser.Engine.trident)?now.getYear():(now.getYear()+1900);
			this.month = now.getMonth();
			this.day = now.getDate();
		}
		if ( this.month==0 || this.month==2 || this.month==4 || this.month==6 || this.month==7 || this.month==9 || this.month==11 ) {
			this.day_of_month = 31;
		} else if ( this.month==3 || this.month==5 || this.month==8 || this.month==10 ) {
			this.day_of_month = 30;
		} else if ( ( this.year%4==0 && this.year%100!=0 ) || this.year%400==0 ) {
			this.day_of_month = 29;
		} else {
			this.day_of_month = 28;
		}
		if (!this.datepicker)	this.datepicker = new Element('div', {"id":"datepicker"});
		var htmlcontent = "<div id='datepicker_container'><table width='100%' border='0' cellpadding='0' cellspacing='0' align='center'>";
		htmlcontent += "<tr><td class='arrow' onclick='dp.flipYear(-1)'><a href='javascript:void();'>&laquo;</a></td><td class='arrow' onclick='dp.flipMonth(-1)'><a href='javascript:void();'>&#8249;</a></td><td colspan='3'>" + this.month_arr[this.month] + " " + this.year + "</td><td class='arrow' onclick='dp.flipMonth(1)'><a>&#8250;</a></td><td class='arrow' onclick='dp.flipYear(1)'><a href='javascript:void();'>&raquo;</a></td></tr>";
		htmlcontent += "<tr><td width='14%'>Sun</td><td width='14%'>Mon</td><td width='14%'>Tue</td><td width='14%'>Wed</td><td width='14%'>Thu</td><td width='14%'>Fri</td><td width='14%'>Sat</td></tr>";
		var inputday = new Date(this.year, this.month, this.day);
		var firstday = new Date(this.year, this.month, 1);
		var firstweekday = firstday.getDay();
		var lastday = new Date(this.year, this.month, this.day_of_month);
		var lastweekday = lastday.getDay();
		htmlcontent += "<tr>";
		for (var i=0; i<firstweekday; i++) {
			htmlcontent += "<td>&nbsp;</td>";
		}
		for (var i=1; i<=this.day_of_month; i++) {
			var curday = new Date(this.year, this.month, i);
			if (curday.getTime()==today.getTime()) {
				var istoday = " today";
			} else {
				var istoday = "";
			}
			if (curday.getTime()==inputday.getTime())	{
				var isinputday = " inputday";
			} else {
				var isinputday = "";
			}
			if (curday.getDay()==0 && i!=1)	{
				htmlcontent += "<tr>";
			}
			htmlcontent += "<td class='dayfield" + istoday + isinputday + "' onclick='dp.clickDay(" + this.year + ", " + this.month + ", " + i + ")' onmouseover='dp.overDay(this)' onmouseout='dp.outDay(this)'>" + i + "</td>";
			if (curday.getDay()==6)	{
				htmlcontent += "</tr>";
			}
		}
		for (var i=lastweekday; i<6; i++) {
			htmlcontent += "<td>&nbsp;</td>";
		}
		if (lastweekday!=6) {
				htmlcontent += "</tr>";
		}
		htmlcontent += "</table></div>";

		this.datepicker.set('html',htmlcontent);
		this.datepicker.setStyles({'opacity': 1, 'position': 'absolute'});
		this.datepicker.setStyle('top', this.dom_source.getCoordinates().bottom);
		this.datepicker.setStyle('left', this.dom_source.getCoordinates().left);
		this.datepicker.setStyle('z-index', 1000);
		this.datepicker.inject(document.body);
		
		this.datepicker.addEvent('mouseleave', function(){
			this.clearDatePicker();
		}.bind(this));
		
		/*
		this.window_click_event = function(){
			this.clearDatePicker();
		}.bind(this);
		
		window.addEvent('click', this.window_click_event);
		*/
	},
 
 
	clickDay : function(y, m, d) {
        var month = (m+1)+"";
        if (month.length==1) month = "0"+month;
        var day = (d+0)+"";
        if (day.length==1) day = "0"+day;
        this.dom_source.set("value", y+"-"+month+"-"+day).fireEvent("change");
        this.clearDatePicker();
    },
 
 
	overDay : function(item) {
		$(item).addClass("dayfield_over");
	},
 
 
	outDay : function(item) {
		$(item).removeClass("dayfield_over");
	},
 
 
	flipMonth : function(offset) {
		if (offset==1) {
			if (this.month==11)	{
				this.month = 0;
				this.year++;
			} else {
				this.month++;
			}
		}
		if (offset==-1) {
			if (this.month==0)	{
				this.month = 11;
				this.year--;
			} else {
				this.month--;
			}
		}
		this.createDatePicker('noset');
	},
 
	flipYear : function(offset) {
		this.year += offset;
		this.createDatePicker('noset');
	},
 
	clearDatePicker : function() {
        if (this.frame)
        	this.frame.dispose();
        this.datepicker.fade(0);
        //window.removeEvent('click', this.window_click_event);
    } 
 
});
