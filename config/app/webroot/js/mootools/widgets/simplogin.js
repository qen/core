var SimpLogin = new Class({
	Implements:[Options, Events],
	options:{
		width: 350,
		center: true,
		overlayOpacity: 0.5,
		ajaxlogin:false,
		success_callback:null,
		login_redirect:'http://www.simpleology.com/webcockpit',
		loginUrl: '/ajax/ajaxlogin'
	},
	toSend: {
		'uname':null,
		'upass':null
	},
	
	initialize:function(options){
		this.setOptions(options);
	},
	display_message:function(msg){
		$('login_message').setStyle('display','');
		$('login_message').set('text',msg);
	},
	display_wait:function(show){
		if (show == true) {
			$('btnlogin').setStyle('display', 'none');
			$('cancellink').setStyle('display', 'none');
			$('login_please_wait').setStyle('display', '');
		}
		else {
			$('btnlogin').setStyle('display', '');
			$('cancellink').setStyle('display', '');
			$('login_please_wait').setStyle('display', 'none');
		}
	},
	handle_result:function(rText,rXml){
		var result = JSON.decode(rText);
		
		if (result.error){
			this.display_message(result.error);
			this.display_wait(false);
		}
		else
		{
			if (typeof(this.options.success_callback) == 'function')
				this.options.success_callback(result);
			this.hide();
		}
	},
	handle_failure:function(xhr){
		this.display_message('Connection failure. Please retry.');
		this.display_wait(false);
	},
	hide:function(){
		this.dialogOverlay.destroy();
		this.dialog.destroy();
	},
	login:function(){
		this.display_wait(true);
		
		var rq = new Request({
			url:this.options.loginUrl,
			onSuccess:this.handle_result.bind(this),
			onFailure:this.handle_failure.bind(this)
		});
		
		rq.send(Hash.toQueryString({
			'uname': $('simploginform').getElement('#uname').value,
			'upass': $('simploginform').getElement('#upass').value.md5()
		}));
	},
	show:function(){
		var dialogOverlay = new Element('div');
		
		dialogOverlay.setStyles({
			position: 'absolute',
			zIndex: '1600000',
			top: '0',
			left: '0',
			backgroundColor: '#000',
			width: window.getScrollWidth(),
			height: window.getScrollHeight(),
			opacity: this.options.overlayOpacity
		});
		
		dialogOverlay.addEvent('click',this.hide.bind(this));
		
		var dialog = new Element('div',{
			'class':'dialog'
		});
		
		dialog.setStyles({
			position: 'absolute',
			zIndex: '1600001',
			width:this.options.width,
			display: 'block'
		});
		
		var dialogLeft = (window.getWidth()/2)-(this.options.width/2);
		
		//this will display the dialog 25% below the view port
		var windowScroll = window.getScroll();
		var dialogTop = (window.getHeight()/4) + windowScroll.y;
		
		dialog.setStyles({
			top: dialogTop,
			left: dialogLeft
		});
		
		new Element('div',{
			'class': 'login-header'
		}).inject(dialog).set('html','<span style="font-size:large;color:#c00;font-family:Times New Roman"><i>simple</i>&middot;ology</span> Login');
		
		//login message
		var login_message = new Element('div',{
			'id':'login_message',
			'style':'display:none',
			'class':'login-message'
		}).inject(dialog);
		
		//create login form
		var loginForm = new Element('form',{
			'action': this.options.loginUrl,
			'method': 'post',
			'id':'simploginform'
		});
		loginForm.inject(dialog);
		
		var label,input,field;
		
		//username
		label = new Element('div',{'class': 'label'});
		input = new Element('div',{'class': 'input'});
		var uname = new Element('input');
		label.set('text','Username').inject(loginForm);
		uname.set({
			'type':'text',
			'name':'uname',
			'id':'uname',
			'size':'25'
		}).inject(input);
		input.inject(loginForm);
		new Element('br').inject(input,'after').setStyle('clear','right');
		
		//password
		label = new Element('div',{'class': 'label'});
		input = new Element('div',{'class': 'input'});
		field = new Element('input');
		label.set('text','Password').inject(loginForm);
		field.set({
			'type':'password',
			'name':'upass',
			'id':'upass',
			'size':'25'
		}).inject(input);
		input.inject(loginForm);
		new Element('br').inject(input,'after').setStyle('clear','right');
		
		//forgot password
		label = new Element('div',{'class': 'label'});
		input = new Element('div',{'class': 'input'});
		field = new Element('a');
		label.set('text','').inject(loginForm);
		field.set({
			'target':'_blank',
			'href':'http://www.simpleology.com/webcockpit/forgot_pass.php'
		}).set('text','I forgot my password').inject(input);
		input.inject(loginForm);
		new Element('br').inject(input,'after').setStyle('clear','right');
		
		//submit
		label = new Element('div',{'class': 'label'});
		input = new Element('div',{'class': 'input'});
		field = new Element('input');
		label.set('text','').inject(loginForm);
		
		if (this.options.ajaxlogin) {
			field.set({
				'type':'button',
				'value':'Login',
				'id':'btnlogin'
			});
			field.addEvent('click',this.login.bind(this));
		}else{
			//IE bug workaround - create a new element
			field.set({
				'type':'submit',
				'value':'Login',
				'id':'btnlogin'
			});
			loginForm.addEvent('submit',function(){
				$('upass').value = $('upass').value.md5();
				this.display_wait(true);
			}.bind(this));
		}
		
		field.inject(input);
		
		//cancel
		var cancel = new Element('a',{
			'style':'padding-left:10px;text-decoration:underline;color:#555;cursor:hand;cursor:pointer',
			'id':'cancellink',
			'text':'cancel'
		}).inject(input).addEvent('click',this.hide.bind(this));
		
		//add hidden redirect
		hiddenredirect = new Element('input',{
			'type':'hidden',
			'name':'redirect',
			'value':this.options.login_redirect
		}).inject(loginForm);
		
		input.inject(loginForm);
		
		//please wait
		var login_please_wait = new Element('span',{
			'id':'login_please_wait',
			'style':'display:none'
		}).set('text','Logging in. Please wait...');
		login_please_wait.inject(field,'after');
		new Element('br').inject(input,'after').setStyle('clear','right');
		
		dialogOverlay.inject($(document.body));
		dialog.inject($(document.body));
		
		dialogOverlay.setStyle('display','');
		dialog.setStyle('display','');
	    
		this.dialogOverlay = dialogOverlay;
		this.dialog = dialog;
		this.loginForm = loginForm;
		
		uname.focus();
		
		window.addEvent('scroll',this.scroll.bind(this));
	},
	dialog:Element.empty,
	dialogOverlay:Element.empty,
	loginForm:Element.empty,
	scroll:function(){
		var dialogLeft = (window.getWidth()/2)-(this.options.width/2);
		
		var windowScroll = window.getScroll();
		var dialogTop = (window.getHeight()/4) + windowScroll.y;
		
		this.dialog.setStyles({
			top: dialogTop,
			left: dialogLeft
		});
		
		this.dialogOverlay.setStyles({
			height: window.getScrollHeight(),
			width: window.getScrollWidth()
		});
	}
});