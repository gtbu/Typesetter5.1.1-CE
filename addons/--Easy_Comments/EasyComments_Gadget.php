<?php

defined('is_running') or die('Not an entry point...');

require_once('EasyComments.php');

class EasyComments_Gadget extends EasyComments{

	function __construct(){
		$this->Init();
		$this->Run();
	}
	
	function EasyComments_Gadget()
    {
        self::__construct();
    }
	

}
