var UIF = {

	frame: function(n,c){
		var i = document.getElementById(n);
		if(!i){
			var d = document.createElement('DIV');
			d.className = 'hidden';
			d.innerHTML = '<iframe src="about:blank" id="'+n+'" name="'+n+'" onload="UIF.onLoad(\''+n+'\')"></iframe>';
			document.body.appendChild(d);
			i = document.getElementById(n);
		}
		i.onComplete = c;

		return n;
	},

	send: function(f,n,c){
		f.setAttribute('target',UIF.frame(n,c));
	},

	onLoad: function(n){
		var i = document.getElementById(n);
		if(i.contentDocument){
			var d = i.contentDocument;
		}else if(i.contentWindow){
			var d = i.contentWindow.document;
		}else{
			var d = window.frames[n].document;
		}
		if(d.location.href == 'about:blank'){
			return;
		}
		i.onComplete(d.body.innerHTML);
	}

};