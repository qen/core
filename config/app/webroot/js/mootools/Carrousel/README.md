Carrousel
===========

Using this class you can simply make a Carrousel which actually goes round, instead of flipping back to the front when it reaches the end.

![Screenshot](http://www.disain.nl/userfiles/carrousel_v11.jpg)

How to use
----------
	#JS 
	//all options are optional. The here given options are the standard values
	
	document.addEvent("domready",function()
	{
		var options = {
			id:				'carrousel', 
			itemsWrapper:	'listItems',
			itemClass:		'.item',
			mode:			'horizontal',
			
			buttonPrev:		'butPrev',
			buttonNext:		'butNext',
			
			fadeIn:			true, 
			hideControls:	true,
			
			duration:		'normal'			
		};
	
		var carrousel = new Carrousel(options);
	});
	
A standard HTML setup would be:

	<div id="carrousel">
		<div id="listItems">
			<div class="item">1</div>
			<div class="item">2</div>
			<div class="item">3</div>			
		</div>
	</div>

Give your #carrousel and div.item CSS-classes a fixed width and height and you're off

Options
----------

* id: (element: defaults to 'carrousel') wrapper for [itemsWrapper]
* itemsWrapper: (element: defaults to 'listItems') wrapper for items
* itemClass: (string: defaults to '.item' ) class to apply to items
* mode: (string: defaults to 'horizontal') mode for sliding. Can also be 'vertical'
* buttonPrev: (element: defaults to 'butPrev') element to make slider go back
* buttonNext: (element: defaults to 'butNext') element to make slide go forwards
* fadeIn: (boolean: defaults to 'true') makes carrousel fade in
* hideControls: (boolean: defaults to 'true') hides [buttonPrev] and [buttonNext] when there is only 1 item in [itemsWrapper]
* duration : (string or int: defaults to 'normal') How fast will the animation go? Can also be 'short' and 'long' or an integer like '500'. 

Events
----------

* onNext: fires when butNext has been clicked
* onPrevious: fires when butPrev has been clicked
* onComplete: fires after the effect has ended (either next or previous)

Variables
----------
* currentID: (int) gives back the active element in array-form. so the first will give back: 0, second: 1, third:2, etc: available after 'complete'

Example:

	var car = new Carrousel({
		onComplete:function()
		{
			alert(this.currentID); //alerts int. eg: 2
		}
	});