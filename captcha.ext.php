<?php



class EXT_Captcha
{
	//--------------------------------------------------------------------------

	public $foreground_color = '000';
	public $background_color = 'FFF';
	public $allowed_symbols  = '0123456789';
	public $length           = array(3,6);
	public $width            = 100;
	public $height           = 50;
	public $amplitude        = 5;
	public $no_spaces        = false;
	public $jpeg_quality     = 90;

	public $field_name  = 'captcha';
	public $field_label = 'Защитный код';

	//--------------------------------------------------------------------------

	public function __construct()
	{
		! session_id() AND session_start();
		sys::set_config_items(&$this, 'captcha');
	}

	//--------------------------------------------------------------------------

	public function init()
	{
		lib('form')->set_field($this->field_name , 'input', $this->field_label, 'trim|strip_tags|required|callback[ext.captcha.validation]');
	}

	//--------------------------------------------------------------------------

	public function field($extra = '')
	{
		lib('form')->set_value($this->field_name, '', TRUE);
		return lib('form')->field($this->field_name, '', $extra);
	}

	//--------------------------------------------------------------------------

	public function label()
	{
		return lib('form')->label($this->field_name);
	}

	//--------------------------------------------------------------------------

	public function image($extra = '')
	{
		if ($extra) $extra = ' ' . $extra;
		return "<img src='/captcha/' alt=''{$extra} />";
	}

	//--------------------------------------------------------------------------

	public function validation(&$value)
	{
		if (isset($_SESSION[$this->field_name()]))
		{
			$result = $_SESSION[$this->field_name()] == $value;

			$value = '';

			if (isset(sys::$lib->form))
			{
				lib('form')->set_error_message('callback[ext.captcha.validation]', 'Не верный защитный код');
			}

			return $result;
		}

		return FALSE;
	}

	//--------------------------------------------------------------------------

	public function field_name()
	{
		return $this->field_name;
	}

	//--------------------------------------------------------------------------

	private function _hex2rgb($hex)
	{
		if ($hex{0} == '#') $hex = substr($hex, 1);
		if (strlen($hex) == 3) $hex .= $hex;
		$rgb = str_split($hex, 2);
		foreach ($rgb as &$row) $row = hexdec($row);
		return $rgb;
	}

	//--------------------------------------------------------------------------

