#!/usr/bin/env php
<?php

// load composer autoload
if (!file_exists(getcwd().'/vendor/autoload.php')) {
    exit("You must run the command from the project root dir.\n");
}
require_once getcwd().'/vendor/autoload.php';

if (!file_exists(getcwd().'/beanworker.php')) {
    exit("The bootstrap file `beanworker.php` must put into the project root dir and you must run the command from there.\n");
}
$container = require_once getcwd().'/beanworker.php';