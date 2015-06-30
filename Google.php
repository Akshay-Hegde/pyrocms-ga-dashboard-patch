<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');
set_include_path(APPPATH . 'libraries/' . PATH_SEPARATOR . get_include_path());
require_once APPPATH . 'libraries/Google/autoload.php';

class Google extends Google_Client {
    function __construct($params = array()) {
        parent::__construct();
    }
}

