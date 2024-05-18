<?php
declare(strict_types=1);

namespace resourceTools\fileaccess;

use ZipArchive;

trait ZipAccess{
	private ZipArchive $zip;
	private bool $canOverride = true;
	//オーバライド
	private bool $overwrite = true;

	public function initZip() : void{
		//check openssl extension(option)
		if(!extension_loaded("zip")){
			throw new \RuntimeException("Cannot read/write zip file due to missing zip extension.");
		}
		$trim = rtrim($this->dir, DIRECTORY_SEPARATOR);
		if(!file_exists($trim)){
			$this->createZip($trim);
			$this->overwrite = false;
		}

		$this->zip = new ZipArchive;
		$res = $this->zip->open($trim);
		if($res !== true){
			echo '[ZipArchive::open] 失敗、コード:'.$res." ".$this->dir;
			throw new \RuntimeException("zip open fault");
		}
	}

	public function getContents(string $path) : string{
		$contents = $this->zip->getFromName($path);
		if($contents === false){
			throw new \RuntimeException(static::class."::getContents(): path bot found ".$path);
		}
		return $contents;
	}

	public function putContents(string $path, string $contents) : void{
		if(!$this->canOverride&&$this->overwrite){
			throw new \LogicException("ZipAccess::writeZip: attempting to override zip file, please set ZipAccess::\$canOverride to true");
		}
		$this->zip->addFromString(str_replace("\\", "/", $path), $contents);
	}

	//readAsset
	public function readZip(string $path) : string{
		return $this->getContents($path);
	}


	//writeAsset
	public function writeZip(string $path, string $contents) : void{
		$this->putContents($path, $contents);
	}

	//exists
	public function exists(string $path) : bool{
		return $this->zip->locateName($path) !== false;
	}

	//close
	public function close() : void{
		$this->zip->close();
	}

	public function getFiles(int &$filecount, int &$excluded, int &$notReadable) : array{
		$list = [];
		for($i = 0; $i < $this->zip->numFiles; $i++){
			$filename = $this->zip->getNameIndex($i);
			if($filename === false){
				throw new \LogicException("ZipAccess::getFiles: failed to get filename from index ".$i);
			}
			//check directory
			if($filename[-1] === "/"||$filename[-1] === "\\"){
				continue;
			}
			++$filecount;
			//exclude files
			foreach($this->exclude as $exclude){
				if(str_contains($filename, $exclude) !== false){
					++$excluded;
					//echo "Ignored exclusion file(".$exclude."): ".$realpath."\n";
					continue 2;
				}
			}	
			if(!$this->hasKey($filename)){
				//check directory
				if($filename[-1] === "/"||$filename[-1] === "\\"){
					continue;
				}
				++$notReadable;
				echo "[ZipAccess][WARNING]: Key not found: ".$this->dir."/".$filename." => ".$filename."\n";
			}
			$list[$filename] = $filename;
		}
		return $list;
	}

	private function createZip(string $path) : void{
		//https://stackoverflow.com/questions/3496667/how-to-create-an-empty-zip-archve-by-php
		file_put_contents($path, base64_decode("UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA=="));
	}

	public function makeDir(string $dir) : void{

	}

	public function scandir(string $path, string $contains, bool $recursive = false): \Generator{
		$path = str_replace(["\\", "//", "///"], "/", $path);
		for($i = 0; $i < $this->zip->numFiles; $i++){
			$filename = $this->zip->getNameIndex($i);
			$target = str_replace(["\\", "//"], "/", $filename);
			if($filename === false){
				throw new \LogicException("ZipAccess::getFiles: failed to get filename from index ".$i);
			}
			if($recursive === false&&strncmp($target, $path, strlen($path)) !== 0){
				continue;
			}
			$test = str_replace([$path."/", $path], "", $target);
			if($recursive === false&&(str_contains($test, "/")||str_contains($test, "\\"))){
				continue;
			}
			if(str_contains($target, $contains)){
				yield $filename;
			}
		}
	}
}
