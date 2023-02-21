<?php

//getopt m
$opts = getopt("m");
build_phar("resourceTools.phar", __DIR__ . DIRECTORY_SEPARATOR, true, isset($opts["m"]));

/**
 * @param string $file_phar
 * @param string $dir
 * @return void
 */
function build_phar(string $file_phar, string $dir, bool $compress, bool $isMini): void{
	if(!preg_match('/^[a-z1-9.\s,_\-]*$/ui', $file_phar)){
		printInfo('error: This program does not support output to directories other than the current directory');
		printInfo('output: '. $file_phar .', regular expression: /^[a-z1-9\.\s,_]*$/ui');
		return;
	}
	if(substr($file_phar, strrpos($file_phar, '.')+1) !== "phar"){
		$file_phar .= ".phar";
	}
	printInfo("start build: ".$file_phar);
	if(file_exists($file_phar)){
		printInfo("Phar file already exists, overwriting");
		Phar::unlinkArchive($file_phar);
	}
	$files = [];
	if($isMini){
		addDirectory($dir, "src", $files, ["decrypt.php", "encrypt.php", "main.php"]);
	}else{
		addDirectory($dir, "src", $files);
		addDirectory($dir, "resources", $files);
	}
	addDirectory($dir, "vendor", $files);

    $count = count($files);
	printInfo("adding ".$count." files");
	$phar = new Phar($file_phar, 0);
	$phar->startBuffering();
	$phar->setSignatureAlgorithm(\Phar::SHA1);
	$phar->buildFromIterator(new \ArrayIterator($files));
	if($isMini){
		$phar->setStub('<?php include "phar://".__FILE__ ."/src/mini/mini.php"; __HALT_COMPILER(); ?>');
	}else{
		$phar->setStub('<?php include "phar://".__FILE__ ."/src/main.php"; __HALT_COMPILER(); ?>');
	}
	
	if($compress){
		printInfo("Compressing...");
		$phar->compressFiles(Phar::GZ);
	}

	$phar->stopBuffering();
	printInfo("end");
}

/**
 * @param string $dir
 * @param string $basePath
 * @param array<string, string> $files
 * @return void
 */
function addDirectory(string $dir, string $basePath, array &$files, array $excluded = []){//: void
	$end_char = substr($dir, -1, 1);
	if($end_char !== "/" && $end_char !== "\\"){
		$dir .= DIRECTORY_SEPARATOR;
	}
	$targetPath = $dir.$basePath;
	if(!is_dir($targetPath)){
		return;
	}
	foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($targetPath)) as $path => $file){
		if($file->isFile() === false){
			continue;
		}
		
		foreach($excluded as $exclude){
			if(str_contains($path, $exclude)){
				continue 2;
			}
		}
		$files[str_replace($dir, "", $path)] = $path;
	}

}

/**
 * @param string $message
 * @return void
 */
function printInfo(string $message){//: void
	echo $message.PHP_EOL;
}