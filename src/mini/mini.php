<?php

use resourceTools\fileaccess\CryptoFileAccess;
use resourceTools\fileaccess\FileAccess;
use resourceTools\fileaccess\ZipCryptoFileAccess;
use resourceTools\fileaccess\ZipFIleAccess;
use resourceTools\helper\ContentFile;
use resourceTools\helper\CryptoHelper;
use resourceTools\helper\ManifestReader;
use resourceTools\helper\Transformer;
use Symfony\Component\Filesystem\Path;

if(version_compare(phpversion(), '8.0.0', '<')){
	echo "PHP 8.0.0 or higher is required to run this script. You are using PHP ".phpversion().PHP_EOL;
	exit(1);
}

//check opelssl ext
if(!extension_loaded("openssl")){
	echo "openssl extension is required to run this script.".PHP_EOL;
	exit(1);
}

//check zip ext
if(!extension_loaded("zip")){
	//warning zip ext
	echo "warning: zip extension is not found. This script will not be able to read/write zip files.".PHP_EOL;
}

//inc autoload
require_once __DIR__."/../../vendor/autoload.php";

$dir = getcwd().DIRECTORY_SEPARATOR;
[$encrypt, $decrypt, $output, $key, $decode_contents] = readOption($dir, $argv);
if($decode_contents !== null){  //decode contents.json
	if($decode_contents !== false&&str_ends_with($decode_contents, "contents.json")){
		$decode_contents = str_replace("contents.json", "", $decode_contents);
	}
	if($decode_contents !== false){
		$dir = $decode_contents.DIRECTORY_SEPARATOR;
	}
	echo "reading ".$dir."contents.json\n";
	if(isZip($dir)){
		$from = new ZipFIleAccess($dir);
	}else{
		//FileAccess is a wrapper for file access functions.
		$from = new FileAccess($dir);
	}
	if(!$from->exists("contents.json")){
		echo $dir."contents.json not found\n";
		exit();
	}
	if($key === null){
		[$key, $keyFile] = $from->searchKey();
		if($key === null||$keyFile === null){
			echo "key not found\n";
			exit();
		}
	}
	echo "key: ".$key."\n";
	echo "\n";
	$contentFile = new ContentFile($key);
	$contentFile->ReadContentsFile($from);
	//var_dump($contentFile->getFiles());
	echo "##########\n";
	echo $contentFile->getJson(), "\n";
	echo "==========\n";
	exit();
}elseif($encrypt !== null){    //encryption
	echo "mode: encrypt\n\n";
	//Processes the return value of readoption and sets the input directory, output directory, etc.
	$output ??= "encrypted";
	$outputdir = Path::join($dir, $output);
	if($encrypt !== false) $dir = $encrypt.DIRECTORY_SEPARATOR;
	if(!is_dir($dir)&&!isZip($dir)) throw new \RuntimeException("[encrypt]: Invalid directory \"".$dir."\"");

	//Obtain key from random number
	$key ??= CryptoHelper::random();

	//"contents.json" is an encrypted list of encrypted files used in the game.
	//The contentsFile object provides for reading and writing to the "contents.json" file.
	$contentFile = new contentFile($key);

	//input FileAccess
	if(isZip($dir)){
		$from = new ZipFIleAccess($dir);
	}else{
		//FileAccess is a wrapper for file access functions.
		$from = new FileAccess($dir);
	}

	if(!$from->exists("manifest.json")){
		echo "error: ".$dir."manifest.json not found.\n";
		exit();
	}

	//output FileAccess
	if(isZip($outputdir)){
		$to = new ZipCryptoFileAccess($outputdir, $contentFile);
	}else{
		//CryptoFileAccess provides read/write access to encrypted files.
		$to = new CryptoFileAccess($outputdir, $contentFile);
	}

	[$files, $filecount, $excluded, $notReadable] = Transformer::getFileList($from);
	$generator = Transformer::runInternal($files, $from, $to, false);
	foreach($generator as $value){
		echo $value.PHP_EOL;
	}
	echo "contents.json\n";
	$to->putContents("contents.json", $contentFile->WriteContentsFile(ManifestReader::getUUID($from)));
	// Save key.txt. This will be used on the production server.
	echo basename($to->getSource()).".key\n";
	$to->putContents(Path::join(basename($to->getSource()).".key"), $contentFile->getKey());
	echo "\n";
	echo "done.\n";
	echo "key: ".$key."\n";
	echo "target: ".rtrim($dir, DIRECTORY_SEPARATOR)."\n";
	echo "output: ".rtrim($outputdir, DIRECTORY_SEPARATOR)."\n";
	echo "file count: ".$filecount."\n";
	echo "excluded: ".$excluded."\n";
	echo "not readable: ".$notReadable."\n";
}elseif($decrypt !== null){    //decryption
	echo "mode: decrypt\n";
	$output ??= "decrypt";
	$outputdir = Path::join($dir, $output);

	if($decrypt !== false) $dir = $decrypt.DIRECTORY_SEPARATOR;
	if(!is_dir($dir)&&!isZip($dir)) throw new \RuntimeException("[decrypt]: Invalid directory \"".$dir."\"");

	//key not specified
	//input FileAccess
	if(isZip($dir)){
		$from = new ZipCryptoFileAccess($dir);
	}else{
		//CryptoFileAccess provides read/write access to encrypted files.
		$from = new CryptoFileAccess($dir);
	}

	if($key === null){
		[$key, $keyFile] = $from->searchKey();
		if($key === null||$keyFile === null){
			echo "*.key file not found\n";
			exit();
		}
	}

	//The ReadContentsFile method reads the file list and decryption key from the encrypted contents.json.
	$contentFile = new contentFile($key);
	$contentFile->ReadContentsFile($from);
	$from->setContentFile($contentFile);

	//output FileAccess
	if(isZip($outputdir)){
		$to = new ZipFileAccess($outputdir);
	}else{
		//FileAccess is a wrapper for file access functions.
		$to = new FileAccess($outputdir);
	}
	[$files, $filecount, $excluded, $notReadable] = Transformer::getFileList($from);
	$generator = Transformer::runInternal($files, $from, $to, false);
	foreach($generator as $value){
		echo $value.PHP_EOL;
	}
	echo "\n";
	echo "done.\n";
	echo "key: ".$key."\n";
	echo "target: ".rtrim($dir, DIRECTORY_SEPARATOR)."\n";
	echo "output: ".rtrim($outputdir, DIRECTORY_SEPARATOR)."\n";
	echo "file count: ".$filecount."\n";
	echo "excluded: ".$excluded."\n";
	echo "not readable: ".$notReadable."\n";
}else{
	echo "error: No \"-e\" or \"-d\" options are specified\n";
	echo "nothing to do.\n\n";
	help();//exit;
}
exit;

