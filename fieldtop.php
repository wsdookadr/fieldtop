<?php

include('fieldtop_config.php');
include('lib/fieldtop.php');

if (php_sapi_name() == 'cli') $mode='cli';
else                          $mode='html';

$o = new DBOverflowCheck($mode);
$o->connectDB($userPass['server'],$userPass['user'],$userPass['pass'],'information_schema');
$o->check();
$o->show($mode);


