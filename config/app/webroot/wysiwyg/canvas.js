  var selected_image = null;
  function clickImages(){
    var images = $$('img');
    images.removeEvents();
    images.addEvent('click', function(event){
      selected_image = this;
      event.stopPropagation();
    });
  }
  function selectedImage(param){
    if (param && selected_image) {
      selected_image.setStyle('float', param);
      return true;
    }
    return selected_image;
  }
  window.addEvent('domready', function(){
    $(document.body).addEvent('click', function(){
      selected_image = null;
    })
  });