<?php
declare(strict_types=1);

namespace resourceTools\helper;

//CryptoHelper is a wapper for encryption and key generation.
class CryptoHelper{
	public static function encrypt(string $data, string $key) : string{
		self::checkKey($key);
		return openssl_encrypt($data, 'AES-256-CFB8', $key, OPENSSL_RAW_DATA, self::getIV($key));
	}

	public static function decrypt(string $data, string $key, int $offset = 0) : string{
		self::checkKey($key);
		return openssl_decrypt(substr($data, $offset), 'AES-256-CFB8', $key, OPENSSL_RAW_DATA, self::getIV($key));
	}

	public static function getIV(string $key) : string{
		return substr($key, 0, 16);
	}

//	//from https://qiita.com/ngyuki/items/dd947aae213327cbeb70
//	public static function random($n = 32) : string{
//		$random = substr(base_convert(bin2hex(openssl_random_pseudo_bytes($n)), 16, 36), 0, $n);
//		return strtoupper($random);
//	}

	public static function random($n = 32) : string{
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$count = strlen($characters) - 1;
		$key = '';
		for($i = 0; $i < $n; $i++){
			$key .= $characters[random_int(0, $count)];
		}
		return $key;
	}


	//check key is valid, key length is 32, please throw exception if not valid
	public static function checkKey(string $key) : void{
		if(strlen($key) !== 32) throw new \RuntimeException("key length is not 32");
	}
}