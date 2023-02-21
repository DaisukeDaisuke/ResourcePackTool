<?php
declare(strict_types=1);

namespace resourceTools\fileaccess;

use FilesystemIterator;
use Symfony\Component\Filesystem\Path;
use JetBrains\PhpStorm\ArrayShape;

class FileAccess{
	protected array $exclude = [
		".git",
		".zip",
		".mcpack",
		".github",
		".7z",
		".gitignore",
		".idea",
		".bat",
		".phar",
		".diff",
		"output",
		".idea",
		".key",

		"pack_icon.png",
		"manifest.json",
		".php",
		"contents.json",
		"key.txt",
	];

	public function __construct(
		public string $dir
	){
		// if($dir[-1] !== "/"&&$dir[-1] !== "\\"){
		// 	$this->dir .= DIRECTORY_SEPARATOR;
		// }
		// var_dump($this->dir);
	}

	public function readAsset(string $path) : string{
		return $this->getContents($path);
	}

	public function writeAsset(string $path, string $contents) : void{
		$this->putContents($path, $contents);
	}

	/**
	 * write the file to path as-is
	 * @internal
	 *
	 * @param string $path
	 * @param string $contents
	 * @return void
	 */
	public function putContents(string $path, string $contents) : void{
		$target = Path::join($this->dir, $path);
		$this->makeDir($path);
		file_put_contents($target, $contents);
	}

	public function getContents(string $path) : string{
		return file_get_contents(Path::join($this->dir, $path));
	}

	//file_exists
	public function exists(string $path) : bool{
		return file_exists(Path::join($this->dir, $path));
	}


	//scanFile
	public function scandir(string $path, string $contains, bool $recursive = false) : \Generator{
		$target = Path::join($this->dir, $path);
		if(!is_dir($target)){
			throw new \LogicException("[FileAccess::scandir] directory \"".$target."\" not found.");
		}
		if($recursive){
			$scandir = array_keys(iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($target, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::KEY_AS_PATHNAME))));
		}else{
			$scandir = scandir($target);
		}
		if($scandir === false){
			throw new \RuntimeException("Failed to scan directory: ".$target);
		}
		foreach($scandir as $file){
			if($file === "." || $file === ".."){
				continue;
			}
			if(!str_contains($file, $contains)){
				continue;
			}
			if(is_dir($file)){
				continue;
			}
			yield $file;
		}
	}

	//mkder

	//clean object
	public function close() : void{

	}

	public function makeDir(string $dir) : void{
		$dir = dirname(Path::join($this->dir, $dir));
		if(!is_dir($dir)){
			mkdir($dir, 0777, true);
		}

	}

	public function getFiles(int &$filecount, int &$excluded, int &$notReadable) : array{
		$output = [];
		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO | FilesystemIterator::SKIP_DOTS)) as $realpath => $file){
			////exclude files
			if(!$file->isFile()){
				continue;
			}
			++$filecount;

			foreach($this->exclude as $exclude){
				if(str_contains($realpath, $exclude) !== false){
					++$excluded;
					//echo "Ignored exclusion file(".$exclude."): ".$realpath."\n";
					continue 2;
				}
			}

			$path = str_replace([$this->dir."/", $this->dir."\\", $this->dir], "", $realpath);
			if(!$this->hasKey($path)){
				++$notReadable;
				echo "[FileAccess][WARNING]: Key not found: ".$path." => ".$realpath."\n";
			}
			$output[$file->getPathname()] = $path;
		}
		return $output;
	}


	public function searchKey(): array{
		$key = null;
		$keyFile = null;
		foreach($this->scandir("", ".key") as $item){
			$keyCandidate = $this->getContents($item);
			if(strlen($keyCandidate) === 32){
				$keyFile = $item;
				$key = $keyCandidate;
				break;
			}elseif(strlen(trim($keyCandidate)) === 32){
				$keyFile = $item;
				$key = trim($keyCandidate);
				break;
			}
		}
		return [$key, $keyFile];
	}

	//is_readable
	public function hasKey(string $path) : bool{
		return true;
	}

	public function getSource() : string{
		return $this->dir;
	}
}
