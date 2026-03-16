<?php

// Register worktree-specific autoload paths BEFORE loading the standard autoloader.
// This is needed because vendor/ is a symlink to the main numen repo, but we want
// classes from this worktree's app/ directory to take precedence.

// Load the standard autoloader first (symlinked from main repo)
require_once __DIR__.'/../vendor/autoload.php';

// Now prepend this worktree's app paths so they take precedence over the main repo's
$loader = new \Composer\Autoload\ClassLoader;
$loader->addPsr4('App\\', __DIR__.'/../app/');
$loader->addPsr4('Database\\Factories\\', __DIR__.'/../database/factories/');
$loader->addPsr4('Database\\Seeders\\', __DIR__.'/../database/seeders/');
$loader->addPsr4('Tests\\', __DIR__.'/../tests/');
$loader->register(true); // true = prepend (add to front of autoloader stack)
