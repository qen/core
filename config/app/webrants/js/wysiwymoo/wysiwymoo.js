/**
 * WYSIWYMoo
 * is a Markdown What You See Is What You Mean editor for MooTools
 *
 * Originally inspired by Peter Hellberg's http://code.c7.se/js/markdown_toolbar/,
 * Ted Devito's jQuery tabby http://teddevito.com/demos/textarea.html
 * and http://softwaremaniacs.org/playground/showdown-highlight/
 *
 * @author Qen Empaces <qen.empaces@gmail.com>
 * @copyright 2011
 * @version 0.1
 * @url http://github.com/qen/wysiwymoo
 * @license MIT
 *
 *
 * http://code.c7.se/js/markdown_toolbar/
 * @author Peter Hellberg <peter@c7.se>
 * @copyright 2007 Code7 Interactive
 * @url http://codeseven.org/projects/control_textarea/
 *
 *
 * http://teddevito.com/demos/textarea.html
 * Tabby jQuery plugin version 0.12
 * Ted Devito - http://teddevito.com/demos/textarea.html
 * Copyright (c) 2009 Ted Devito
 *
 */

var WYSIWYMoo = new Class({

  element: null,
  toolbar: null,
  markdown: null,
  preview: null,
  language: new Hash({
    'heading' : 'Heading',
    'bold' : 'Bold',
    'italics' : 'Italics',
    'unordered_list' : 'Bullet list',
    'ordered_list' : 'Ordered list',
    'link' : 'Link',
    'link_text': new Hash({
      'enterurl': 'Enter Link URL',
      'linktext': 'Link Text'
    }),
    'image' : 'Image',
    'image_text': new Hash({
      'enter': 'Enter Image URL',
      'alt': 'Image Alt Text'
    }),
    'html': 'HTML Preview',
    'help' : 'Help'
  }),

  initialize: function(element){

    this.element = $(element);
    this.markdown = new Showdown.converter();

    if(this.element) {
      var d = this.element.getSize();
      this.wrapper = new Element('div', {
        'class': 'markdown',
        'styles': {
          'width': d.width,
          'height': d.height,
          'position': 'relative'
        }
      });
      this.wrapper.wraps(this.element);

      /* Create the toolbar element */
      this.toolbar =	new Element('ul', {
        'class': 'markdown_toolbar',
        'styles': {
          'width': d.x,
          'overflow': 'hidden'
        }
      });

    /* Add the buttons to the toolbar */
    this.buttons();

    /* Inject the toolbar before the textarea */
    this.toolbar.inject(this.wrapper, 'before');

    this.preview = new Element('div', {
      'class': 'preview',
      'styles': {
        'width': d.x,
        'height': d.y,
        'overflow': 'scroll',
        'display': 'none'
      }
    }).inject(this.element, 'after');

    /* handle tabs */
    this.element.addEvents({
      'keydown':function(evnt){
        if (evnt.key == 'tab'){
          evnt.preventDefault();
          evnt.stopPropagation();

          if (this.element.setSelectionRange) {
            this.gecko_tab(this.element, evnt.shift, {
              tabString : "    "
            });
          } else if (document.selection) {
            this.ie_tab(this.element, evnt.shift, {
              tabString : "    "
            });
          }
        }
      }.bind(this),

      'mouseup': function(){
        if (document.selection)
          this.range = document.selection.createRange();

      }.bind(this)
    });

  }
},

/**
 * Tabby jQuery plugin version 0.12
 * Ted Devito - http://teddevito.com/demos/textarea.html
 * Copyright (c) 2009 Ted Devito
 */
gecko_tab: function (o, shft, options) {
  var ss = o.selectionStart;
  var es = o.selectionEnd;

  // when there's no selection and we're just working with the caret, we'll add/remove the tabs at the caret, providing more control
  if(ss == es) {
    // SHIFT+TAB
    if (shft) {
      // check to the left of the caret first
      if (options.tabString == o.value.substring(ss-options.tabString.length, ss)) {
        o.value = o.value.substring(0, ss-options.tabString.length) + o.value.substring(ss); // put it back together omitting one character to the left
        o.focus();
        o.setSelectionRange(ss - options.tabString.length, ss - options.tabString.length);
      }
      // then check to the right of the caret
      else if (options.tabString == o.value.substring(ss, ss + options.tabString.length)) {
        o.value = o.value.substring(0, ss) + o.value.substring(ss + options.tabString.length); // put it back together omitting one character to the right
        o.focus();
        o.setSelectionRange(ss,ss);
      }
    }
    // TAB
    else {
      o.value = o.value.substring(0, ss) + options.tabString + o.value.substring(ss);
      o.focus();
      o.setSelectionRange(ss + options.tabString.length, ss + options.tabString.length);
    }
  }
  // selections will always add/remove tabs from the start of the line
  else {
    // split the textarea up into lines and figure out which lines are included in the selection
    var lines = o.value.split("\n");
    var indices = new Array();
    var sl = 0; // start of the line
    var el = 0; // end of the line
    for (var i in lines) {
      el = sl + lines[i].length;
      indices.push({
        start: sl,
        end: el,
        selected: (sl <= ss && el > ss) || (el >= es && sl < es) || (sl > ss && el < es)
        });
      sl = el + 1;// for "\n"
    }

    // walk through the array of lines (indices) and add tabs where appropriate
    var modifier = 0;
    for (var i in indices) {
      if (indices[i].selected) {
        var pos = indices[i].start + modifier; // adjust for tabs already inserted/removed
        // SHIFT+TAB
        if (shft && options.tabString == o.value.substring(pos,pos+options.tabString.length)) { // only SHIFT+TAB if there's a tab at the start of the line
          o.value = o.value.substring(0,pos) + o.value.substring(pos + options.tabString.length); // omit the tabstring to the right
          modifier -= options.tabString.length;
        }
        // TAB
        else if (!shft) {
          o.value = o.value.substring(0,pos) + options.tabString + o.value.substring(pos); // insert the tabstring
          modifier += options.tabString.length;
        }
      }
    }
    o.focus();
    var ns = ss + ((modifier > 0) ? options.tabString.length : (modifier < 0) ? -options.tabString.length : 0);
    var ne = es + modifier;
    o.setSelectionRange(ns,ne);
  }
},

ie_tab: function (o, shft, options) {
  var range = options.range || document.selection.createRange();
  // @todo problem with out of focus when the toolbar is clicked
  //if (o == $(range.parentElement())) {

  // when there's no selection and we're just working with the caret, we'll add/remove the tabs at the caret, providing more control
  if ('' == range.text) {

    // SHIFT+TAB
    if (shft) {
      var bookmark = range.getBookmark();
      //first try to the left by moving opening up our empty range to the left
      range.moveStart('character', -options.tabString.length);
      if (options.tabString == range.text) {
        range.text = '';
      } else {
        // if that didn't work then reset the range and try opening it to the right
        range.moveToBookmark(bookmark);
        range.moveEnd('character', options.tabString.length);
        if (options.tabString == range.text)
          range.text = '';
      }
      // move the pointer to the start of them empty range and select it
      range.collapse(true);
      range.select();
    }

    else {

      // very simple here. just insert the tab into the range and put the pointer at the end
      range.text = options.tabString;
      range.collapse(false);
      range.select();

    }
  }
  // selections will always add/remove tabs from the start of the line
  else {
    var selection_text = range.text;
    var selection_len = selection_text.length;
    var selection_arr = selection_text.split("\r\n");

    var before_range = document.body.createTextRange();
    before_range.moveToElementText(o);
    before_range.setEndPoint("EndToStart", range);
    var before_text = before_range.text;
    var before_arr = before_text.split("\r\n");
    var before_len = before_text.length; // - before_arr.length + 1;

    var after_range = document.body.createTextRange();
    after_range.moveToElementText(o);
    after_range.setEndPoint("StartToEnd", range);
    var after_text = after_range.text; // we can accurately calculate distance to the end because we're not worried about MSIE trimming a \r\n

    var end_range = document.body.createTextRange();
    end_range.moveToElementText(o);
    end_range.setEndPoint("StartToEnd", before_range);
    var end_text = end_range.text; // we can accurately calculate distance to the end because we're not worried about MSIE trimming a \r\n

    var check_html = o.value;
    //o.text(before_len + " + " + selection_len + " + " + after_text.length + " = " + check_html.length);
    //console.log(before_len + " + " + selection_len + " + " + after_text.length + " = " + check_html.length);
    if((before_len + end_text.length) < check_html.length) {
      before_arr.push("");
      before_len += 2; // for the \r\n that was trimmed
      if (shft && options.tabString == selection_arr[0].substring(0,options.tabString.length))
        selection_arr[0] = selection_arr[0].substring(options.tabString.length);
      else if (!shft) selection_arr[0] = options.tabString + selection_arr[0];
    } else {
      if (shft && options.tabString == before_arr[before_arr.length-1].substring(0,options.tabString.length))
        before_arr[before_arr.length-1] = before_arr[before_arr.length-1].substring(options.tabString.length);
      else if (!shft) before_arr[before_arr.length-1] = options.tabString + before_arr[before_arr.length-1];
    }

    for (var i = 1; i < selection_arr.length; i++) {
      if (shft && options.tabString == selection_arr[i].substring(0,options.tabString.length))
        selection_arr[i] = selection_arr[i].substring(options.tabString.length);
      else if (!shft) selection_arr[i] = options.tabString + selection_arr[i];
    }

    if (1 == before_arr.length && 0 == before_len) {
      if (shft && options.tabString == selection_arr[0].substring(0,options.tabString.length))
        selection_arr[0] = selection_arr[0].substring(options.tabString.length);
      else if (!shft) selection_arr[0] = options.tabString + selection_arr[0];
    }

    if ((before_len + selection_len + after_text.length) < check_html.length) {
      selection_arr.push("");
      selection_len += 2; // for the \r\n that was trimmed
    }

    before_range.text = before_arr.join("\r\n");
    range.text = selection_arr.join("\r\n");

    var new_range = document.body.createTextRange();
    new_range.moveToElementText(o);

    if (0 < before_len)	new_range.setEndPoint("StartToEnd", before_range);
    else new_range.setEndPoint("StartToStart", before_range);
    new_range.setEndPoint("EndToEnd", range);

    new_range.select();

  }
//}
},
/* END Tabby code */

/* Control.TextArea code */

doOnChange: function(){
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
  if(!!document.selection) {
    this.range = document.selection.createRange();
    return this.range.text;
  } else if(!!this.element.setSelectionRange) {
    this.range = this.element.setSelectionRange;
    return this.element.value.substring(this.element.selectionStart,this.element.selectionEnd);
  }
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
  var seltext = this.getSelection();

  if (seltext.test(/^\s+/)){
    before = " " + before;
  }

  if (seltext.test(/\s+$/)){
    after = after + " ";
  }

  this.replaceSelection(before + seltext.trim() + after);
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

setCaretPosition: function(pos){

  if(this.element.setSelectionRange)
  {
    this.element.focus();
    this.element.setSelectionRange(pos,pos);
  }
  else if (this.element.createTextRange) {
    var range = this.element.createTextRange();
    range.collapse(true);
    range.moveEnd('character', pos);
    range.moveStart('character', pos);
    range.select();
  }
},

buttons: function() {

  this.addButton('heading', function(evnt){
    if (this.element.setSelectionRange) {
      this.gecko_tab(this.element, evnt.shift, {
        tabString : "# "
      });
    } else if (document.selection) {
      this.ie_tab(this.element, evnt.shift, {
        tabString : "# ",
        range: this.range
        });
    }
  });

  this.addButton('italics', function(){
    this.wrapSelection('*','*');
  });

  this.addButton('bold', function(){
    this.wrapSelection('**','**');
  });

  this.addButton('unordered_list', function(evnt){
    if (this.element.setSelectionRange) {
      this.gecko_tab(this.element, evnt.shift, {
        tabString : "- "
      });
    } else if (document.selection) {
      this.ie_tab(this.element, evnt.shiftift, {
        tabString : "- ",
        range: this.range
        });
    }
  });

  this.addButton('ordered_list', function(evnt){
    if (this.element.setSelectionRange) {
      this.gecko_tab(this.element, evnt.shift, {
        tabString : "1. "
      });
    } else if (document.selection) {
      this.ie_tab(this.element, evnt.shiftt, {
        tabString : "1. ",
        range: this.range
        });
    }
  });

  this.addButton('quote', function(evnt){
    if (this.element.setSelectionRange) {
      this.gecko_tab(this.element, evnt.shift, {
        tabString : "> "
      });
    } else if (document.selection) {
      this.ie_tab(this.element, evnt.shift, {
        tabString : "> ",
        range: this.range
        });
    }
  });

  this.addButton('link', function(){

    var str = this.language.get('link_text');

    var selection = this.getSelection();
    var response = prompt(str.get('enterurl'),'');

    if(response == null) return;

    this.replaceSelection('[' + (selection == '' ? str.get('linktext') : selection) + '](' + (response == '' ? 'http://link_url/' : response).replace(/^(?!(f|ht)tps?:\/\/)/,'http://') + ')');
  });

  this.addButton('image', function() {

    var str = this.language.get('image_text');

    var selection = this.getSelection();
    var response = prompt(str.get('enter'),'');

    if(response == null) {
      return;
    }

    this.replaceSelection('![' + (selection == '' ? str.get('alt') : selection) + '](' + (response == '' ? 'http://image_url/' : response).replace(/^(?!(f|ht)tps?:\/\/)/,'http://') + ')');

  });

  this.addButton('help', function(evnt) {
    window.open('http://daringfireball.net/projects/markdown/dingus');
  });

  this.addButton('html', function(evnt){
    var el = $(evnt.target);
    if (el.hasClass('markdown_close_button')) {
      el.removeClass('markdown_close_button');
      el.set('title', 'HTML Preview');
      this.toolbar.getElements('a').setStyle('opacity', 1);
      this.element.setStyle('display', 'block');
      this.preview.setStyle('display', 'none');
      return;
    }//end if

    this.toolbar.getElements('a').setStyle('opacity', 0);
    el.addClass('markdown_close_button');
    el.set('title', 'Close HTML Preview');
    el.set('opacity', 1);

    this.element.setStyle('display', 'none');
    this.preview.set('html', this.markdown.makeHtml(this.getValue()))
    this.preview.setStyle('display', 'block');
  });

},

addButton: function(name, func){
  var buttonText = this.language.get(name) || name;

  var button = new Element('li').adopt(
    new Element('a', {
      'class': 'markdown_'+name+'_button',
      'events': {
        'click': function(evnt){
          evnt.stopPropagation();
          evnt.stop();
          func.call(this, evnt);
        }.bind(this)
      },
      'title': buttonText
    }).adopt(
      new Element('span').set('html', buttonText)
      )
    );

  this.toolbar.adopt(button);
}
});


//
// showdown.js -- A javascript port of Markdown.
//
// Copyright (c) 2007 John Fraser.
//
// Original Markdown Copyright (c) 2004-2005 John Gruber
//   <http://daringfireball.net/projects/markdown/>
//
// Redistributable under a BSD-style open source license.
// See license.txt for more information.
//
// The full source distribution is at:
//
//				A A L
//				T C A
//				T K B
//
//   <http://www.attacklab.net/>
//

//
// Wherever possible, Showdown is a straight, line-by-line port
// of the Perl version of Markdown.
//
// This is not a normal parser design; it's basically just a
// series of string substitutions.  It's hard to read and
// maintain this way,  but keeping Showdown close to the original
// design makes it easier to port new features.
//
// More importantly, Showdown behaves like markdown.pl in most
// edge cases.  So web applications can do client-side preview
// in Javascript, and then build identical HTML on the server.
//
// This port needs the new RegExp functionality of ECMA 262,
// 3rd Edition (i.e. Javascript 1.5).  Most modern web browsers
// should do fine.  Even with the new regular expression features,
// We do a lot of work to emulate Perl's regex functionality.
// The tricky changes in this file mostly have the "attacklab:"
// label.  Major or self-explanatory changes don't.
//
// Smart diff tools like Araxis Merge will be able to match up
// this file with markdown.pl in a useful way.  A little tweaking
// helps: in a copy of markdown.pl, replace "#" with "//" and
// replace "$text" with "text".  Be sure to ignore whitespace
// and line endings.
//
//
// Showdown usage:
//
//   var text = "Markdown *rocks*.";
//
//   var converter = new Showdown.converter();
//   var html = converter.makeHtml(text);
//
//   alert(html);
//
// Note: move the sample code to the bottom of this
// file before uncommenting it.
//

// **************************************************
// GitHub Flavored Markdown modifications by Tekkub
// http://github.github.com/github-flavored-markdown/
//
// Modifications are tagged with "GFM"
// **************************************************

//
// Showdown namespace
//
var Showdown = {};

//
// converter
//
// Wraps all "globals" so that the only thing
// exposed is makeHtml().
//
Showdown.converter = function() {

  //
  // Globals:
  //

  // Global hashes, used by various utility routines
  var g_urls;
  var g_titles;
  var g_html_blocks;

  // Used to track when we're inside an ordered or unordered list
  // (see _ProcessListItems() for details):
  var g_list_level = 0;


  this.makeHtml = function(text) {
    //
    // Main function. The order in which other subs are called here is
    // essential. Link and image substitutions need to happen before
    // _EscapeSpecialCharsWithinTagAttributes(), so that any *'s or _'s in the <a>
    // and <img> tags get encoded.
    //

    // Clear the global hashes. If we don't clear these, you get conflicts
    // from other articles when generating a page which contains more than
    // one article (e.g. an index page that shows the N most recent
    // articles):
    g_urls = new Array();
    g_titles = new Array();
    g_html_blocks = new Array();

    // attacklab: Replace ~ with ~T
    // This lets us use tilde as an escape char to avoid md5 hashes
    // The choice of character is arbitray; anything that isn't
    // magic in Markdown will work.
    text = text.replace(/~/g,"~T");

    // attacklab: Replace $ with ~D
    // RegExp interprets $ as a special character
    // when it's in a replacement string
    text = text.replace(/\$/g,"~D");

    // Standardize line endings
    text = text.replace(/\r\n/g,"\n"); // DOS to Unix
    text = text.replace(/\r/g,"\n"); // Mac to Unix

    // Make sure text begins and ends with a couple of newlines:
    text = "\n\n" + text + "\n\n";

    // Convert all tabs to spaces.
    text = _Detab(text);

    // Strip any lines consisting only of spaces and tabs.
    // This makes subsequent regexen easier to write, because we can
    // match consecutive blank lines with /\n+/ instead of something
    // contorted like /[ \t]*\n+/ .
    text = text.replace(/^[ \t]+$/mg,"");

    // Turn block-level HTML blocks into hash entries
    text = _HashHTMLBlocks(text);

    // Strip link definitions, store in hashes.
    text = _StripLinkDefinitions(text);

    text = _RunBlockGamut(text);

    text = _UnescapeSpecialChars(text);

    // attacklab: Restore dollar signs
    text = text.replace(/~D/g,"$$");

    // attacklab: Restore tildes
    text = text.replace(/~T/g,"~");

    // ** GFM **  Auto-link URLs and emails
    text = text.replace(/https?\:\/\/[^"\s\<\>]*[^.,;'">\:\s\<\>\)\]\!]/g, function(wholeMatch,matchIndex){
      var left = text.slice(0, matchIndex), right = text.slice(matchIndex)
      if (left.match(/<[^>]+$/) && right.match(/^[^>]*>/)) {
        return wholeMatch
        }
      href = wholeMatch.replace(/^http:\/\/github.com\//, "https://github.com/")
      return "<a href='" + href + "'>" + wholeMatch + "</a>";
    });
    text = text.replace(/[a-z0-9_\-+=.]+@[a-z0-9\-]+(\.[a-z0-9-]+)+/ig, function(wholeMatch){
      return "<a href='mailto:" + wholeMatch + "'>" + wholeMatch + "</a>";
    });

    // ** GFM ** Auto-link sha1 if GitHub.nameWithOwner is defined
    text = text.replace(/[a-f0-9]{40}/ig, function(wholeMatch,matchIndex){
      if (typeof(GitHub) == "undefined" || typeof(GitHub.nameWithOwner) == "undefined") {
        return wholeMatch;
      }
      var left = text.slice(0, matchIndex), right = text.slice(matchIndex)
      if (left.match(/@$/) || (left.match(/<[^>]+$/) && right.match(/^[^>]*>/))) {
        return wholeMatch;
      }
      return "<a href='http://github.com/" + GitHub.nameWithOwner + "/commit/" + wholeMatch + "'>" + wholeMatch.substring(0,7) + "</a>";
    });

    // ** GFM ** Auto-link user@sha1 if GitHub.nameWithOwner is defined
    text = text.replace(/([a-z0-9_\-+=.]+)@([a-f0-9]{40})/ig, function(wholeMatch,username,sha,matchIndex){
      if (typeof(GitHub) == "undefined" || typeof(GitHub.nameWithOwner) == "undefined") {
        return wholeMatch;
      }
      GitHub.repoName = GitHub.repoName || _GetRepoName()
      var left = text.slice(0, matchIndex), right = text.slice(matchIndex)
      if (left.match(/\/$/) || (left.match(/<[^>]+$/) && right.match(/^[^>]*>/))) {
        return wholeMatch;
      }
      return "<a href='http://github.com/" + username + "/" + GitHub.repoName + "/commit/" + sha + "'>" + username + "@" + sha.substring(0,7) + "</a>";
    });

    // ** GFM ** Auto-link user/repo@sha1
    text = text.replace(/([a-z0-9_\-+=.]+\/[a-z0-9_\-+=.]+)@([a-f0-9]{40})/ig, function(wholeMatch,repo,sha){
      return "<a href='http://github.com/" + repo + "/commit/" + sha + "'>" + repo + "@" + sha.substring(0,7) + "</a>";
    });

    // ** GFM ** Auto-link #issue if GitHub.nameWithOwner is defined
    text = text.replace(/#([0-9]+)/ig, function(wholeMatch,issue,matchIndex){
      if (typeof(GitHub) == "undefined" || typeof(GitHub.nameWithOwner) == "undefined") {
        return wholeMatch;
      }
      var left = text.slice(0, matchIndex), right = text.slice(matchIndex)
      if (left == "" || left.match(/[a-z0-9_\-+=.]$/) || (left.match(/<[^>]+$/) && right.match(/^[^>]*>/))) {
        return wholeMatch;
      }
      return "<a href='http://github.com/" + GitHub.nameWithOwner + "/issues/#issue/" + issue + "'>" + wholeMatch + "</a>";
    });

    // ** GFM ** Auto-link user#issue if GitHub.nameWithOwner is defined
    text = text.replace(/([a-z0-9_\-+=.]+)#([0-9]+)/ig, function(wholeMatch,username,issue,matchIndex){
      if (typeof(GitHub) == "undefined" || typeof(GitHub.nameWithOwner) == "undefined") {
        return wholeMatch;
      }
      GitHub.repoName = GitHub.repoName || _GetRepoName()
      var left = text.slice(0, matchIndex), right = text.slice(matchIndex)
      if (left.match(/\/$/) || (left.match(/<[^>]+$/) && right.match(/^[^>]*>/))) {
        return wholeMatch;
      }
      return "<a href='http://github.com/" + username + "/" + GitHub.repoName + "/issues/#issue/" + issue + "'>" + wholeMatch + "</a>";
    });

    // ** GFM ** Auto-link user/repo#issue
    text = text.replace(/([a-z0-9_\-+=.]+\/[a-z0-9_\-+=.]+)#([0-9]+)/ig, function(wholeMatch,repo,issue){
      return "<a href='http://github.com/" + repo + "/issues/#issue/" + issue + "'>" + wholeMatch + "</a>";
    });

    return text;
  }


  var _GetRepoName = function() {
    return GitHub.nameWithOwner.match(/^.+\/(.+)$/)[1]
  }

  var _StripLinkDefinitions = function(text) {
    //
    // Strips link definitions from text, stores the URLs and titles in
    // hash references.
    //

    // Link defs are in the form: ^[id]: url "optional title"

    /*
		var text = text.replace(/
				^[ ]{0,3}\[(.+)\]:  // id = $1  attacklab: g_tab_width - 1
				  [ \t]*
				  \n?				// maybe *one* newline
				  [ \t]*
				<?(\S+?)>?			// url = $2
				  [ \t]*
				  \n?				// maybe one newline
				  [ \t]*
				(?:
				  (\n*)				// any lines skipped = $3 attacklab: lookbehind removed
				  ["(]
				  (.+?)				// title = $4
				  [")]
				  [ \t]*
				)?					// title is optional
				(?:\n+|$)
			  /gm,
			  function(){...});
	*/
    var text = text.replace(/^[ ]{0,3}\[(.+)\]:[ \t]*\n?[ \t]*<?(\S+?)>?[ \t]*\n?[ \t]*(?:(\n*)["(](.+?)[")][ \t]*)?(?:\n+|\Z)/gm,
      function (wholeMatch,m1,m2,m3,m4) {
        m1 = m1.toLowerCase();
        g_urls[m1] = _EncodeAmpsAndAngles(m2);  // Link IDs are case-insensitive
        if (m3) {
          // Oops, found blank lines, so it's not a title.
          // Put back the parenthetical statement we stole.
          return m3+m4;
        } else if (m4) {
          g_titles[m1] = m4.replace(/"/g,"&quot;");
        }

        // Completely remove the definition from the text
        return "";
      }
      );

    return text;
  }


  var _HashHTMLBlocks = function(text) {
    // attacklab: Double up blank lines to reduce lookaround
    text = text.replace(/\n/g,"\n\n");

    // Hashify HTML blocks:
    // We only want to do this for block-level HTML tags, such as headers,
    // lists, and tables. That's because we still want to wrap <p>s around
    // "paragraphs" that are wrapped in non-block-level tags, such as anchors,
    // phrase emphasis, and spans. The list of tags we're looking for is
    // hard-coded:
    var block_tags_a = "p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|script|noscript|form|fieldset|iframe|math|ins|del"
    var block_tags_b = "p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|script|noscript|form|fieldset|iframe|math"

    // First, look for nested blocks, e.g.:
    //   <div>
    //     <div>
    //     tags for inner block must be indented.
    //     </div>
    //   </div>
    //
    // The outermost tags must start at the left margin for this to match, and
    // the inner nested divs must be indented.
    // We need to do this before the next, more liberal match, because the next
    // match will start at the first `<div>` and stop at the first `</div>`.

    // attacklab: This regex can be expensive when it fails.
    /*
		var text = text.replace(/
		(						// save in $1
			^					// start of line  (with /m)
			<($block_tags_a)	// start tag = $2
			\b					// word break
								// attacklab: hack around khtml/pcre bug...
			[^\r]*?\n			// any number of lines, minimally matching
			</\2>				// the matching end tag
			[ \t]*				// trailing spaces/tabs
			(?=\n+)				// followed by a newline
		)						// attacklab: there are sentinel newlines at end of document
		/gm,function(){...}};
	*/
    text = text.replace(/^(<(p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|script|noscript|form|fieldset|iframe|math|ins|del)\b[^\r]*?\n<\/\2>[ \t]*(?=\n+))/gm,hashElement);

    //
    // Now match more liberally, simply from `\n<tag>` to `</tag>\n`
    //

    /*
		var text = text.replace(/
		(						// save in $1
			^					// start of line  (with /m)
			<($block_tags_b)	// start tag = $2
			\b					// word break
								// attacklab: hack around khtml/pcre bug...
			[^\r]*?				// any number of lines, minimally matching
			.*</\2>				// the matching end tag
			[ \t]*				// trailing spaces/tabs
			(?=\n+)				// followed by a newline
		)						// attacklab: there are sentinel newlines at end of document
		/gm,function(){...}};
	*/
    text = text.replace(/^(<(p|div|h[1-6]|blockquote|pre|table|dl|ol|ul|script|noscript|form|fieldset|iframe|math)\b[^\r]*?.*<\/\2>[ \t]*(?=\n+)\n)/gm,hashElement);

    // Special case just for <hr />. It was easier to make a special case than
    // to make the other regex more complicated.

    /*
		text = text.replace(/
		(						// save in $1
			\n\n				// Starting after a blank line
			[ ]{0,3}
			(<(hr)				// start tag = $2
			\b					// word break
			([^<>])*?			//
			\/?>)				// the matching end tag
			[ \t]*
			(?=\n{2,})			// followed by a blank line
		)
		/g,hashElement);
	*/
    text = text.replace(/(\n[ ]{0,3}(<(hr)\b([^<>])*?\/?>)[ \t]*(?=\n{2,}))/g,hashElement);

    // Special case for standalone HTML comments:

    /*
		text = text.replace(/
		(						// save in $1
			\n\n				// Starting after a blank line
			[ ]{0,3}			// attacklab: g_tab_width - 1
			<!
			(--[^\r]*?--\s*)+
			>
			[ \t]*
			(?=\n{2,})			// followed by a blank line
		)
		/g,hashElement);
	*/
    text = text.replace(/(\n\n[ ]{0,3}<!(--[^\r]*?--\s*)+>[ \t]*(?=\n{2,}))/g,hashElement);

    // PHP and ASP-style processor instructions (<?...?> and <%...%>)

    /*
		text = text.replace(/
		(?:
			\n\n				// Starting after a blank line
		)
		(						// save in $1
			[ ]{0,3}			// attacklab: g_tab_width - 1
			(?:
				<([?%])			// $2
				[^\r]*?
				\2>
			)
			[ \t]*
			(?=\n{2,})			// followed by a blank line
		)
		/g,hashElement);
	*/
    text = text.replace(/(?:\n\n)([ ]{0,3}(?:<([?%])[^\r]*?\2>)[ \t]*(?=\n{2,}))/g,hashElement);

    // attacklab: Undo double lines (see comment at top of this function)
    text = text.replace(/\n\n/g,"\n");
    return text;
  }

  var hashElement = function(wholeMatch,m1) {
    var blockText = m1;

    // Undo double lines
    blockText = blockText.replace(/\n\n/g,"\n");
    blockText = blockText.replace(/^\n/,"");

    // strip trailing blank lines
    blockText = blockText.replace(/\n+$/g,"");

    // Replace the element text with a marker ("~KxK" where x is its key)
    blockText = "\n\n~K" + (g_html_blocks.push(blockText)-1) + "K\n\n";

    return blockText;
  };

  var _RunBlockGamut = function(text) {
    //
    // These are all the transformations that form block-level
    // tags like paragraphs, headers, and list items.
    //
    text = _DoHeaders(text);

    // Do Horizontal Rules:
    var key = hashBlock("<hr />");
    text = text.replace(/^[ ]{0,2}([ ]?\*[ ]?){3,}[ \t]*$/gm,key);
    text = text.replace(/^[ ]{0,2}([ ]?\-[ ]?){3,}[ \t]*$/gm,key);
    text = text.replace(/^[ ]{0,2}([ ]?\_[ ]?){3,}[ \t]*$/gm,key);

    text = _DoLists(text);
    text = _DoCodeBlocks(text);
    text = _DoBlockQuotes(text);

    // We already ran _HashHTMLBlocks() before, in Markdown(), but that
    // was to escape raw HTML in the original Markdown source. This time,
    // we're escaping the markup we've just created, so that we don't wrap
    // <p> tags around block-level tags.
    text = _HashHTMLBlocks(text);
    text = _FormParagraphs(text);

    return text;
  }


  var _RunSpanGamut = function(text) {
    //
    // These are all the transformations that occur *within* block-level
    // tags like paragraphs, headers, and list items.
    //

    text = _DoCodeSpans(text);
    text = _EscapeSpecialCharsWithinTagAttributes(text);
    text = _EncodeBackslashEscapes(text);

    // Process anchor and image tags. Images must come first,
    // because ![foo][f] looks like an anchor.
    text = _DoImages(text);
    text = _DoAnchors(text);

    // Make links out of things like `<http://example.com/>`
    // Must come after _DoAnchors(), because you can use < and >
    // delimiters in inline links like [this](<url>).
    text = _DoAutoLinks(text);
    text = _EncodeAmpsAndAngles(text);
    text = _DoItalicsAndBold(text);

    // Do hard breaks:
    text = text.replace(/  +\n/g," <br />\n");

    return text;
  }

  var _EscapeSpecialCharsWithinTagAttributes = function(text) {
    //
    // Within tags -- meaning between < and > -- encode [\ ` * _] so they
    // don't conflict with their use in Markdown for code, italics and strong.
    //

    // Build a regex to find HTML tags and comments.  See Friedl's
    // "Mastering Regular Expressions", 2nd Ed., pp. 200-201.
    var regex = /(<[a-z\/!$]("[^"]*"|'[^']*'|[^'">])*>|<!(--.*?--\s*)+>)/gi;

    text = text.replace(regex, function(wholeMatch) {
      var tag = wholeMatch.replace(/(.)<\/?code>(?=.)/g,"$1`");
      tag = escapeCharacters(tag,"\\`*_");
      return tag;
    });

    return text;
  }

  var _DoAnchors = function(text) {
    //
    // Turn Markdown link shortcuts into XHTML <a> tags.
    //
    //
    // First, handle reference-style links: [link text] [id]
    //

    /*
		text = text.replace(/
		(							// wrap whole match in $1
			\[
			(
				(?:
					\[[^\]]*\]		// allow brackets nested one level
					|
					[^\[]			// or anything else
				)*
			)
			\]

			[ ]?					// one optional space
			(?:\n[ ]*)?				// one optional newline followed by spaces

			\[
			(.*?)					// id = $3
			\]
		)()()()()					// pad remaining backreferences
		/g,_DoAnchors_callback);
	*/
    text = text.replace(/(\[((?:\[[^\]]*\]|[^\[\]])*)\][ ]?(?:\n[ ]*)?\[(.*?)\])()()()()/g,writeAnchorTag);

    //
    // Next, inline-style links: [link text](url "optional title")
    //

    /*
		text = text.replace(/
			(						// wrap whole match in $1
				\[
				(
					(?:
						\[[^\]]*\]	// allow brackets nested one level
					|
					[^\[\]]			// or anything else
				)
			)
			\]
			\(						// literal paren
			[ \t]*
			()						// no id, so leave $3 empty
			<?(.*?)>?				// href = $4
			[ \t]*
			(						// $5
				(['"])				// quote char = $6
				(.*?)				// Title = $7
				\6					// matching quote
				[ \t]*				// ignore any spaces/tabs between closing quote and )
			)?						// title is optional
			\)
		)
		/g,writeAnchorTag);
	*/
    text = text.replace(/(\[((?:\[[^\]]*\]|[^\[\]])*)\]\([ \t]*()<?(.*?)>?[ \t]*((['"])(.*?)\6[ \t]*)?\))/g,writeAnchorTag);

    //
    // Last, handle reference-style shortcuts: [link text]
    // These must come last in case you've also got [link test][1]
    // or [link test](/foo)
    //

    /*
		text = text.replace(/
		(		 					// wrap whole match in $1
			\[
			([^\[\]]+)				// link text = $2; can't contain '[' or ']'
			\]
		)()()()()()					// pad rest of backreferences
		/g, writeAnchorTag);
	*/
    text = text.replace(/(\[([^\[\]]+)\])()()()()()/g, writeAnchorTag);

    return text;
  }

  var writeAnchorTag = function(wholeMatch,m1,m2,m3,m4,m5,m6,m7) {
    if (m7 == undefined) m7 = "";
    var whole_match = m1;
    var link_text   = m2;
    var link_id	 = m3.toLowerCase();
    var url		= m4;
    var title	= m7;

    if (url == "") {
      if (link_id == "") {
        // lower-case and turn embedded newlines into spaces
        link_id = link_text.toLowerCase().replace(/ ?\n/g," ");
      }
      url = "#"+link_id;

      if (g_urls[link_id] != undefined) {
        url = g_urls[link_id];
        if (g_titles[link_id] != undefined) {
          title = g_titles[link_id];
        }
      }
      else {
        if (whole_match.search(/\(\s*\)$/m)>-1) {
          // Special case for explicit empty url
          url = "";
        } else {
          return whole_match;
        }
      }
    }

    url = escapeCharacters(url,"*_");
    var result = "<a href=\"" + url + "\"";

    if (title != "") {
      title = title.replace(/"/g,"&quot;");
      title = escapeCharacters(title,"*_");
      result +=  " title=\"" + title + "\"";
    }

    result += ">" + link_text + "</a>";

    return result;
  }


  var _DoImages = function(text) {
    //
    // Turn Markdown image shortcuts into <img> tags.
    //

    //
    // First, handle reference-style labeled images: ![alt text][id]
    //

    /*
		text = text.replace(/
		(						// wrap whole match in $1
			!\[
			(.*?)				// alt text = $2
			\]

			[ ]?				// one optional space
			(?:\n[ ]*)?			// one optional newline followed by spaces

			\[
			(.*?)				// id = $3
			\]
		)()()()()				// pad rest of backreferences
		/g,writeImageTag);
	*/
    text = text.replace(/(!\[(.*?)\][ ]?(?:\n[ ]*)?\[(.*?)\])()()()()/g,writeImageTag);

    //
    // Next, handle inline images:  ![alt text](url "optional title")
    // Don't forget: encode * and _

    /*
		text = text.replace(/
		(						// wrap whole match in $1
			!\[
			(.*?)				// alt text = $2
			\]
			\s?					// One optional whitespace character
			\(					// literal paren
			[ \t]*
			()					// no id, so leave $3 empty
			<?(\S+?)>?			// src url = $4
			[ \t]*
			(					// $5
				(['"])			// quote char = $6
				(.*?)			// title = $7
				\6				// matching quote
				[ \t]*
			)?					// title is optional
		\)
		)
		/g,writeImageTag);
	*/
    text = text.replace(/(!\[(.*?)\]\s?\([ \t]*()<?(\S+?)>?[ \t]*((['"])(.*?)\6[ \t]*)?\))/g,writeImageTag);

    return text;
  }

  var writeImageTag = function(wholeMatch,m1,m2,m3,m4,m5,m6,m7) {
    var whole_match = m1;
    var alt_text   = m2;
    var link_id	 = m3.toLowerCase();
    var url		= m4;
    var title	= m7;

    if (!title) title = "";

    if (url == "") {
      if (link_id == "") {
        // lower-case and turn embedded newlines into spaces
        link_id = alt_text.toLowerCase().replace(/ ?\n/g," ");
      }
      url = "#"+link_id;

      if (g_urls[link_id] != undefined) {
        url = g_urls[link_id];
        if (g_titles[link_id] != undefined) {
          title = g_titles[link_id];
        }
      }
      else {
        return whole_match;
      }
    }

    alt_text = alt_text.replace(/"/g,"&quot;");
    url = escapeCharacters(url,"*_");
    var result = "<img src=\"" + url + "\" alt=\"" + alt_text + "\"";

    // attacklab: Markdown.pl adds empty title attributes to images.
    // Replicate this bug.

    //if (title != "") {
    title = title.replace(/"/g,"&quot;");
    title = escapeCharacters(title,"*_");
    result +=  " title=\"" + title + "\"";
    //}

    result += " />";

    return result;
  }


  var _DoHeaders = function(text) {

    // Setext-style headers:
    //	Header 1
    //	========
    //
    //	Header 2
    //	--------
    //
    text = text.replace(/^(.+)[ \t]*\n=+[ \t]*\n+/gm,
      function(wholeMatch,m1){
        return hashBlock("<h1>" + _RunSpanGamut(m1) + "</h1>");
      });

    text = text.replace(/^(.+)[ \t]*\n-+[ \t]*\n+/gm,
      function(matchFound,m1){
        return hashBlock("<h2>" + _RunSpanGamut(m1) + "</h2>");
      });

    // atx-style headers:
    //  # Header 1
    //  ## Header 2
    //  ## Header 2 with closing hashes ##
    //  ...
    //  ###### Header 6
    //

    /*
		text = text.replace(/
			^(\#{1,6})				// $1 = string of #'s
			[ \t]*
			(.+?)					// $2 = Header text
			[ \t]*
			\#*						// optional closing #'s (not counted)
			\n+
		/gm, function() {...});
	*/

    text = text.replace(/^(\#{1,6})[ \t]*(.+?)[ \t]*\#*\n+/gm,
      function(wholeMatch,m1,m2) {
        var h_level = m1.length;
        return hashBlock("<h" + h_level + ">" + _RunSpanGamut(m2) + "</h" + h_level + ">");
      });

    return text;
  }

  // This declaration keeps Dojo compressor from outputting garbage:
  var _ProcessListItems;

  var _DoLists = function(text) {
    //
    // Form HTML ordered (numbered) and unordered (bulleted) lists.
    //

    // attacklab: add sentinel to hack around khtml/safari bug:
    // http://bugs.webkit.org/show_bug.cgi?id=11231
    text += "~0";

    // Re-usable pattern to match any entirel ul or ol list:

    /*
		var whole_list = /
		(									// $1 = whole list
			(								// $2
				[ ]{0,3}					// attacklab: g_tab_width - 1
				([*+-]|\d+[.])				// $3 = first list item marker
				[ \t]+
			)
			[^\r]+?
			(								// $4
				~0							// sentinel for workaround; should be $
			|
				\n{2,}
				(?=\S)
				(?!							// Negative lookahead for another list item marker
					[ \t]*
					(?:[*+-]|\d+[.])[ \t]+
				)
			)
		)/g
	*/
    var whole_list = /^(([ ]{0,3}([*+-]|\d+[.])[ \t]+)[^\r]+?(~0|\n{2,}(?=\S)(?![ \t]*(?:[*+-]|\d+[.])[ \t]+)))/gm;

    if (g_list_level) {
      text = text.replace(whole_list,function(wholeMatch,m1,m2) {
        var list = m1;
        var list_type = (m2.search(/[*+-]/g)>-1) ? "ul" : "ol";

        // Turn double returns into triple returns, so that we can make a
        // paragraph for the last item in a list, if necessary:
        list = list.replace(/\n{2,}/g,"\n\n\n");
        ;
        var result = _ProcessListItems(list);

        // Trim any trailing whitespace, to put the closing `</$list_type>`
        // up on the preceding line, to get it past the current stupid
        // HTML block parser. This is a hack to work around the terrible
        // hack that is the HTML block parser.
        result = result.replace(/\s+$/,"");
        result = "<"+list_type+">" + result + "</"+list_type+">\n";
        return result;
      });
    } else {
      whole_list = /(\n\n|^\n?)(([ ]{0,3}([*+-]|\d+[.])[ \t]+)[^\r]+?(~0|\n{2,}(?=\S)(?![ \t]*(?:[*+-]|\d+[.])[ \t]+)))/g;
      text = text.replace(whole_list,function(wholeMatch,m1,m2,m3) {
        var runup = m1;
        var list = m2;

        var list_type = (m3.search(/[*+-]/g)>-1) ? "ul" : "ol";
        // Turn double returns into triple returns, so that we can make a
        // paragraph for the last item in a list, if necessary:
        var list = list.replace(/\n{2,}/g,"\n\n\n");
        ;
        var result = _ProcessListItems(list);
        result = runup + "<"+list_type+">\n" + result + "</"+list_type+">\n";
        return result;
      });
    }

    // attacklab: strip sentinel
    text = text.replace(/~0/,"");

    return text;
  }

  _ProcessListItems = function(list_str) {
    //
    //  Process the contents of a single ordered or unordered list, splitting it
    //  into individual list items.
    //
    // The $g_list_level global keeps track of when we're inside a list.
    // Each time we enter a list, we increment it; when we leave a list,
    // we decrement. If it's zero, we're not in a list anymore.
    //
    // We do this because when we're not inside a list, we want to treat
    // something like this:
    //
    //    I recommend upgrading to version
    //    8. Oops, now this line is treated
    //    as a sub-list.
    //
    // As a single paragraph, despite the fact that the second line starts
    // with a digit-period-space sequence.
    //
    // Whereas when we're inside a list (or sub-list), that line will be
    // treated as the start of a sub-list. What a kludge, huh? This is
    // an aspect of Markdown's syntax that's hard to parse perfectly
    // without resorting to mind-reading. Perhaps the solution is to
    // change the syntax rules such that sub-lists must start with a
    // starting cardinal number; e.g. "1." or "a.".

    g_list_level++;

    // trim trailing blank lines:
    list_str = list_str.replace(/\n{2,}$/,"\n");

    // attacklab: add sentinel to emulate \z
    list_str += "~0";

    /*
		list_str = list_str.replace(/
			(\n)?							// leading line = $1
			(^[ \t]*)						// leading whitespace = $2
			([*+-]|\d+[.]) [ \t]+			// list marker = $3
			([^\r]+?						// list item text   = $4
			(\n{1,2}))
			(?= \n* (~0 | \2 ([*+-]|\d+[.]) [ \t]+))
		/gm, function(){...});
	*/
    list_str = list_str.replace(/(\n)?(^[ \t]*)([*+-]|\d+[.])[ \t]+([^\r]+?(\n{1,2}))(?=\n*(~0|\2([*+-]|\d+[.])[ \t]+))/gm,
      function(wholeMatch,m1,m2,m3,m4){
        var item = m4;
        var leading_line = m1;
        var leading_space = m2;

        if (leading_line || (item.search(/\n{2,}/)>-1)) {
          item = _RunBlockGamut(_Outdent(item));
        }
        else {
          // Recursion for sub-lists:
          item = _DoLists(_Outdent(item));
          item = item.replace(/\n$/,""); // chomp(item)
          item = _RunSpanGamut(item);
        }

        return  "<li>" + item + "</li>\n";
      }
      );

    // attacklab: strip sentinel
    list_str = list_str.replace(/~0/g,"");

    g_list_level--;
    return list_str;
  }


  var _DoCodeBlocks = function(text) {
    //
    //  Process Markdown `<pre><code>` blocks.
    //

    /*
		text = text.replace(text,
			/(?:\n\n|^)
			(								// $1 = the code block -- one or more lines, starting with a space/tab
				(?:
					(?:[ ]{4}|\t)			// Lines must start with a tab or a tab-width of spaces - attacklab: g_tab_width
					.*\n+
				)+
			)
			(\n*[ ]{0,3}[^ \t\n]|(?=~0))	// attacklab: g_tab_width
		/g,function(){...});
	*/

    // attacklab: sentinel workarounds for lack of \A and \Z, safari\khtml bug
    text += "~0";

    text = text.replace(/(?:\n\n|^)((?:(?:[ ]{4}|\t).*\n+)+)(\n*[ ]{0,3}[^ \t\n]|(?=~0))/g,
      function(wholeMatch,m1,m2) {
        var codeblock = m1;
        var nextChar = m2;

        codeblock = _EncodeCode( _Outdent(codeblock));
        codeblock = _Detab(codeblock);
        codeblock = codeblock.replace(/^\n+/g,""); // trim leading newlines
        codeblock = codeblock.replace(/\n+$/g,""); // trim trailing whitespace

        codeblock = "<pre><code>" + codeblock + "\n</code></pre>";

        return hashBlock(codeblock) + nextChar;
      }
      );

    // attacklab: strip sentinel
    text = text.replace(/~0/,"");

    return text;
  }

  var hashBlock = function(text) {
    text = text.replace(/(^\n+|\n+$)/g,"");
    return "\n\n~K" + (g_html_blocks.push(text)-1) + "K\n\n";
  }


  var _DoCodeSpans = function(text) {
    //
    //   *  Backtick quotes are used for <code></code> spans.
    //
    //   *  You can use multiple backticks as the delimiters if you want to
    //	 include literal backticks in the code span. So, this input:
    //
    //		 Just type ``foo `bar` baz`` at the prompt.
    //
    //	   Will translate to:
    //
    //		 <p>Just type <code>foo `bar` baz</code> at the prompt.</p>
    //
    //	There's no arbitrary limit to the number of backticks you
    //	can use as delimters. If you need three consecutive backticks
    //	in your code, use four for delimiters, etc.
    //
    //  *  You can use spaces to get literal backticks at the edges:
    //
    //		 ... type `` `bar` `` ...
    //
    //	   Turns to:
    //
    //		 ... type <code>`bar`</code> ...
    //

    /*
		text = text.replace(/
			(^|[^\\])					// Character before opening ` can't be a backslash
			(`+)						// $2 = Opening run of `
			(							// $3 = The code block
				[^\r]*?
				[^`]					// attacklab: work around lack of lookbehind
			)
			\2							// Matching closer
			(?!`)
		/gm, function(){...});
	*/

    text = text.replace(/(^|[^\\])(`+)([^\r]*?[^`])\2(?!`)/gm,
      function(wholeMatch,m1,m2,m3,m4) {
        var c = m3;
        c = c.replace(/^([ \t]*)/g,"");	// leading whitespace
        c = c.replace(/[ \t]*$/g,"");	// trailing whitespace
        c = _EncodeCode(c);
        return m1+"<code>"+c+"</code>";
      });

    return text;
  }


  var _EncodeCode = function(text) {
    //
    // Encode/escape certain characters inside Markdown code runs.
    // The point is that in code, these characters are literals,
    // and lose their special Markdown meanings.
    //
    // Encode all ampersands; HTML entities are not
    // entities within a Markdown code span.
    text = text.replace(/&/g,"&amp;");

    // Do the angle bracket song and dance:
    text = text.replace(/</g,"&lt;");
    text = text.replace(/>/g,"&gt;");

    // Now, escape characters that are magic in Markdown:
    text = escapeCharacters(text,"\*_{}[]\\",false);

    // jj the line above breaks this:
    //---

    //* Item

    //   1. Subitem

    //            special char: *
    //---

    return text;
  }


  var _DoItalicsAndBold = function(text) {

    // <strong> must go first:
    text = text.replace(/(\*\*|__)(?=\S)([^\r]*?\S[*_]*)\1/g,
      "<strong>$2</strong>");

    text = text.replace(/(\w)_(\w)/g, "$1~E95E$2") // ** GFM **  "~E95E" == escaped "_"
    text = text.replace(/(\*|_)(?=\S)([^\r]*?\S)\1/g,
      "<em>$2</em>");

    return text;
  }


  var _DoBlockQuotes = function(text) {

    /*
		text = text.replace(/
		(								// Wrap whole match in $1
			(
				^[ \t]*>[ \t]?			// '>' at the start of a line
				.+\n					// rest of the first line
				(.+\n)*					// subsequent consecutive lines
				\n*						// blanks
			)+
		)
		/gm, function(){...});
	*/

    text = text.replace(/((^[ \t]*>[ \t]?.+\n(.+\n)*\n*)+)/gm,
      function(wholeMatch,m1) {
        var bq = m1;

        // attacklab: hack around Konqueror 3.5.4 bug:
        // "----------bug".replace(/^-/g,"") == "bug"

        bq = bq.replace(/^[ \t]*>[ \t]?/gm,"~0");	// trim one level of quoting

        // attacklab: clean up hack
        bq = bq.replace(/~0/g,"");

        bq = bq.replace(/^[ \t]+$/gm,"");		// trim whitespace-only lines
        bq = _RunBlockGamut(bq);				// recurse

        bq = bq.replace(/(^|\n)/g,"$1  ");
        // These leading spaces screw with <pre> content, so we need to fix that:
        bq = bq.replace(
          /(\s*<pre>[^\r]+?<\/pre>)/gm,
          function(wholeMatch,m1) {
            var pre = m1;
            // attacklab: hack around Konqueror 3.5.4 bug:
            pre = pre.replace(/^  /mg,"~0");
            pre = pre.replace(/~0/g,"");
            return pre;
          });

        return hashBlock("<blockquote>\n" + bq + "\n</blockquote>");
      });
    return text;
  }


  var _FormParagraphs = function(text) {
    //
    //  Params:
    //    $text - string to process with html <p> tags
    //

    // Strip leading and trailing lines:
    text = text.replace(/^\n+/g,"");
    text = text.replace(/\n+$/g,"");

    var grafs = text.split(/\n{2,}/g);
    var grafsOut = new Array();

    //
    // Wrap <p> tags.
    //
    var end = grafs.length;
    for (var i=0; i<end; i++) {
      var str = grafs[i];

      // if this is an HTML marker, copy it
      if (str.search(/~K(\d+)K/g) >= 0) {
        grafsOut.push(str);
      }
      else if (str.search(/\S/) >= 0) {
        str = _RunSpanGamut(str);
        str = str.replace(/\n/g,"<br />");  // ** GFM **
        str = str.replace(/^([ \t]*)/g,"<p>");
        str += "</p>"
        grafsOut.push(str);
      }

    }

    //
    // Unhashify HTML blocks
    //
    end = grafsOut.length;
    for (var i=0; i<end; i++) {
      // if this is a marker for an html block...
      while (grafsOut[i].search(/~K(\d+)K/) >= 0) {
        var blockText = g_html_blocks[RegExp.$1];
        blockText = blockText.replace(/\$/g,"$$$$"); // Escape any dollar signs
        grafsOut[i] = grafsOut[i].replace(/~K\d+K/,blockText);
      }
    }

    return grafsOut.join("\n\n");
  }


  var _EncodeAmpsAndAngles = function(text) {
    // Smart processing for ampersands and angle brackets that need to be encoded.

    // Ampersand-encoding based entirely on Nat Irons's Amputator MT plugin:
    //   http://bumppo.net/projects/amputator/
    text = text.replace(/&(?!#?[xX]?(?:[0-9a-fA-F]+|\w+);)/g,"&amp;");

    // Encode naked <'s
    text = text.replace(/<(?![a-z\/?\$!])/gi,"&lt;");

    return text;
  }


  var _EncodeBackslashEscapes = function(text) {
    //
    //   Parameter:  String.
    //   Returns:	The string, with after processing the following backslash
    //			   escape sequences.
    //

    // attacklab: The polite way to do this is with the new
    // escapeCharacters() function:
    //
    // 	text = escapeCharacters(text,"\\",true);
    // 	text = escapeCharacters(text,"`*_{}[]()>#+-.!",true);
    //
    // ...but we're sidestepping its use of the (slow) RegExp constructor
    // as an optimization for Firefox.  This function gets called a LOT.

    text = text.replace(/\\(\\)/g,escapeCharacters_callback);
    text = text.replace(/\\([`*_{}\[\]()>#+-.!])/g,escapeCharacters_callback);
    return text;
  }


  var _DoAutoLinks = function(text) {

    text = text.replace(/<((https?|ftp|dict):[^'">\s]+)>/gi,"<a href=\"$1\">$1</a>");

    // Email addresses: <address@domain.foo>

    /*
		text = text.replace(/
			<
			(?:mailto:)?
			(
				[-.\w]+
				\@
				[-a-z0-9]+(\.[-a-z0-9]+)*\.[a-z]+
			)
			>
		/gi, _DoAutoLinks_callback());
	*/
    text = text.replace(/<(?:mailto:)?([-.\w]+\@[-a-z0-9]+(\.[-a-z0-9]+)*\.[a-z]+)>/gi,
      function(wholeMatch,m1) {
        return _EncodeEmailAddress( _UnescapeSpecialChars(m1) );
      }
      );

    return text;
  }


  var _EncodeEmailAddress = function(addr) {
    //
    //  Input: an email address, e.g. "foo@example.com"
    //
    //  Output: the email address as a mailto link, with each character
    //	of the address encoded as either a decimal or hex entity, in
    //	the hopes of foiling most address harvesting spam bots. E.g.:
    //
    //	<a href="&#x6D;&#97;&#105;&#108;&#x74;&#111;:&#102;&#111;&#111;&#64;&#101;
    //	   x&#x61;&#109;&#x70;&#108;&#x65;&#x2E;&#99;&#111;&#109;">&#102;&#111;&#111;
    //	   &#64;&#101;x&#x61;&#109;&#x70;&#108;&#x65;&#x2E;&#99;&#111;&#109;</a>
    //
    //  Based on a filter by Matthew Wickline, posted to the BBEdit-Talk
    //  mailing list: <http://tinyurl.com/yu7ue>
    //

    // attacklab: why can't javascript speak hex?
    function char2hex(ch) {
      var hexDigits = '0123456789ABCDEF';
      var dec = ch.charCodeAt(0);
      return(hexDigits.charAt(dec>>4) + hexDigits.charAt(dec&15));
    }

    var encode = [
    function(ch){
      return "&#"+ch.charCodeAt(0)+";";
    },
    function(ch){
      return "&#x"+char2hex(ch)+";";
    },
    function(ch){
      return ch;
    }
    ];

    addr = "mailto:" + addr;

    addr = addr.replace(/./g, function(ch) {
      if (ch == "@") {
        // this *must* be encoded. I insist.
        ch = encode[Math.floor(Math.random()*2)](ch);
      } else if (ch !=":") {
        // leave ':' alone (to spot mailto: later)
        var r = Math.random();
        // roughly 10% raw, 45% hex, 45% dec
        ch =  (
          r > .9  ?	encode[2](ch)   :
          r > .45 ?	encode[1](ch)   :
          encode[0](ch)
          );
      }
      return ch;
    });

    addr = "<a href=\"" + addr + "\">" + addr + "</a>";
    addr = addr.replace(/">.+:/g,"\">"); // strip the mailto: from the visible part

    return addr;
  }


  var _UnescapeSpecialChars = function(text) {
    //
    // Swap back in all the special characters we've hidden.
    //
    text = text.replace(/~E(\d+)E/g,
      function(wholeMatch,m1) {
        var charCodeToReplace = parseInt(m1);
        return String.fromCharCode(charCodeToReplace);
      }
      );
    return text;
  }


  var _Outdent = function(text) {
    //
    // Remove one level of line-leading tabs or spaces
    //

    // attacklab: hack around Konqueror 3.5.4 bug:
    // "----------bug".replace(/^-/g,"") == "bug"

    text = text.replace(/^(\t|[ ]{1,4})/gm,"~0"); // attacklab: g_tab_width

    // attacklab: clean up hack
    text = text.replace(/~0/g,"")

    return text;
  }

  var _Detab = function(text) {
    // attacklab: Detab's completely rewritten for speed.
    // In perl we could fix it by anchoring the regexp with \G.
    // In javascript we're less fortunate.

    // expand first n-1 tabs
    text = text.replace(/\t(?=\t)/g,"    "); // attacklab: g_tab_width

    // replace the nth with two sentinels
    text = text.replace(/\t/g,"~A~B");

    // use the sentinel to anchor our regex so it doesn't explode
    text = text.replace(/~B(.+?)~A/g,
      function(wholeMatch,m1,m2) {
        var leadingText = m1;
        var numSpaces = 4 - leadingText.length % 4;  // attacklab: g_tab_width

        // there *must* be a better way to do this:
        for (var i=0; i<numSpaces; i++) leadingText+=" ";

        return leadingText;
      }
      );

    // clean up sentinels
    text = text.replace(/~A/g,"    ");  // attacklab: g_tab_width
    text = text.replace(/~B/g,"");

    return text;
  }


  //
  //  attacklab: Utility functions
  //


  var escapeCharacters = function(text, charsToEscape, afterBackslash) {
    // First we have to escape the escape characters so that
    // we can build a character class out of them
    var regexString = "([" + charsToEscape.replace(/([\[\]\\])/g,"\\$1") + "])";

    if (afterBackslash) {
      regexString = "\\\\" + regexString;
    }

    var regex = new RegExp(regexString,"g");
    text = text.replace(regex,escapeCharacters_callback);

    return text;
  }


  var escapeCharacters_callback = function(wholeMatch,m1) {
    var charCodeToEscape = m1.charCodeAt(0);
    return "~E"+charCodeToEscape+"E";
  }

} // end of Showdown.converter
