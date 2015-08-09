<?php

class AddTextWaterMark {
	
	var $photo = null,
		$output_width = 0,
		$output_height = 0,
		$total_colours = 0,
		$text_colour = null,
		$source_image = null,
		$watermark_path = null,
		$watermark_image = null,
		$settings = array(),
		$text = 'bigwideworld.ch |',
		$font = './Fonts/Bitter-Bold.ttf',
		$text_size = '15',
		$text_width = 0,
		$text_height = 0;

	//////////////////////////////////////////////////

	function __construct(){
		
		require( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );

		$this->settings = get_option('mhm_imagehandling_settings');
		
		$this->getSource();

		if( $this->settings['image_url']!=='' && (bool)$this->settings['activate'] ){
			if( !intval($this->settings['minimumsize']) ||  $this->source_width > intval($this->settings['minimumsize']) ){
				$this->getWatermarkImage();
				$this->addWatermarkImage();
				/*
				$this->text .= ' '.date('Y');
				$this->setTextSize();
				$this->getTextWidth();
		
				if($this->text_size > 0){
					$this->getAverageColour();
					$this->setTextColour();
					//$this->addWatermarkBackground();
					$this->addWatermarkText();
				}
				*/
			}
		}
		$this->sendImageToBrowser();

	}

	//////////////////////////////////////////////////

	private function fail($statuscode){
		switch($statuscode){
			case '404':
			    header('HTTP/1.0 404 Not Found');
				die('File not found');
			break;
		}
	}//fail

	//////////////////////////////////////////////////
	
	private function dump($var,$die=false){
		echo '<pre>' .print_r($var,1). '</pre>';
		if($die){die();}
	}

	//////////////////////////////////////////////////
	
	private function addWatermarkBackground(){

		$text_bg_color = imagecolorallocatealpha($this->photo, 255, 255, 255, 85);
		
		$text_bottom = $this->output_height-$this->text_height;
		
		$rectangle_x1 = 0;
		$rectangle_y1 = $this->output_height-$this->text_height;
		$rectangle_x2 = $this->text_size/2 + $this->text_width;
		$rectangle_y2 = $this->output_height;
		
		imagefilledrectangle($this->photo, $rectangle_x1, $rectangle_y1, $rectangle_x2, $rectangle_y2, $text_bg_color);
	}

	//////////////////////////////////////////////////

	private function addWatermarkImage(){
		
		if($this->photo && $this->watermark_image){
			
 			$watermarkFileW = imagesx($this->watermark_image);
 			$watermarkFileH = imagesy($this->watermark_image);
 			
 			$watermarkFileProportion = $watermarkFileW / $watermarkFileH;
 			
 			$resizefactor = intval($this->settings['resizepercent']) ? intval($this->settings['resizepercent']) : 10;

 			$watermarkW = $this->source_width / (100 / $resizefactor);
 			$watermarkH = $watermarkW / $watermarkFileProportion;

 			// create temporary file for resized watermark
 			// transparent colour fill explicitly required so that
 			// the image is anti-aliased on resize
 			$image = imagecreatetruecolor($watermarkW, $watermarkH);
 			imagealphablending($image, true);
 			imagesavealpha($image, true);
 			$black = imagecolorallocatealpha($image, 0, 0, 0, 127);
 			imagefill($image, 0, 0, $black);

 			// make resized watermark image
 			imagecopyresampled($image, $this->watermark_image, 0, 0, 0, 0, $watermarkW, $watermarkH, $watermarkFileW, $watermarkFileH);

			// copy resized watermark image into source image
			imagecopy($this->photo, $image, ($this->source_width - $watermarkW) - ($watermarkW*.05), ($this->source_height - $watermarkH) - ($watermarkH*.05), 0, 0, $watermarkW, $watermarkH);

		}
		
	}

	//////////////////////////////////////////////////
	
	private function addWatermarkText(){
		
		$img = imagecreatetruecolor($this->text_width, $this->text_height);

		imagealphablending($img, false);
		imagesavealpha($img, true);
		$grey = imagecolorallocatealpha($img, 128,128,128,110);
		$white = imagecolorallocate($img, 255,255,255);
		imagefilledrectangle($img, 0, 0, $this->text_width, $this->text_height, $grey);
		
		$textDim = imagettfbbox($this->text_size, 0, $this->font, $this->text);
		$textX = $textDim[2] - $textDim[0];
		$textY = $textDim[7] - $textDim[1];
		
		$text_posX = ($this->text_width / 2) - ($textX / 2);
		$text_posY = ($this->text_height / 2) - ($textY / 2);
		
		imagealphablending($img, true);
		imagettftext($img, $this->text_size, 0, $text_posX+1, $text_posY+1, $grey, $this->font, $this->text);
		imagettftext($img, $this->text_size, 0, $text_posX, $text_posY, $white, $this->font, $this->text);
		
		imagecopy($this->photo, $img, $this->output_width-$this->text_width, $this->output_height-$this->text_height, 0, 0, $this->text_width, $this->text_height);

	}

