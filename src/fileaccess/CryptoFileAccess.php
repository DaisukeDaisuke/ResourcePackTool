<?php
declare(strict_types=1);

namespace resourceTools\fileaccess;


use resourceTools\helper\CryptoHelper;
use resourceTools\helper\ContentFile;
use Symfony\Component\Filesystem\Path;

//CryptoFileAccess provides read/write access to encrypted files.
class CryptoFileAccess extends FileAccess{
	public function __construct(
		public string          $dir,
		protected ?ContentFile $contentFile = null,
		protected ?string      $key = null
	){

	}

	public function readAsset(string $path) : string{
		$key = $this->contentFile->getFileKey($path);
		return CryptoHelper::decrypt(file_get_contents(Path::join($this->dir, $path)), $this->contentFile->getFileKey($path) ?? throw new \LogicException("CryptoFileAccess, readAsset: key not found"));
	}

	public function writeAsset(string $path, string $contents) : void{
//		if(str_ends_with($path, ".json")){
//			$contents = json_encode(json_decode($contents, true, flags: JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
//		}
		$target = Path::join($this->dir, $path);
		$this->makeDir($path);
		$key = $this->key ?? CryptoHelper::random();
		$this->contentFile->addFile($path, $key);
		file_put_contents($target, CryptoHelper::encrypt($contents, $key));
	}

	public function hasKey(string $path) : bool{
		return $this->contentFile->getFileKey($path) !== null;
	}

	public function setContentFile(?ContentFile $contentFile) : void{
		$this->contentFile = $contentFile;
	}

	public function getContentFile() : ?ContentFile{
		return $this->contentFile;
	}
}