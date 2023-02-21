<?php
declare(strict_types=1);

/*
Portable program to encrypt resource packs
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


[$dir, $contentKey, $outputdir] = readOptions($argv);
if(!is_dir($dir)){
	throw new \RuntimeException("\"".$dir."\" is not a directory");
}

$exclude = [
	".git",
	".zip",
	".mcpack",
	".github",
	".7z",
	".gitignore",
	".idea",
	".bat",
	"output",

	"pack_icon.png",
	"manifest.json",
	".php",
];

echo "path: ".$dir."\n";
echo "output dir: ".$output."\n";

$content = [];
foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS)) as $realpath => $file){
	foreach($exclude as $item){
		if(str_contains($realpath, $item)){
			continue 2;
		}
	}
	//r_dump($file);
	$path = str_replace($dir, "", $realpath);
	if($path[0] === "/"||$path[1] === ":"){
		throw new \LogicException("The path cannot be processed correctly: base: ".$dir.", target: ".$realpath." => ".$path);
	}
	$outputFile = $outputdir.$path;
	$key = CryptoHelper::random(32);
	$content[] = [
		"path" => str_replace("\\", "/", $path),
		"key" => $key,
	];

	echo $path." with ".$key."\n";
	$data = file_get_contents($realpath);
	$data = CryptoHelper::encrypt($data, $key);
	@mkdir(dirname($outputFile), 0777, true);
	file_put_contents($outputFile, $data);
	
}
$content1["content"] = $content;
$manifestFile = $dir."manifest.json";
if(!file_exists($manifestFile)){
	throw new \RuntimeException("\"".$manifestFile."\" not found");
}

$manifest = json_decode(file_get_contents($manifestFile), true, flags: JSON_THROW_ON_ERROR);
$uuid = $manifest["header"]["uuid"] ?? null;
if($uuid === null){
	throw new \RuntimeException("manifest.json: uuid not found");
}
//var_dump($manifest);
$json = json_encode($content1, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$contentFile = CryptoHelper::generateContentsFile($uuid, $json, $contentKey);
//var_dump($json, hexentities1($contentFile));
echo $outputdir."contents.json with ".$contentKey."\n";
file_put_contents($outputdir."contents.json", $contentFile);
file_put_contents($outputdir.basename($outputdir).".key", $contentKey);
//var_dump($content1);

echo "copy: ".$outputdir."manifest.json\n";
copy($manifestFile, $outputdir."manifest.json");
if(file_exists($dir."pack_icon.png")){
	echo "copy: ".$outputdir."pack_icon.png\n";
	copy($dir."pack_icon.png", $outputdir."pack_icon.png");
}
echo "key: ".$contentKey."\n";
echo "done!\n";
exit;

class CryptoHelper{
	public static function encrypt(string $contents, string $key) : string{
		if(strlen($key) !== 32){
			throw new RuntimeException("The argument key must be 32 bytes long");
		}
		return openssl_encrypt($contents, "AES-256-CFB8", $key, OPENSSL_RAW_DATA, substr($key, 0, 16));
	}

	// https://qiita.com/ngyuki/items/dd947aae213327cbeb70
	public static function random($n = 32) : string{
		$random = substr(base_convert(bin2hex(openssl_random_pseudo_bytes($n)), 16, 36), 0, $n);
		return strtoupper($random);
	}

	public const HEADER = "\x0\x0\x0\x0\xfc\xb9\xcf\x9b\x0\x0\x0\x0\x0\x0\x0\x0";
	public static function generateContentsFile(string $uuid, string $json, string $key) : string{
		$metadata = self::HEADER."$".$uuid.str_repeat("\x0", 0xCB);
		$encrypted = self::encrypt($json, $key);
		$content = $metadata.$encrypted;

		if(strlen($uuid) !== 36){
			throw new RuntimeException("The argument uuid must be 36 bytes long");
		}
		if(strlen($metadata) !== 0x100){
			throw new RuntimeException("The Metadata must be 0x100 bytes long");
		}
		return $content;
	}
}

//readOption
function readOptions(array $argv){
	unset($argv[0]);
	if(!isset($argv[1])){
		help();
	}
	
	$argv = array_values($argv);
	$key = readShortOpt($argv, "k") ?? CryptoHelper::random();
	$output = readShortOpt($argv, "o") ?? "encrypted";
	//key must 32bytes
	if(strlen($key) !== 32){
		echo "Key length should be 32 bytes.\n";
		help();
	}
	$path = $argv[0] ?? null;
	if($path === null){
		echo "Path not specified.\n";
		help();
	}
	$path = realpath($path);
	if($path === false){
		echo "Path not found.\n";
		help();
	}
	$path = rtrim($path, "/\\").DIRECTORY_SEPARATOR;
	$output = rtrim($output, "/\\").DIRECTORY_SEPARATOR;
	return [$path, $key, $output];
}

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