function help(){//: never
	//$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
	//echo "called by ", $backtrace[0]["file"], "#L", $backtrace[0]["line"], PHP_EOL;

	$script = Phar::running(false);
	if($script === ""){
		$script = __FILE__;
	}
	$script = str_replace("\\", "/", $script);
	$script = explode("/", $script);
	$script = $script[array_key_last($script)];
	echo $script." -e [directory] : encrypt".PHP_EOL;
	echo $script." -d [directory] : decrypt".PHP_EOL;
	echo "-o/--output".PHP_EOL;
	echo "-k/--key".PHP_EOL;
	echo "-g/--keygen".PHP_EOL;
	echo "-h/--help".PHP_EOL;
	echo "-c/--contents    # Decode and print contents.json for debugging".PHP_EOL;
	echo PHP_EOL;
	echo "example:".PHP_EOL;
	echo "  ".$script." -e    # Encrypt the current directory using a random password and output to the \"encrypted\" directory.".PHP_EOL;
	echo "  ".$script." -efolder -oencrypted -k18N8QKHGA70G0KCWO0K4OOGCWWKG80OC".PHP_EOL;
	echo "  ".$script." -dfolder -odecrypt -k18N8QKHGA70G0KCWO0K4OOGCWWKG80OC".PHP_EOL;
	echo "  ".$script." e <key>".PHP_EOL;
	echo "  ".$script." e folder <key>".PHP_EOL;
	echo "  ".$script." d folder <key>".PHP_EOL;
	echo PHP_EOL;
	echo "Note: contents.json does not support nested folders.";
	exit;
}

