window.addEvent('domready', function(){
    $$('div.appvar').each(function(el){
      try {
        obj = JSON.decode(el.get('html').trim());
        appvar.extend(obj);
      } catch (e) {};
    });

    // SORTABLES >> BEGIN
    try {
        var sortableoptions = {
            clone: true,
            opacity: .2,
            handle: '.handler',
            constrain: true,
            //revert: {duration: 500, transition: 'elastic:out'},
            'onSort': function(el, i){
                $(i).setStyles({
                    opacity: .8
                });
            },
            'onComplete': function(el){
                //el.removeClass('grabbing');
            },
            'onStart': function(el, clone){
                //el.addClass('grabbing');
                //clone.addClass('grabbing');
            }
        };

        new Sortables($$('.Sortable'), sortableoptions);
        $$('.Sortable .handler').setStyle('cursor', 'move');

        sortableoptions.constrain   = false;
        sortableoptions.revert      = {duration: 500, transition: 'elastic:out'};
        new Sortables($$('.Sortables'), sortableoptions);
        $$('.Sortables .handler').setStyle('cursor', 'move');
        
    } catch (e) {};
	// SORTABLES >> END

    /* OverText */
    $$('.OverText').each(function(el){
        new OverText(el);
    });

    /* FormCheck */
    $$('form.FormCheck').each(function(el){
        new FormCheck(el);
    });

    /*A SmoothScroll*/
    new Fx.SmoothScroll({
        transition: 'circ:out',
        links: '.SmoothScroll',
        wheelStops: false
    });

    $$('.ConfirmBox').addEvents({
        'click': function(e){
            if (!confirm(this.get('title'))) {
                e.stop();
                e.stopPropagation();
            }//end if
        }
    });


    /* -- MENU TAB --  */

    /************
    try {
        var test_pattern = '^'+dt.getElement('a').get('href').escapeRegExp()+'?';
        if (!appvar.WEBROOT.test(test_pattern) || appvar.CURRENT_DIR == appvar.WEBROOT)
            if (appvar.CURRENT_DIR.test(test_pattern) || appvar.CURRENT_URI.test(test_pattern)){
                dt.addClass('sel');
            }//end if

    } catch (e) {}
    ************/
   
    $$('a.nav').each(function(el){
        var test_pattern = el.get('href');
        test_pattern = '^'+test_pattern.escapeRegExp()+'?';
        if (requestURI.test(test_pattern) || requestURI == el.get('href')){
            el.getSiblings('a.sel').removeClass('sel');
            el.addClass('sel');
            el.getParent().addClass('sel');
        }//end if
    });
    /* --  -- */


    
    
});