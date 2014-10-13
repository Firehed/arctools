<?php

/**
 * Normally, this file would be generated automatically with `arc liberate`;
 * however this set of libraries is designed to work nicely with Composer to
 * bridge the two. This takes a rather straightforward approach of requiring
 * the vendor/autoload.php file generated by Composer using Arcanist's format.
 */

foreach ([
    __DIR__ . '/../../autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
}
return false;