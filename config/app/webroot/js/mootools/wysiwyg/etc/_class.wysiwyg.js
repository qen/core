/*
Author:
	luistar15, <leo020588 [at] gmail.com>
License:
	MIT License
 
Class
	wysiwyg (rev.06-07-08)

Arguments:
	Parameters - see Parameters below

Parameters:
	textarea: textarea dom element | default: first textarea
	klass: string | css class | default: 'wysiwyg'
	src: string | iframe src | default: 'about:blank'
	buttons: array | list editor buttons | default: ['strong','em','u','superscript','subscript',null,'left','center','right','indent','outdent',null,'h1','h2','h3','p','ul','ol',null,'img','link','unlink',null,'clean','toggle']
		null -> spacer

Methods:
	toggleView(): toggle view iframe <-> textarea and update content
	toTextarea(view): update content from iframe to textarea
		view: bolean | if is true, change view | default:false
	toEditor(view): update content from textarea to iframe
		view: bolean | if is true, change view | default:false
	exec(cmd,value): execute command on iframe document
	clean(html): return valid xhtml string

Requires:
	mootools 1.2 core
*/

var wysiwyg = new Class({

	initialize: function(params){
		this.TA = params.textarea || document.getElement('textarea');
		this.TB = new Element('div',{'class':'toolbar'});
		this.IF = new Element('iframe',{'frameborder':0,'src':(params.src||'about:blank')}).addEvent('load',function(){
				this.doc = this.IF.contentWindow.document;
				this.doc.designMode = 'on';
				this.toggleView();
			}.bind(this));
		this.CT = new Element('div',{'class':(params.klass||'wysiwyg')}).injectBefore(this.TA).adopt(this.TB,this.IF,this.TA);

		this.open = false;

		$each((params.buttons||['strong','em','u','superscript','subscript',null,'left','center','right','indent','outdent',null,'h1','h2','h3','p','ul','ol',null,'img','link','unlink',null,'clean','toggle']),function(b){
			if(!b){
				new Element('span',{'class':'spacer'}).inject(this.TB);
			}else if(Browser.Engine.trident){
				new Element('a',{'class':b,'href':'//'+b}).addEvent('click',function(e){
					var ev = new Event(e); ev.stop();
					if(b=='toggle'){
						this.toggleView();
					}else{
						this.exec(b);
					}
				}.bind(this)).inject(this.TB);
			}else{
				new Element('span',{'class':b}).addEvent('click',(b=='toggle'?this.toggleView.bind(this):this.exec.bind(this,[b]))).inject(this.TB);
			}
		},this);
	},

	toggleView: function(){
		if($try(function(){if(this.doc.body){return true;}}.bind(this))){
			if(this.open){
				this.toTextarea(true);
			}else{
				this.toEditor(true);
			}
			this.open = !this.open;
		}
	},

	toTextarea: function(view){
		this.TA.value = this.clean(this.doc.body.innerHTML);
		if(view){
			this.TA.removeClass('hidden');
			this.IF.addClass('hidden');
			this.TB.addClass('disabled');
			this.TA.focus();
		}
	},

	toEditor: function(view){
		var val = this.TA.value.trim();
		this.doc.body.innerHTML = val==''?'<p>&nbsp;</p>':val;
		if(view){
			this.TA.addClass('hidden');
			this.IF.removeClass('hidden');
			this.TB.removeClass('disabled');
		}
	},

    exec: function(b,v){
		if(this.open){
			this.IF.contentWindow.focus();
			but = _BUTTONS[b];
			var val = v || but[1];
			if(!v && but[2]){
				if(!(val=prompt(but[1],but[2]))){return;}
			}
			this.doc.execCommand(but[0],false,val);
		}
    },

	clean: function(html){
		return html
		.replace(/\s{2,}/g,' ')
		.replace(/^\s+|\s+$/g,'')
		.replace(/\n/g,'')
		.replace(/<[^> ]*/g,function(s){return s.toLowerCase()})
		.replace(/<[^>]*>/g,function(s){s=s.replace(/ [^=]+=/g,function(a){return a.toLowerCase()});return s})
		.replace(/<[^>]*>/g,function(s){s=s.replace(/( [^=]+=)([^"][^ >]*)/g,"$1\"$2\"");return s})
		.replace(/<[^>]*>/g,function(s){s=s.replace(/ ([^=]+)="[^"]*"/g,function(a,b){if(b=='alt'||b=='href'||b=='src'||b=='title'||b=='style'){return a}return''});return s})
		.replace(/<b(\s+|>)/g,"<strong$1")
		.replace(/<\/b(\s+|>)/g,"</strong$1")
		.replace(/<i(\s+|>)/g,"<em$1")
		.replace(/<\/i(\s+|>)/g,"</em$1")
		.replace(/<span style="font-weight: normal;">(.+?)<\/span>/gm,'$1')
		.replace(/<span style="font-weight: bold;">(.+?)<\/span>/gm,'<strong>$1</strong>')
		.replace(/<span style="font-style: italic;">(.+?)<\/span>/gm,'<em>$1</em>')
		.replace(/<span style="(font-weight: bold; ?|font-style: italic; ?){2}">(.+?)<\/span>/gm,'<strong><em>$2</em></strong>')
		.replace(/<img src="([^">]*)">/g,'<img alt="Image" src="$1" />')
		.replace(/(<img [^>]+[^\/])>/g,"$1 />")
		.replace(/<u>(.+?)<\/u>/gm,'<span style="text-decoration: underline;">$1</span>')
		.replace(/<font[^>]*?>(.+?)<\/font>/gm,'$1')
		.replace(/<font>|<\/font>/gm,'')
		.replace(/<br>\s*<\/(h1|h2|h3|h4|h5|h6|li|p)/g,'</$1')
		.replace(/<br>/g,'<br />')
		.replace(/<(table|tbody|tr|td|th)[^>]*>/g,'<$1>')
		.replace(/<\?xml[^>]*>/g,'')
		.replace(/<[^ >]+:[^>]*>/g,'')
		.replace(/<\/[^ >]+:[^>]*>/g,'')
		.replace(/(<[^\/]>|<[^\/][^>]*[^\/]>)\s*<\/[^>]*>/g,'');
	}
});

var _BUTTONS = {
	strong: ['bold',null],
	em: ['italic',null],
	u: ['underline',null],
	superscript: ['superscript',null],
	subscript: ['subscript',null],
	left: ['justifyleft',null],
	center: ['justifycenter',null],
	right: ['justifyright',null],
	indent: ['indent',null],
	outdent: ['outdent',null],
	h1: ['formatblock','<H1>'],
	h2: ['formatblock','<H2>'],
	h3: ['formatblock','<H3>'],
	p: ['formatblock','<P>'],
	ul: ['insertunorderedlist',null],
	ol: ['insertorderedlist',null],
	link: ['createlink','Ingrese la URL:','http://'],
	unlink: ['unlink',null],
	img: ['insertimage','Ingrese la URL de la imagen:','http://'],
	clean: ['removeformat',null],
	toggle: ['toggleview']
};