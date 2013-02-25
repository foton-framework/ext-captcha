<?php



class EXT_COM_Captcha
{

	function index()
	{
		sys::$lib->load->extension('captcha')->generate_image();
	}
	
}