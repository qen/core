var sortable_searches = {

	requestQue: new Request.Queue({	onComplete: function(name, instance, text, xml){}	}),
	roar: new Roar({ position: 'upperLeft',	duration: 2400	}),
	
	create: function(params){

		var Settings = {
			sortable: null,
			container: null,
			requestSave: null,
			requestSearch: null,		
			url_search: params.url_search,
			url_save: params.url_save,
			timer: 0
		};
		
		Settings.sortable = new Sortables($$(params.container+' ul.sortable_lists'),{
			clone: true,
			opacity: .2,
			revert: { duration: 500, transition: 'elastic:out' },
			onStart: function(){
				$clear(Settings.timer);
			},
			onComplete: function(el) {
				
				this.serialize(false, function(el, idx){return el;}).each(function(list, idx){
	
					if (idx == 0) {
						build = new Array();
						list.each(function(f, s){
							build.push(f.get('rel'));
						});
	
						$clear(Settings.timer);
						
						Settings.timer = (function(){
							Settings.requestSave.post(params.buildpost(build));
						}).delay(1800);
						
						return;
					}
	
				});
				
			}
		});
		
		
		Settings.container = $$(params.container+' ul.search_result')[0];	
	
		Settings.requestSave = new Request.JSON({
			url: Settings.url_save, 
			onComplete: function(data){
				sortable_searches.roar.alert(params.messages.saved[0], params.messages.saved[1]);
			}
		});
		
		sortable_searches.requestQue.addRequest(params.container+'.save', Settings.requestSave);
	
		Settings.requestSearch = new Request.JSON({
			url: Settings.url_search, 
			
			onComplete: function(data){
				$$(params.container+' ul.search_result').removeClass('loadergif');

				if (!data) return;
				if (!data[params.varname]) {
					sortable_searches.roar.alert(params.messages.emptysearch[0], params.messages.emptysearch[1]);
					return;
				}//end if
				
				params.searches(data, Settings.container, Settings.sortable);
			
			}
			
		});
		
		sortable_searches.requestQue.addRequest(params.container+'.search', Settings.requestSearch);
		
		$$(params.container+' input[value="Search"]')[0].addEvent('click', function(){
			$$(params.container+' ul.search_result').addClass('loadergif');
			$$(params.container+' ul.search_result li').destroy();
			
			Settings.sortable.removeItems($$(params.container+' ul.search_result li')).destroy();
			
			var query = $$(params.container+' input[name="q"]')[0].value;
			(function(){
				Settings.requestSearch.get({'r':'json', 'p': Settings.page, 'q':query});
			}).delay(2400);
		});	
		
	}
	
};
/***
EXAMPLE:

	sortable_searches.create({
		container	: 'div.properties',
		varname		: 'properties',
		url_search	: neighborhood.properties.url_search,
		url_save	: neighborhood.properties.url_save,
		buildpost	: function(build){
			return { 'properties' : build }
		},
		messages 	: {
			saved 		: ['pssstt!', 'Properties saved.'],
			emptysearch	: ['pssstt!', 'hmmm -_- <br> No properties found. try again']
		},
		searches	: function(data, container, sortable){
			
			data.properties.each(function(property){
				var rid = 'propertyid_'+property.propertyid;
				
				if ($type($(rid)) == 'element') return;

				var li = new Element('li', {
					html: property.prop_name,
					styles: {
						'background-image': 'url("<!--{$smarty.const.WEBROOT}-->images/property/'+property.propertyid+'?icon")',
						'background-position':'left top',
						'background-repeat':'no-repeat'
					},
					rel: property.propertyid,
					id: rid
				});
	
				container.adopt(li);
				sortable.addItems(li);
			});
		
		}
	});
	
HTML STRUCTURE:

	<div class="fL properties" style="background-color:#DCE8EB; margin-right:5px; padding:5px; width:350px">
		<ul class="sortable_lists" style="border:0px solid #27333A; padding:10px; background-color:#fff;">
		
			<li rel="[[ID]]" id="propertyid_[[ID]]">
			[[NAME]]
			</li>
			
		</ul>
	</div>
	
	
	<div class="fL properties" style="background-color:#efefef; margin-right:10px; padding:5px; width:370px">
		<h5>Search Properties</h5>
		<input name="q" type="text" value="" /> <input type="button" value="Search"/>
		<ul class='search_result sortable_lists' style="border:0px solid #27333A; padding:10px; margin-top:7px; background-color:#fff;">
		</ul>
	</div>	
	
****/