function ProcessOption(string $shortOption, string $longOption, array $option){//: string|false|null
	$value = $option[$shortOption] ?? $option[$longOption] ?? null;
	if(is_array($value)) $value = $value[0];
	return $value;
}

function readParameter(string $arg0, ?string $arg1) : string|false{
	$result = false;
	$arg = substr($arg0, 1);
	if($arg !== false&&$arg !== ""){
		$result = $arg;
	}elseif($arg1 !== null){
		$result = $arg1;
	}
	return $result;
}

function readOption(string $dir, array $argv) : array{
	$option = user_getopt(
		"e::d::o:k:g::b::c:h",
		["encrypt::", ":decrypt::", "output:", "key:", "keygen::", "contents::", "help"],
		$argv,
		$parameter
	);
	//check option
	if(count($option) === 0&&$parameter === 0){
		help();//exit;
	}

	//If there is a value(!== false), get the value, else get false
	$encrypt = ProcessOption("e", "encrypt", $option);
	$decrypt = ProcessOption("d", "decrypt", $option);
	$output = ProcessOption("o", "output", $option);
	$key = ProcessOption("k", "key", $option);
	$keygen = ProcessOption("g", "keygen", $option);
	$help = ProcessOption("h", "help", $option);
	$contents = ProcessOption("c", "contents", $option);

	if($key === null){
		foreach($parameter as $i => $item){
			$trim = trim($item);
			if(strlen($trim) === 32&&preg_match('/^[a-zA-Z0-9]{32}$/', $trim)){
				$key = $trim;
				unset($parameter[$i]);
				$parameter = array_values($parameter);
				break;
			}
		}
	}

	$arg0 = $parameter[0] ?? null;
	$arg1 = $parameter[1] ?? null;
	if($encrypt === null&&$decrypt === null&&$arg0 !== null){
		if($arg0[0] === "e"){
			$encrypt = readParameter($arg0, $arg1);
		}elseif($arg0[0] === "d"){
			$decrypt = readParameter($arg0, $arg1);
		}elseif($arg0[0] === "c"){
			$contents = readParameter($arg0, $arg1);
		}elseif($arg0[0] === "g"){
			$keygen = readParameter($arg0, $arg1);
		}
	}

	$encrypt = solveRealpath($encrypt, $dir);
	$decrypt = solveRealpath($decrypt, $dir);
	$contents = solveRealpath($contents, $dir);

	if($keygen !== null){
		$keygen = (int) $keygen;
		if($keygen > 36){
			echo "The requested key length is too long, max: 36".PHP_EOL;
			exit;
		}
		$key = $keygen !== false ? $keygen : 32;
		echo CryptoHelper::random($key);
		exit();
	}

	if($help !== null){
		help();//exit;
	}

	if($key !== null&&strlen($key) !== 32){
		echo "Key length must be 32 bytes\n";
		echo "key: ".$key;
		exit(1);
	}

	return [$encrypt, $decrypt, $output, $key, $contents];
}

function solveRealpath(mixed $input, string $dir){// : string|false|null
	if(!is_string($input)&&!$input === false&&!is_null($input)){
		throw new \LogicException("input must be string|false|null");
	}
	if($input === null){
		return null;
	}
	if($input === false){
		return false;
	}
	$path = realpath($input);
	if($path === false){
		if(isZip($dir)){
			return $dir.$input;
		}
		throw new \RuntimeException("[user_realpath]: Invalid path \"".$path."\" / ".$input.", options maybe incorrect or file/directory not found");
	}
	return $path;
}


function isZip(string $path) : bool{
	return str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".zip")||str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".mcpack");
}

/**
 * @see getopt() native getopt function.
 * @param list<string> $long_options
 * @param list<string> $argv
 * @param list<string>|null $parameter
 * @param list<string> $after
 * @param bool $notallowUnknownOptions
 * @param list<string>|null $unknownOptions
 * @param string $short_options
 * @return array<string, string>
 */
