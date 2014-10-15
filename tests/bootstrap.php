<?php

foreach ([
    __DIR__.'/../vendor/autoload.php', // This project
    __DIR__.'/../../../vendor/autoload.php', // Anything using this project
    ] as $autoloader) {
    if (file_exists($autoloader)) {
        require_once $autoloader;
    }
}

// The include_path should have had libphutil's path appended when this file is
// included from the `arc unit` workflow.
@include_once 'libphutil/scripts/__init_script__.php';
if (!@constant('__LIBPHUTIL__')) {
    echo "ERROR: Unable to load libphutil. Update your PHP 'include_path' to ".
         "include the parent directory of libphutil/.\n";
    exit(1);
}

phutil_load_library('arcanist/src');

// Fake out the interaction between Composer and libphutil's autoloaders:
// Composer always prepends its loader, and libphutil will throw an exception
// if a class is not found if there is no loader that runs after its own. This
// adds a do-nothing loader after libphutil's to avoid the `throw`. Mostly,
// this avoids some bogus `class_exists` failures in PHPUnit itself.
spl_autoload_register(function($class) {});
