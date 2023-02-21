<?php
declare(strict_types=1);

namespace resourceTools\helper;

use resourceTools\fileaccess\FileAccess;

//manifestReader reads the uuid from manifest.json.
class ManifestReader{
	//read manifest.json, uuid
	public static function getUUID(FileAccess $fileAccess) : string{
		$json = json_decode($fileAccess->getContents("manifest.json"), true, 512, JSON_THROW_ON_ERROR);
		return trim($json["header"]["uuid"]);
	}

	public static function getName(FileAccess $fileAccess) : string{
		$json = json_decode($fileAccess->getContents("manifest.json"), true, 512, JSON_THROW_ON_ERROR);
		return str_replace([" ", "/", "\\", ".", "!", "<", ">", "*", "?", "\"", "|", ":"],["_"], trim($json["header"]["name"]));
	}
}
