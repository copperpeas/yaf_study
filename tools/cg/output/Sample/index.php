<?php
// var_dump(__LINE__);
define('APPLICATION_PATH', dirname(__FILE__));
// var_dump(APPLICATION_PATH);
$application = new Yaf\Application( APPLICATION_PATH . "/conf/application.ini");
// var_dump(__FUNCTION__);
// var_dump($application);
// var_dump($application->bootstrap());exit;

$application->bootstrap()->run();
?>
