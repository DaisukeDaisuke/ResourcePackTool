<?php
declare(strict_types=1);

namespace resourceTools\helper;

use resourceTools\fileaccess\ZipCryptoFileAccess;
use resourceTools\fileaccess\CryptoFileAccess;
use resourceTools\fileaccess\FileAccess;

class Reader{
	protected FileAccess|CryptoFileAccess $fileAccess;
	protected ?string $uuid = null;
	protected ?string $key = null;
	private ?string $keyCache = null;

	public function __construct(
		protected string $path
	){
		$this->initFileAccess();
	}
	//init file access
	public function initFileAccess() : void{
		//iszipped
		if($this->isZip($this->path)){
			$this->fileAccess = new ZipCryptoFileAccess($this->path);
		}else{
			$this->fileAccess = new CryptoFileAccess($this->path);
		}
	}

	public function getUUID() : ?string{
		return $this->uuid;
	}

	public function updateKey(string $key): void{
		if($this->key === $key){
			return;
		}
		$this->key = $key;
		$contentFile = (new ContentFile($key))->ReadContentsFile($this->fileAccess);
		$this->fileAccess->setContentFile($contentFile);
	}

	public function getKey(): ?string{
		if(!isset($this->keyCache)){
			$source = $this->fileAccess->getSource();
			$base = pathinfo(basename($source), PATHINFO_FILENAME);
			$key = $base.".key";
			$keytxt = $base.".txt";

			$base1 = dirname($source).DIRECTORY_SEPARATOR.$key;
			$base2 = dirname($source).DIRECTORY_SEPARATOR.$keytxt;
			if($this->fileAccess->exists($key)){
				$this->keyCache = $this->fileAccess->getContents($key);
			}elseif($this->fileAccess->exists($keytxt)){
				$this->keyCache = null;
			}elseif(file_exists($base1)){
				$this->keyCache = file_get_contents($base1);
			}elseif(file_exists($base2)){
				$this->keyCache = file_get_contents($base2);
			}
			if($this->keyCache === null){
				return null;
			}
			$this->keyCache = trim($this->keyCache);
			if(strlen($this->keyCache) !== 32){
				$this->keyCache = null;
			}
		}
		return $this->keyCache;
	}

	function isZip(string $path) : bool{
		return str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".zip")||str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".mcpack");
	}

	public function getFileAccess() : FileAccess|CryptoFileAccess{
		return $this->fileAccess;
	}

	public function getContentsFile() : ?ContentFile{
		if($this->fileAccess instanceof CryptoFileAccess){
			return $this->fileAccess->getContentFile();
		}
		return null;
	}
}
