<?php
if(isset($_FILES['file'])){

	$type = substr($_FILES['file']['name'],strrpos($_FILES['file']['name'],'.'));
	$image_name = time().$type;

	$path = './img/';

	if(!copy($_FILES['file']['tmp_name'],$path.$image_name)){
		$image_name = 'no';
	}

}else{
	$image_name = 'nop';
}
?>
<html><head></head><body><?php echo $image_name; ?></body></html>