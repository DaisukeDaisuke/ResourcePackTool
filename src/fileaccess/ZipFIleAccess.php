<?php
declare(strict_types=1);

namespace resourceTools\fileaccess;

class ZipFIleAccess extends FileAccess{
	use ZipAccess;

	public function __construct(
		string $dir
	){
		parent::__construct($dir);
		$this->initZip();
	}

	public function readAsset(string $path) : string{
		return $this->readZip($path);
	}

	public function writeAsset(string $path, string $contents) : void{
		$this->writeZip($path, $contents);
	}
}
