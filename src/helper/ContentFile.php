<?php
declare(strict_types=1);

namespace resourceTools\helper;

/*
The contentsFile object provides for reading and writing to the "contents.json" file.
"contents.json" is an encrypted list of encrypted files used in the game.
The path must use forward slashes, backslashes are not allowed.
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
   |0 1 2 3 4 5 6 7 8 9 a b c d e f|
 00|header \x00x4 FCB9CF9B \x00x8  |
 10|$|           uuid(36Byte)      |
 20|             uuid              |
 30|uuid(0-4)|      \x00*0xCB      |
*
100|            encrypted json     |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
*/

use resourceTools\fileaccess\FileAccess;
use RuntimeException;
use resourceTools\exception\ContentsFileException;

final class ContentFile{
	//Headers in contents.json are binary and below.
	public const HEADER = "\x0\x0\x0\x0\xfc\xb9\xcf\x9b\x0\x0\x0\x0\x0\x0\x0\x0";
	protected string $json;
	//The header is followed by a "$" (\x24) followed by a uuid in string format.
	//After the uuid there are 0xCB bytes null bytes (\x00) and put the encrypted json body underneath.

	public function WriteContentsFile(string $uuid) : string{
		$json = json_encode($this->getFiles(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return self::generateContentsFile($uuid, $json, $this->key);
	}

	public function generateContentsFile(string $uuid, string $json, string $key) : string{
		$metadata = self::HEADER."$".$uuid.str_repeat("\x0", 0xCB);
		$encrypted = CryptoHelper::encrypt($json, $key);
		$content = $metadata.$encrypted;
		if(CryptoHelper::decrypt($content, $key, 0x100) !== $json){
			throw new \LogicException("generateContentsFile: decrypt");
		}
		if(strlen($uuid) !== 36){
			throw new RuntimeException("The argument uuid must be 36 bytes long");
		}
		if(strlen($metadata) !== 0x100){
			throw new RuntimeException("The Metadata must be 0x100 bytes long");
		}
		return $content;
	}


	//get json from contents file
	public function ReadContentsFile(FileAccess $fileAccess) : self{
		//get uuid from contents file
		$path = $fileAccess->getSource();
		if(!$fileAccess->exists("contents.json")){
			throw new ContentsFileException("contents.json ".$path."/contents.json is not found");
		}

		$json = trim(CryptoHelper::decrypt($fileAccess->getContents("contents.json"), $this->key, 0x100));
		$this->json = $json;
//		if($json[0] !== "{"&&$json[0] !== "["){
//			echo "\nWARNING: Key may be incorrect!\n";
//		}
		try{
			$this->setFiles(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
		}catch(\JsonException){
			throw new ContentsFileException("Failed to decrypt or decode content.json! key: ".$this->getKey());
		}
		if(!isset($this->files["content"])){
			throw new \LogicException("\"contents.json > content\" not found");
		}
		//write index
		foreach($this->files["content"] as $file){
			if(str_contains($file["path"], "..")){
				var_dump($json);
				throw new \RuntimeException("[ContentFile]: Invalid path: ".$file["path"]);
			}
			$this->index[$file["path"]] = $file["key"] ?? null;
		}
		return $this;
	}


	//property of file list
	private array $files = [];
	//index of file list
	private array $index = [];
	private string $key;

	public function __construct(string $key){
		$this->key = $key;
	}

	public function addFile(string $path, string $key) : void{
		$this->files["content"][] = ["path" => str_replace(["\\", "//", "///", "////", "/////"], "/", $path), "key" => $key];
		$this->index[$path] = $key;
	}

	//setter files
	public function setFiles(array $files) : void{
		$this->files = $files;
	}

	//getKey
	public function getFileKey(string $path) : ?string{
		return $this->index[$path] ?? $this->index[str_replace("\\", "/", $path)] ?? $this->index[str_replace("/", "\\", $path)] ?? null;
	}

	//getter
	public function getFiles() : array{
		return $this->files;
	}

	public function getJson() : string{
		return $this->json;
	}

	// This is not necessary.
	public function sortContentsKeys() : void{
		usort($this->files["content"], function($a, $b){
			return strcmp($a["path"], $b["path"]);
		});
	}

	public function getKey() : string{
		return $this->key;
	}

	public function setKey(string $key) : void{
		$this->key = $key;
	}
}