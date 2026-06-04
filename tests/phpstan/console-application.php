<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$_SERVER['DEFAULT_URI'] ??= 'http://localhost';
$_ENV['DEFAULT_URI'] ??= 'http://localhost';
putenv('DEFAULT_URI=http://localhost');

$kernel = new Kernel('dev', true);
$kernel->boot();

return new Application($kernel);