	function generate_image(){

		$alphabet = "0123456789abcdefghijklmnopqrstuvwxyz"; # do not change without changing font files!

		# symbols used to draw CAPTCHA
		$allowed_symbols = $this->allowed_symbols; #digits
		//$allowed_symbols = "23456789abcdeghkmnpqsuvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)

		# folder with fonts
		$fontsdir = 'fonts';

		# CAPTCHA string length
		$length = mt_rand($this->length[0], $this->length[1]);
		//$length = 6;

		# CAPTCHA image size (you do not need to change it, whis parameters is optimal)
		$width  = $this->width;
		$height = $this->height;

		# symbol's vertical fluctuation amplitude divided by 2
		$fluctuation_amplitude = $this->amplitude;

		# increase safety by prevention of spaces between symbols
		$no_spaces = $this->no_spaces;

		# show credits
		$show_credits = false; # set to false to remove credits line. Credits adds 12 pixels to image height
		$credits = ''; # if empty, HTTP_HOST will be shown

		# CAPTCHA image colors (RGB, 0-255)
		$foreground_color = $this->_hex2rgb($this->foreground_color);
		$background_color = $this->_hex2rgb($this->background_color);
		//$foreground_color = array(mt_rand(0,100), mt_rand(0,100), mt_rand(0,100));
		//$background_color = array(mt_rand(200,255), mt_rand(200,255), mt_rand(200,255));

		# JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
		$jpeg_quality = $this->jpeg_quality;

		$fonts=array();
		$fontsdir_absolute=EXT_PATH .'captcha/'.$fontsdir;
		if ($handle = opendir($fontsdir_absolute)) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match('/\.png$/i', $file)) {
					$fonts[]=$fontsdir_absolute.'/'.$file;
				}
			}
		    closedir($handle);
		}

		$alphabet_length=strlen($alphabet);

		do{
			// generating random key_string
			while(true){
				$this->key_string='';
				for($i=0;$i<$length;$i++){
					$this->key_string.=$allowed_symbols{mt_rand(0,strlen($allowed_symbols)-1)};
				}
				if(!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->key_string)) break;
			}

			$_SESSION[$this->field_name()] = $this->key_string;

			$font_file=$fonts[mt_rand(0, count($fonts)-1)];
			$font=imagecreatefrompng($font_file);
			imagealphablending($font, true);
			$fontfile_width=imagesx($font);
			$fontfile_height=imagesy($font)-1;
			$font_metrics=array();
			$symbol=0;
			$reading_symbol=false;

			// loading font
			for($i=0;$i<$fontfile_width && $symbol<$alphabet_length;$i++){
				$transparent = (imagecolorat($font, $i, 0) >> 24) == 127;

				if(!$reading_symbol && !$transparent){
					$font_metrics[$alphabet{$symbol}]=array('start'=>$i);
					$reading_symbol=true;
					continue;
				}

				if($reading_symbol && $transparent){
					$font_metrics[$alphabet{$symbol}]['end']=$i;
					$reading_symbol=false;
					$symbol++;
					continue;
				}
			}

			$img=imagecreatetruecolor($width, $height);
			imagealphablending($img, true);
			$white=imagecolorallocate($img, 255, 255, 255);
			$black=imagecolorallocate($img, 0, 0, 0);

			imagefilledrectangle($img, 0, 0, $width-1, $height-1, $white);

			// draw text
			$x=1;
			for($i=0;$i<$length;$i++){
				$m=$font_metrics[$this->key_string{$i}];

				$y=mt_rand(-$fluctuation_amplitude, $fluctuation_amplitude)+($height-$fontfile_height)/2+2;

				if($no_spaces){
					$shift=0;
					if($i>0){
						$shift=10000;
						for($sy=7;$sy<$fontfile_height-20;$sy+=1){
							for($sx=$m['start']-1;$sx<$m['end'];$sx+=1){
				        		$rgb=imagecolorat($font, $sx, $sy);
				        		$opacity=$rgb>>24;
								if($opacity<127){
									$left=$sx-$m['start']+$x;
									$py=$sy+$y;
									if($py>$height) break;
									for($px=min($left,$width-1);$px>$left-12 && $px>=0;$px-=1){
						        		$color=imagecolorat($img, $px, $py) & 0xff;
										if($color+$opacity<190){
											if($shift>$left-$px){
												$shift=$left-$px;
											}
											break;
										}
									}
									break;
								}
							}
						}
						if($shift==10000){
							$shift=mt_rand(4,6);
						}

					}
				}else{
					$shift=1;
				}
				imagecopy($img, $font, $x-$shift, $y, $m['start'], 1, $m['end']-$m['start'], $fontfile_height);
				$x+=$m['end']-$m['start']-$shift;
			}
		}while($x>=$width-10); // while not fit in canvas

		$center=$x/2;

		// credits. To remove, see configuration file
		$img2=imagecreatetruecolor($width, $height+($show_credits?12:0));
		$foreground=imagecolorallocate($img2, $foreground_color[0], $foreground_color[1], $foreground_color[2]);
		$background=imagecolorallocate($img2, $background_color[0], $background_color[1], $background_color[2]);
		imagefilledrectangle($img2, 0, 0, $width-1, $height-1, $background);
		imagefilledrectangle($img2, 0, $height, $width-1, $height+12, $foreground);
		$credits=empty($credits)?$_SERVER['HTTP_HOST']:$credits;
		imagestring($img2, 2, $width/2-imagefontwidth(2)*strlen($credits)/2, $height-2, $credits, $background);

		// periods
		$rand1=mt_rand(750000,1200000)/10000000;
		$rand2=mt_rand(750000,1200000)/10000000;
		$rand3=mt_rand(750000,1200000)/10000000;
		$rand4=mt_rand(750000,1200000)/10000000;
		// phases
		$rand5=mt_rand(0,31415926)/10000000;
		$rand6=mt_rand(0,31415926)/10000000;
		$rand7=mt_rand(0,31415926)/10000000;
		$rand8=mt_rand(0,31415926)/10000000;
		// amplitudes
		$rand9=mt_rand(330,420)/110;
		$rand10=mt_rand(330,450)/110;

		//wave distortion

		for($x=0;$x<$width;$x++){
			for($y=0;$y<$height;$y++){
				$sx=$x+(sin($x*$rand1+$rand5)+sin($y*$rand3+$rand6))*$rand9-$width/2+$center+1;
				$sy=$y+(sin($x*$rand2+$rand7)+sin($y*$rand4+$rand8))*$rand10;

				if($sx<0 || $sy<0 || $sx>=$width-1 || $sy>=$height-1){
					continue;
				}else{
					$color=imagecolorat($img, $sx, $sy) & 0xFF;
					$color_x=imagecolorat($img, $sx+1, $sy) & 0xFF;
					$color_y=imagecolorat($img, $sx, $sy+1) & 0xFF;
					$color_xy=imagecolorat($img, $sx+1, $sy+1) & 0xFF;
				}

				if($color==255 && $color_x==255 && $color_y==255 && $color_xy==255){
					continue;
				}else if($color==0 && $color_x==0 && $color_y==0 && $color_xy==0){
					$newred=$foreground_color[0];
					$newgreen=$foreground_color[1];
					$newblue=$foreground_color[2];
				}else{
					$frsx=$sx-floor($sx);
					$frsy=$sy-floor($sy);
					$frsx1=1-$frsx;
					$frsy1=1-$frsy;

					$newcolor=(
						$color*$frsx1*$frsy1+
						$color_x*$frsx*$frsy1+
						$color_y*$frsx1*$frsy+
						$color_xy*$frsx*$frsy);

					if($newcolor>255) $newcolor=255;
					$newcolor=$newcolor/255;
					$newcolor0=1-$newcolor;

					$newred=$newcolor0*$foreground_color[0]+$newcolor*$background_color[0];
					$newgreen=$newcolor0*$foreground_color[1]+$newcolor*$background_color[1];
					$newblue=$newcolor0*$foreground_color[2]+$newcolor*$background_color[2];
				}

				imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
			}
		}

		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', FALSE);
		header('Pragma: no-cache');

		if(function_exists("imagejpeg")){
			header("Content-Type: image/jpeg");
			imagejpeg($img2, null, $jpeg_quality);
		}else if(function_exists("imagegif")){
			header("Content-Type: image/gif");
			imagegif($img2);
		}else if(function_exists("imagepng")){
			header("Content-Type: image/x-png");
			imagepng($img2);
		}
	}

	// returns key_string
	function key_string(){
		return $this->key_string;
	}
}