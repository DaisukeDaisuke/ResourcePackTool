<?php
declare(strict_types=1);

namespace resourceTools\fileaccess;

//crypto zip file access
use resourceTools\helper\ContentFile;
use resourceTools\helper\CryptoHelper;

class ZipCryptoFileAccess extends CryptoFileAccess{
	use ZipAccess;

	public function __construct(string $dir, ?ContentFile $contentFile = null, ?string $key = null){
		parent::__construct($dir, $contentFile, $key);
		$this->initZip();
	}

	//readAsset
	public function readAsset(string $path) : string{
		return CryptoHelper::decrypt($this->readZip($path), $this->contentFile->getFileKey($path) ?? throw new \LogicException("ZipCryptoFileAccess, readAsset: key not found"));
	}

	//writeAsset
	public function writeAsset(string $path, string $contents) : void{
//		if(str_ends_with($path, ".json")){
//			$contents = json_encode(json_decode($contents, true, flags: JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
//		}
		$key = $this->key ?? CryptoHelper::random();
		$this->contentFile->addFile($path, $key);
		$this->writeZip($path, CryptoHelper::encrypt($contents, $key));
	}
}