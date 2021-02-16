<?php

require_once 'vendor/autoload.php';

$auth = new \Services\Auth();
;
echo '<pre>'; print_r($auth->getToken());die;