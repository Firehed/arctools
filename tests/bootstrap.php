<?php

require_once __DIR__.'/../vendor/autoload.php';

$include_path = ini_get('include_path');
ini_set(
    'include_path',
    $include_path.PATH_SEPARATOR.dirname(__FILE__).'/../../');
@include_once 'libphutil/scripts/__init_script__.php';
if (!@constant('__LIBPHUTIL__')) {
    echo "ERROR: Unable to load libphutil. Update your PHP 'include_path' to ".
         "include the parent directory of libphutil/.\n";
    exit(1);
}

phutil_load_library('arcanist/src');

// Fake out the interaction between Composer and libphutil's autoloaders:
spl_autoload_register(function($class) {
//    var_dump($class);
});
echo 'hi';
