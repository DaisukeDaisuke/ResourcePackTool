<?php
declare(strict_types=1);

namespace resourceTools;

require_once __DIR__.'/../vendor/autoload.php';

use pocketmine\errorhandler\ErrorToExceptionHandler;
use Symfony\Component\Console\Application;
use resourceTools\command\encrypt;
use resourceTools\command\decrypt;
use resourceTools\command\DecryptRecursive;
use resourceTools\command\DebugContent;

//check openssl extension
if(!extension_loaded("openssl")){
    echo "error: openssl extension not found".PHP_EOL;
    exit(1);
}

ErrorToExceptionHandler::set();

// アプリケーションを作成する
$application = new Application();
// コマンドを登録する
$application->add(new encrypt());
$application->add(new decrypt());
$application->add(new DecryptRecursive());
$application->add(new DebugContent());
// アプリケーションを実行する
$application->run();
