#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use App\Command\ProcessFullJsonCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ProcessFullJsonCommand());
$application->run();