	//////////////////////////////////////////////////
	
	private function getAverageColour(){
		
		/*
		 * Take a sample 1/5th of the size of the original 
		 * image (bottom left-hand-corner) and calculate the average colour
		 */

		$samplearea_width = $this->source_width/5;
		$samplearea_height = $this->source_height/5;
		$samplearea_x = $this->source_width-$this->text_width;
		$samplearea_y = $this->source_height - $samplearea_height;
		
		$destination_x = 0;
		$destination_y = 0;
		$destination_w = 1;
		$destination_h = 1;
		
		$measuring_image = imagecreatetruecolor($this->output_width, $this->output_height);

		imagecopyresampled ( $measuring_image , $this->photo , $destination_x , $destination_y , $samplearea_x , $samplearea_y , $destination_w , $destination_h , $samplearea_width , $samplearea_height );
		
		$rgb = imagecolorat($measuring_image, 0, 0);
		$colours = imagecolorsforindex($measuring_image, $rgb);
		$this->total_colours = array_sum($colours);

		imagedestroy($measuring_image);

	}

	//////////////////////////////////////////////////
	
	private function sendImageToBrowser(){
		header('Content-Type: image/jpeg');
		imagejpeg($this->photo, null, 92);
		imagedestroy($this->photo);
		die();
	}

	//////////////////////////////////////////////////
	
	private function setTextColour(){
		if($this->total_colours>700){
			$this->text_colour = imagecolorallocatealpha($this->photo, 50, 50, 50, 50);
		}elseif($this->total_colours>600){
			$this->text_colour = imagecolorallocatealpha($this->photo, 50, 50, 50, 0);
		}elseif($this->total_colours>500){
			$this->text_colour = imagecolorallocate($this->photo, 50, 50, 50);
		}elseif($this->total_colours>250){
			$this->text_colour = imagecolorallocate($this->photo, 255, 255, 255);
		}else{
			$this->text_colour = imagecolorallocatealpha($this->photo, 255, 255, 255, 65);
		}
	}

	//////////////////////////////////////////////////
	
	private function getTextWidth(){
		$dims = imagettfbbox($this->text_size, 0, $this->font, $this->text);
		$this->text_width = ($dims[4] - $dims[6]) + 2*$this->text_size;
		$this->text_height = $dims[3] - $dims[5] + 2*$this->text_size;
	}

	//////////////////////////////////////////////////
	
	private function setTextSize(){
		if($this->source_width < 300){
			$this->text_size = '0.1';
		}elseif($this->source_width < 1601){
			$this->text_size = '11';
		}else{
			$this->text_size = '13';
		}
	}

	//////////////////////////////////////////////////

	private function getSource(){

		$this->source_path = $_SERVER['DOCUMENT_ROOT'].$_SERVER['REQUEST_URI'];
		
		if( !file_exists($this->source_path) ){
			$this->fail('404');
		}
		
		$this->photo = imagecreatefromjpeg($this->source_path);

		list($width, $height) = getimagesize($this->source_path);
		$this->source_width = $this->output_width = $width;
		$this->source_height = $this->output_height = $height;
	}

	//////////////////////////////////////////////////

	private function getWatermarkImage(){

		if( isset($this->settings['image_url']) && $this->settings['image_url']!==''){

			$this->watermark_path = str_replace('http://'.$_SERVER['HTTP_HOST'], $_SERVER['DOCUMENT_ROOT'], $this->settings['image_url']);
	
			if( !file_exists( $this->watermark_path ) ){
				error_log('mhm-imagehandling - file "' .$this->watermark_path. '" does not exist');
				return;
			}
	
			if( !(exif_imagetype($this->watermark_path) === IMAGETYPE_PNG) ){
				error_log('mhm-imagehandling - file "' .$this->watermark_path. '" is not a PNG file');
				return;
			}

			$this->watermark_image = imagecreatefrompng($this->watermark_path);
			$background = imagecolorallocate($this->watermark_image, 0, 0, 0);
	        imagecolortransparent($this->watermark_image, $background);
	        imagealphablending($this->watermark_image, false);
	        imagesavealpha($this->watermark_image, true);

		}
	}

}		

new AddTextWaterMark();