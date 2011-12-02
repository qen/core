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
    this.TB = new Element('div',{
      'class':'toolbar'
    });
    
    this.IF = new Element('iframe',{
      'frameborder':0,
      'src':(params.src||'wysiwyg/canvas')
    }).addEvent('load',function(){
      this.doc = this.IF.contentWindow.document;
      this.doc.designMode = 'on';
      this.toggleView();
    }.bind(this));

    this.CT = new Element('div',{
      'class':(params.klass||'wysiwyg')
    }).injectBefore(this.TA).adopt(this.TB,this.IF,this.TA);

    this.open = false;
    this.UPLOADER_DIV = false;
    this.UPLOADER_IFRAME = false;

    try {
      this.TA.getParent('form').addEvent('submit', function(){
        this.TA.value = this.clean(this.doc.body.innerHTML);
      }.bind(this));
    } catch (e) {}

    //$('imgs').getChildren().each(function(el){el.addEvent('click',W3.exec.bind(W3,['img',el.src]));});
    
    $each((params.buttons||['strong','em','u','superscript','subscript',null,'left','center','right','indent','outdent',null,'h1','h2','h3','p','ul','ol',null,'img','upload','link','unlink',null,'clean','toggle']),function(b){
      if(!b){
        new Element('span',{
          'class':'spacer'
        }).inject(this.TB);
        return true;
      }//end if

      if (b == 'upload') {
        this.UPLOADER_IFRAME = new IFrame({
          'id': 'wysiwyg_uploader',
          'name' : 'wysiwyg_uploader',
          'class': 'wysiwyg_uploader',
          'frameborder': '0',
          'scrolling': 'no',
          'scrollbar' : 'no',
          //'src' : 'data:text/html;,%3Chtml%3E%0A%3Chead%3E%3C/head%3E%0A%3Cbody%20scroll=%22no%22%20style=%22margin:0px;%20padding:0px;%22%3E%0A%3Cform%20action=%22'+(params.upload||'wysiwyg/upload')+'%22%20method=%22post%22%20enctype=%22multipart/form-data%22%3E%0A%3Cdiv%20style=%22background:#efefef;%20padding:%205px;%20text-align:right;%22%3E%3Cinput%20type=%22file%22%20name=%22file%22%20id=%22fileInput%22/%3E%20%3Cinput%20type=%22submit%22%20value=%22Upload%20Image%22%20/%3E%3C/div%3E%0A%3C/form%3E%0A%3C/body%3E%0A%3C/html%3E',
          'src' : 'javascript:false;',
          'styles' : {
            'width': '0px',
            'height': '0px',
            'overflow': 'hidden',
            'display': 'none'
          },
          'events' : {
            'load': function(){
              if (!this.UPLOADER_DIV.isDisplayed()) return;

              this.UPLOADER_DIV.getElement('input').value = '';

              var d = false;
              if(this.UPLOADER_IFRAME.contentDocument){
                d = this.UPLOADER_IFRAME.contentDocument;
              }else if(this.UPLOADER_IFRAME.contentWindow){
                d = this.UPLOADER_IFRAME.contentWindow.document;
              }else{
                d = window.frames[this.UPLOADER_IFRAME.get('id')].document;
              }//end if

              this.uploadImage();
              if (this.getSelectedText() != ''){
                this.doc.execCommand('createlink', false, d.body.innerHTML);
                return;
              }
              
              if (!d.body.innerHTML.test(/\.(jpg|gif|png|jpeg)$/i)) {
                alert("Error: \n"+d.body.innerHTML);
                return;
              }//end if
                
              this.exec('img', d.body.innerHTML);
              
              try {
                this.IF.contentWindow.clickImages()
              } catch (e) {}
              
            }.bind(this)
          }
        }).inject(document.body, 'bottom');
        
        this.toolbar_pos = this.TB.getCoordinates();
        this.UPLOADER_DIV = new Element('div',{
          'styles': {
            'overflow': 'hidden',
            'display': 'none',
            'position': 'absolute',
            'background': '#efefef',
            'padding': '18px 10px',
            'top': this.toolbar_pos.top+this.toolbar_pos.height,
            'left': this.toolbar_pos.left + 1,
            'width': this.toolbar_pos.width - 20,
            'height': this.toolbar_pos.height - 10,
            'border' : '1px inset #000',
            'border-width' : '1px 0'
          },
          'html' : '<span style="float:left"><a class="wysiwyg_close_button"></a> | <b></b></span><form target="wysiwyg_uploader" action="'+(params.upload||'wysiwyg/upload')+'" method="post" enctype="multipart/form-data"><div style="text-align:right;"><input type="file" name="file" id="fileInput"/> <input type="submit" value="Upload" /></div></form><br style="clear:both"/>'
          /*
          'events' : {
            'mouseleave': function(){
              if (this.UPLOADER_DIV.isDisplayed())
                this.uploadImage();
            }.bind(this)
          }
          */
        }).inject(document.body, 'bottom');
        this.UPLOADER_DIV.getElement('span a').addEvent('click', function(){
          if (this.UPLOADER_DIV.isDisplayed())
            this.uploadImage();
        }.bind(this));
      }//end if

      new Element('a',{
        'class' : b,
        'href'  :'//'+b,
        'title' : b
      }).addEvent('click',function(e){
        var ev = new Event(e);
        ev.stop();
        switch(b)
        {
          case 'toggle':
            this.toggleView();
            break;
          case 'upload':
            this.uploadImage();
            break;
          default:
            this.exec(b);
            break;
        }
      }.bind(this)).inject(this.TB);

    },this);
  },

  uploadImage: function(){
    this.UPLOADER_DIV.toggle();
    this.TB.setStyle('margin-bottom', 0);

    if (this.UPLOADER_DIV.isDisplayed()) {
      this.TB.setStyle('margin-bottom', this.UPLOADER_DIV.getDimensions().height );
      var str = 'Image upload';
      var sel = this.getSelectedText();
      if (sel != ''){
        str = 'Set a link for: <i>' + sel.substring(0, 24) + '...</i>';
      }
      this.UPLOADER_DIV.getElement('span b').set('html', str);
    }
    
  },

  toggleView: function(){
    if($try(function(){
      if(this.doc.body){
        return true;
      }
    }.bind(this))){
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
    this.doc.body.innerHTML = val==''?'':val;
    if(view){
      this.TA.addClass('hidden');
      this.IF.removeClass('hidden');
      this.TB.removeClass('disabled');
    }
    try {
      this.IF.contentWindow.clickImages()
    } catch (e) {}
  },

  exec: function(b,v){
    if(this.open){
      this.IF.contentWindow.focus();
      but = _BUTTONS[b];
      var val = v || but[1];
      if(!v && but[2]){
        if(!(val=prompt(but[1],but[2]))){
          return;
        }
      }
      if (['left', 'right', 'center', 'clean'].contains(b) ) {
        var css = b;
        if (b == 'center' || b == 'clean') css = 'none';
        if (this.IF.contentWindow.selectedImage(css)) return;
      }//end if
      this.doc.execCommand(but[0],false,val);
    }
  },

  getSelectedText : function() {
      return (this.doc.getSelection) ? window.getSelection() : this.doc.selection.createRange().text;
  },

//
//  getRng : function() {
//      var s = this.getSel();
//      if(!s) { return null; }
//      return (s.rangeCount > 0) ? s.getRangeAt(0) : s.createRange();
//  },
//
//  selRng : function(rng,s) {
//      if(window.getSelection) {
//          s.removeAllRanges();
//          s.addRange(rng);
//      } else {
//          rng.select();
//      }
//  },

  clean: function(html){
    html.replace(/\s{2,}/g,' ');
    html.replace(/^\s+|\s+$/g,'');
    html.replace(/\n/g,'');
    html.replace(/<[^> ]*/g,function(s){
      return s.toLowerCase()
    });
    html.replace(/<[^>]*>/g,function(s){
      s=s.replace(/ [^=]+=/g,function(a){
        return a.toLowerCase()
      });
      return s
    });
    html.replace(/<[^>]*>/g,function(s){
      s=s.replace(/( [^=]+=)([^"][^ >]*)/g,"$1\"$2\"");
      return s
    });
    html.replace(/<[^>]*>/g,function(s){
      s=s.replace(/ ([^=]+)="[^"]*"/g,function(a,b){
        if(b=='alt'||b=='href'||b=='src'||b=='title'||b=='style'){
          return a
        }
        return''
      });
      return s
    });
    html.replace(/<b(\s+|>)/g,"<strong$1");
    html.replace(/<\/b(\s+|>)/g,"</strong$1");
    html.replace(/<i(\s+|>)/g,"<em$1");
    html.replace(/<\/i(\s+|>)/g,"</em$1");
    html.replace(/<span style="font-weight: normal;">(.+?)<\/span>/gm,'$1');
    html.replace(/<span style="font-weight: bold;">(.+?)<\/span>/gm,'<strong>$1</strong>');
    html.replace(/<span style="font-style: italic;">(.+?)<\/span>/gm,'<em>$1</em>');
    html.replace(/<span style="(font-weight: bold; ?|font-style: italic; ?){2}">(.+?)<\/span>/gm,'<strong><em>$2</em></strong>');
    html.replace(/<img src="([^">]*)">/g,'<img alt="Image" src="$1" />');
    html.replace(/(<img [^>]+[^\/])>/g,"$1 />");
    html.replace(/<u>(.+?)<\/u>/gm,'<span style="text-decoration: underline;">$1</span>');
    html.replace(/<font[^>]*?>(.+?)<\/font>/gm,'$1');
    html.replace(/<font>|<\/font>/gm,'');
    html.replace(/<br>\s*<\/(h1|h2|h3|h4|h5|h6|li|p)/g,'</$1');
    html.replace(/<br>/g,'<br />');
    html.replace(/<(table|tbody|tr|td|th)[^>]*>/g,'<$1>');
    html.replace(/<\?xml[^>]*>/g,'');
    html.replace(/<[^ >]+:[^>]*>/g,'');
    html.replace(/<\/[^ >]+:[^>]*>/g,'');
    html.replace(/(<[^\/]>|<[^\/][^>]*[^\/]>)\s*<\/[^>]*>/g,'');

    return html;
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
  link: ['createlink','Insert link URL:','http://'],
  unlink: ['unlink',null],
  img: ['insertimage','Insert image URL:','http://'],
  clean: ['removeformat',null],
  toggle: ['toggleview'],
  upload: ['upload',null]
};