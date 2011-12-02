/**
 * @author Peter Hellberg <peter@c7.se>
 * @copyright 2007 Code7 Interactive
 * @package MarkdownToolbar
 * @license MIT
 * @url http://codeseven.org/projects/control_textarea/
 * @version 0.0.1
 */

var MarkdownToolbar = {
	
  id:       'markdown_toolbar',
	language: 'en',
	element:  null,

	add: function(element, language){
		
		this.element = $(element);
		
		if(this.element) {
			
			/* Set the language */
			if(language) {
				this.language = language;
			}
			
			/* Create the toolbar element */
			var toolbar =	new Element('ul', {'id': this.id});
			
			/* Add the buttons to the toolbar */
			toolbar.adopt(this.buttons());

			/* Inject the toolbar before the textarea */
			toolbar.injectBefore(this.element);
		}
	},

/* Control.TextArea code */	
	doOnChange: function(event){
		if(this.onChangeTimeout)
			window.clearTimeout(this.onChangeTimeout);
		this.onChangeTimeout = window.setTimeout(function(){
			if(this.notify)
				this.notify('change',this.getValue());
		}.bind(this),this.onChangeTimeoutLength);
	},

	getValue: function(){
		return this.element.value;
	},
	
	getSelection: function(){
		if(!!document.selection)
			return document.selection.createRange().text;
		else if(!!this.element.setSelectionRange)
			return this.element.value.substring(this.element.selectionStart,this.element.selectionEnd);
		else
			return false;
	},
	
	replaceSelection: function(text){
		if(!!document.selection){
			this.element.focus();
			var old = document.selection.createRange().text;
			var range = document.selection.createRange();
			if(old == '')
				this.element.innerHTML += text;
			else{
				range.text = text;
				range -= old.length - text.length;
			}
		}else if(!!this.element.setSelectionRange){
			var selection_start = this.element.selectionStart;
			this.element.value = this.element.value.substring(0,selection_start) + text + this.element.value.substring(this.element.selectionEnd);
			this.element.setSelectionRange(selection_start + text.length,selection_start + text.length);
		}
		this.doOnChange();
		this.element.focus();
	},
	wrapSelection: function(before,after){
		this.replaceSelection(before + this.getSelection() + after);
	},
	insertBeforeSelection: function(text){
		this.replaceSelection(text + this.getSelection());
	},
	insertAfterSelection: function(text){
		this.replaceSelection(this.getSelection() + text);
	},
	
	/* Uses $A, thus not compatible with mootools.. fix */
	injectEachSelectedLine: function(callback,before,after){
		this.replaceSelection((before || '') + $A(this.getSelection().split("\n")).injectInside([],callback).join("\n") + (after || ''));
	},
	
	insertBeforeEachSelectedLine: function(text,before,after){
		this.injectEachSelectedLine(function(lines,line){
			lines.push(text + line);
			return lines;
		},before,after);
	},
/* END Control.TextArea code */

	buttons: function() {
	
		var buttonList = new Array(
			['italics',	
				$H({sv: 'Kursiv',en: 'Italic'}),
				function(){ MarkdownToolbar.wrapSelection('*','*'); }
			],
			['bold',
				$H({sv: 'Fetstil',en: 'Bold'}),
				function(){ MarkdownToolbar.wrapSelection('**','**'); }
			],
			['link',
				$H({sv: 'Länk',en: 'Link'}),
				function(){
					
					var txt = $H({
						sv: $H({enterurl: 'Skriv in adressen', linktext: 'Länktext'}),
						en: $H({enterurl: 'Enter Link URL', linktext: 'Link Text'})
					});
					
					str = txt.get(MarkdownToolbar.language);

					var selection = MarkdownToolbar.getSelection();
					var response = prompt(str.get('enterurl'),'');
					
					if(response == null) { return; }
					
					MarkdownToolbar.replaceSelection('[' + (selection == '' ? str.get('linktext') : selection) + '](' + (response == '' ? 'http://link_url/' : response).replace(/^(?!(f|ht)tps?:\/\/)/,'http://') + ')');
				}	
			],
			['image',
				new Hash({sv: 'Bild', en: 'Image'}),
				function() {
					
					var txt = $H({
						sv: $H({enter: 'Skriv in adressen till bilden', alt: 'Alternativ bildtext'}),
						en: $H({enter: 'Enter Image URL', alt: 'Image Alt Text'})
					});
					
					str = txt.get(MarkdownToolbar.language);

					var selection = MarkdownToolbar.getSelection();
					var response = prompt(str.get('enter'),'');
					
					if(response == null) { return; }

					MarkdownToolbar.replaceSelection('![' + (selection == '' ? str.get('alt') : selection) + '](' + (response == '' ? 'http://image_url/' : response).replace(/^(?!(f|ht)tps?:\/\/)/,'http://') + ')');
		
				}
			],
			['heading',
				new Hash({sv: 'Rubrik', en: 'Heading'}),
				function() {

					var txt = $H({
						sv: $H({selection: 'Rubrik'}),
						en: $H({selection: 'Heading'})
					});
					
					str = txt.get(MarkdownToolbar.language);

					var selection = MarkdownToolbar.getSelection();
					if(selection == '') {
						selection = str.get('selection');
					}
			
					var str = '';
					(Math.max(5,selection.length)).times(function(){ str += '-'; });

					MarkdownToolbar.replaceSelection(selection + "\n" + str + "\n");
				}
			],
			/* Fix these */
			/* ['unordered_list',
				new Hash({sv: 'Punktlista',en: 'Bullet list'})}],
			['ordered_list',
				new Hash({sv: 'Numrerad lista',en: 'Ordered list'})],
			['quote',	
				new Hash({sv: 'Citera',en: 'Quote'})],
			['code',
				new Hash({sv: 'Kod',en: 'Code'})], */
			['help',
				new Hash({sv: 'Hjälp',en: 'Help'}),
				function() {
					window.open('http://daringfireball.net/projects/markdown/dingus');
				}
			]
		);
		
		var buttons = new Array();

		buttonList.each(function(item, index) {

			var buttonText = item[1].get(MarkdownToolbar.language);

			buttons.include(new Element('li').adopt(
					new Element('a', {
						'id': 'markdown_'+item[0]+'_button', 
						'events': {
							'click': function(){
								item[2]();
							}
						},
						'title': buttonText}).adopt(
							new Element('span').setText(buttonText))
				)
			); 
		});

		return buttons;
	}
}