function user_getopt(string $short_options, array $long_options = [], array $argv = [], ?array &$parameter = null, ?array $after = null, bool $notallowUnknownOptions = false, array &$unknownOptions = null) : array{
	$unknownOptions = [];
	$result = [];
	for($i = 1, $iMax = count($argv) - 1; $i <= $iMax; $i++){
		$next_value = null;
		$value = $argv[$i];
		if(isset($argv[$i + 1])&&!str_starts_with($argv[$i + 1], "-")){
			$next_value = $argv[$i + 1];
		}

//			if($ignoreScriptOption === true){
//				if(str_starts_with($value, "-")){
//					unset($argv[$i]);
//					continue;
//				}
//				$ignoreScriptOption = false;
//			}

		if($after !== null){
			foreach($after as $item){
				if($item === $value){
					$after = null;
					continue 2;
				}
			}
			unset($argv[$i]);
			continue;
		}

		if(str_starts_with($value, "--")){
			$found = false;
			$target = substr($value, 2);
			foreach($long_options as $long_option){
				//var_dump([$target, $long_option], str_starts_with($target, $long_option));
				if(str_starts_with($target, strstr($long_option, ":", true) ?: $long_option)){
					$found = true;
					$operator = substr(strstr($long_option, ":") ?: "", 0, 2);
					$explode = explode("=", $target);
					if($operator === "::"){
						if(count($explode) === 2){
							$result[$explode[0]][] = $explode[1];
							continue;
						}
						if($next_value === null){
							$result[$target][] = false;
							unset($argv[$i]);
							continue;
						}
						$result[$target][] = $next_value;
						unset($argv[$i], $argv[$i + 1]);
						++$i;
					}elseif($operator !== ""&&$operator[0] === ":"){
						if(count($explode) === 2){
							$result[$explode[0]][] = $explode[1];
							continue;
						}
						if($next_value === null){
							continue;
						}
						$result[$target][] = $next_value;
						unset($argv[$i], $argv[$i + 1]);
						++$i;
					}else{
						$result[$target][] = false;
						unset($argv[$i]);
					}
				}
			}
			//unknown Options
			if(!$found){
				if($next_value === null){
					$unknownOptions["--".$target][] = false;
				}else{
					$unknownOptions["--".$target][] = $next_value;
				}
			}
			continue;
		}

		if(str_starts_with($value, "-")){
			if(($str = strstr($short_options, $value[1])) !== false){
				foreach($long_options as $item){
					if(ltrim($value, "-") === rtrim($item, ":")){
						echo "[user_getopt] Found ambiguous options: ", $value, ", do you mean --", rtrim($item, ":"), "?\n";
					}
				}
				if(substr($str, 1, 2) === "::"){
					if(strlen($value) >= 3){
						$result[$value[1]][] = substr($value, 2);
						unset($argv[$i]);
						continue;
					}
					if($next_value !== null){
						$result[$value[1]][] = $next_value;
						unset($argv[$i], $argv[$i + 1]);
						++$i;
					}else{
						$result[$value[1]][] = false;
						unset($argv[$i]);
					}
				}elseif(isset($str[1])&&$str[1] === ":"){
					if(strlen($value) >= 3){
						$result[$value[1]][] = substr($value, 2);
						unset($argv[$i]);
						continue;
					}
					if($next_value === null){
						continue;
					}
					$result[$value[1]][] = $next_value;
					unset($argv[$i], $argv[$i + 1]);
					++$i;
				}else{
					$result[$value[1]][] = false;
					unset($argv[$i]);
				}
			}else{
				//unknown Options
				if(strlen($value) >= 3){
					$unknownOptions[$value[1]][] = substr($value, 2);
					continue;
				}
				if($next_value === null){
					$unknownOptions["-".$value[1]][] = false;
				}else{
					$unknownOptions["-".$value[1]][] = $next_value;
				}
			}
			continue;
		}
	}

	unset($argv[0]);
	$parameter = array_values($argv);
	foreach($unknownOptions as $key => $item){
		if(count($item) === 1){
			$unknownOptions[$key] = $item[0];
		}
	}
	foreach($result as $key => $item){
		if(count($item) === 1){
			$result[$key] = $item[0];
		}
	}
	if($notallowUnknownOptions === true&&count($unknownOptions) !== 0){
		foreach($unknownOptions as $name => $item){
			throw new \RuntimeException("final: The \"".$name."\" option does not exist.");
		}
	}

	return $result;
}