<?php
declare(strict_types=1);

/*
Portable program to decrypt resource packs
*/

if(version_compare(phpversion(), '8.0.0', '<')){
	echo "PHP 8.0.0 or higher is required to run this script. You are using PHP ".phpversion().PHP_EOL;
	exit(1);
}

//check opelssl ext
if(!extension_loaded("openssl")){
	echo "openssl extension is required to run this script.".PHP_EOL;
	exit(1);
}

[$path, $contentKey, $outputdir] = readOptions($argv);

$test = file_get_contents($path."contents.json");
$test = substr($test, 0x100);

$content = openssl_decrypt($test, "AES-256-CFB8", $contentKey, OPENSSL_RAW_DATA , substr($contentKey, 0, 16));
try{
    $test = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
}catch(\JsonException $e){
    echo "final: maybe key incorrect!\n";
    exit;
}
foreach($test["content"] as $item){
    $target = $item["path"] ?? null;
    if($target[0] === "/"||$target[0] === "\\"||str_contains($target, "..")){
        throw new \RUntimeException("Invalid path(Unable to write to the parent directory for security reasons): ".$target);
    }
    if($target === null){
        echo "Path not found in ".$item["path"]."\n";
        continue;
    }
    if(!isset($item["key"])){
        if($target === "manifest.json"||$target === "pack_icon.png"||$target === "contents.json"){
            continue;
        }
        echo "copy: ".$target."\n";
        copy($path.$target, $outputdir.$target);
        continue;
    }
    $key = $item["key"];
    $output = $outputdir.$target;
    echo $target," with ",$key,"\n";
	$test = file_get_contents($path.DIRECTORY_SEPARATOR.$target);
	$content1 = openssl_decrypt($test, "AES-256-CFB8", $key, OPENSSL_RAW_DATA , substr($key, 0, 16));
	@mkdir(dirname($output), 0777, true);
	file_put_contents($output, $content1);
	//break;
}
echo "done!\n";
exit;

//readOption
function readOptions(array $argv){
	unset($argv[0]);
	if(!isset($argv[1])){
		help();
	}
	
	$argv = array_values($argv);
	$key = readShortOpt($argv, "k") ?? null;
	$output = readShortOpt($argv, "o") ?? "encrypted";
	//key must 32bytes
	$path = $argv[0] ?? null;
    if($path === null){
        echo "Path not specified.\n";
        help();
    }
    $path = realpath($path);
    if($path === false||!is_dir($path)){
        echo "Path not found.\n";
        help();
    }
    if($key === null){
        $key = readKeyFile($path);
        if($key === null||strlen($key) === 0){
            echo "Key not specified.\n";
            help();
        }
    }
    if(strlen($key) !== 32){
		echo "Key length should be 32 bytes.\n";
		help();
	}
    
    $path = rtrim($path, "/\\").DIRECTORY_SEPARATOR;
    $output = rtrim($output, "/\\").DIRECTORY_SEPARATOR;
	return [$path, $key, $output];
}
exit;

function readShortOpt(array &$argv, $opt): ?string{
	$result = null;
	foreach($argv as $i => $value){
		if(str_starts_with($value, "-".$opt)){
			$result = substr($value, 2);
			unset($argv[$i]);
			if($result === ""||$result === false){
				$result = $argv[$i+1] ?? null;
				unset($argv[$i+1]);
			}
		}
	}
	$argv = array_values($argv);
	return $result;
}

function help(){
	echo "Usage: php encrypt.php <path> -k <key> -o <output>\n";
	exit;
}

//read *.key file with scandir
function readKeyFile(string $path): ?string{
    $files = scandir($path);
    foreach($files as $file){
        if(!str_ends_with($file, ".key")){
            continue;
        }
		$key1 = file_get_contents($path."/".$file);
		foreach([$key1, trim($key1)] as $key){
			if(strlen($key) !== 32){
				continue;
			}
			return $key;
		}
    }
    return null;
}