/**
 * An alpha transparent png support library for Internet Explore (other browsers
 * support this functionality natively) now compatible with Mootools.
 *
 * @author Toby Miller <tmiller@tobymiller.com>
 * @author Keith Baker <god.dreams@gmail.com>
 * @copyright Copyright (C) 2008, Toby Miller
 * @license MIT
 *
 * to remove flash of unstyled content for images (not background):
 * add to header:
 *	<!--[if lt IE 7]>
 *		<style type="text/css" media="all">
 *			img{filter:alpha(opacity=0);}
 *		</style>
 *	<![endif]-->
 * add to footer:
 *	<!--[if lt IE 7]>
 *       <script type="text/javascript">
 *           var ap = new AlphaPng();
 *      </script>
 *  <![endif]-->
 *
 * TODO: override 	setOpacity and getStyle to apply effects to parent
 * element to allow for fade effects.
*/
var AlphaPng = new Class({
	/**
	 * defaultOptions
	 * Options used in typical implementations
	 *
	 * debug:			0 = production, 1 = development
	 * clearImage:		url location of a transparent image
	 * backgroundTags:	allowable html tags for alpha transparent backgrounds
	 */
	defaultOptions: {
		'debug':			0,
		'clearImage':		'/mootools/alpha/img/spacer.gif',
		'backgroundTags':	['div', 'table', 'td', 'a']
	},
 
	/**
	 * initialize
	 * Initialize an instance of the AlphaPng object
	 *
	 * @param mixed array or object representation of options
	 * @return void
	 */
	initialize: function(options) {
		if (window.ie6)
		{
			// Merges the default options with the ones given as parameters
			this.setOptions($merge(this.defaultOptions, options));
 
			// Execute
			this.fixElements();
		}
	},
 
	/**
	 * fixImages
	 * Fixes foreground images that are using alpha transparent pngs
	 * @param void
	 * @return void
	 */
	fixElements: function()
	{
		if (window.ie6)
		{
			 /**
			 * fixBackgrounds
			 * Fixes background images that are using alpha transparent pngs
			 *
			 * @param void
			 * @return void
			 */
			var rpng = new RegExp('url\\(([\.a-zA-Z0-9_/:-]+\.png)\\)');
			var rgif = new RegExp('url\\((/spacer\.gif$/i)\\)');
			// Background Images (found in Stylesheets)
			for (var i = 0; i < document.styleSheets.length; i++){
				for (var j = 0; j < document.styleSheets[i].rules.length; j++){
					var cssstyle = document.styleSheets[i].rules[j].style;
					var bgimage = cssstyle.backgroundImage.replace(rpng, '$1');
					var spacer = cssstyle.backgroundImage.replace(rgif, '$1');
					var position = cssstyle.position;
					if (bgimage && bgimage.match(/\.png/i)){
						if (bgimage.match(/\.\./)){
							var a = bgimage.substring(bgimage.lastIndexOf(/^.*\//),bgimage.match(/\.png/i).length + 2)
							var bgimage = bgimage.substring(bgimage.indexOf(a) + 3,bgimage.length)
						}
						cssstyle.position = (position == 'static') ? 'relative' : position;
						cssstyle.filter = 'progid:DXImageTransform.Microsoft.AlphaImageLoader(enabled=\'true\', src=\'' + bgimage + '\', sizingMethod=\'crop\')';
						cssstyle.backgroundImage = 'url(\'' + this.options.clearImage + '\')';
					}
					else if(!spacer){
						cssstyle.filter = 'filter:alpha(opacity=1)';
					}
				}
			}
 
			// Background Images (found in HTML)
			$ES(this.options.backgroundTags.join(',')).each(function(tag){
				var rpng = new RegExp('url\\(([a-zA-Z0-9_/:-]+\.png)\\)');
				var bgimage = tag.getStyle('background-image').replace(rpng, '$1');
				var position = tag.getStyle('position');
				if (bgimage && bgimage.match(/\.png/i)){
					tag.setStyles({
						'position': (position == 'static') ? 'relative' : position,
						'filter': 'progid:DXImageTransform.Microsoft.AlphaImageLoader(enabled=\'true\', src=\'' + bgimage + '\', sizingMethod=\'scale\')',
						'background-image': 'url(\'' + this.options.clearImage + '\')'
					});
				}
			}.bind(this));
 
			// Foreground Images
			// Inputs need height/width as a style to keep validation
			// if src$=png, src==spacer.gif && filter src==img src.png
			$$('[src$=.png]').each(function(img){
				var size = img.getCoordinates();
				img.setStyles({
					'filter':	"progid:DXImageTransform.Microsoft.AlphaImageLoader(enabled=\'true\', src=\'" + img.getProperty('src') + "\', sizingMethod=\'scale\')",
					'width':	size.width,
					'height':	size.height
				});
				//var span = new Element('span',{'class': 'ie6opacity', 'styles': {'background': 'none', 'display': 'inline', 'margin': 0, 'padding': 0, 'position': 'static'}}).injectBefore(img);
				//img.setProperty('src', this.options.clearImage).injectInside(span);
				img.setProperty('src', this.options.clearImage);
			}.bind(this));
 
			//if src is not spacer, set opacity to 1
			$$('[src]').each(function(el){
				if(!el.getAttribute('src').match(/spacer\.gif$/i)) el.setStyles({'filter': 'alpha(opacity=100)'});
				//else if(el.getAttribute('src').match(/spacer\.gif$/i)) el.setStyle('visibility', 'visible');//so far this sets everything to black except on hover.
			}.bind(this));
		}
	}
});
// Adds options management support
AlphaPng.implement(new Events, new Options);
window.addEvent('domready', function(){
	if (window.ie6) var ap = new AlphaPng();
});