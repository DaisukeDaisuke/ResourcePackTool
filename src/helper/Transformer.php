<?php
declare(strict_types=1);

namespace resourceTools\helper;

use resourceTools\fileaccess\FileAccess;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class Transformer{
	/** @var array<string, int> */
	public static array $extensions = [];

	public static function run(FileAccess $from, FileAccess $to, OutputInterface $output, bool $trimJson = false) : array{
		[$list, $filecount, $excluded, $notReadable] = self::getFileList($from);
		$generator = self::runInternal($list, $from, $to, $trimJson);
		foreach($generator as $path){
			$output->writeln($path);
		}
		return [$filecount, $excluded, $notReadable];
	}

	public static function runWithProgressBar(FileAccess $from, FileAccess $to, OutputInterface $output, bool $trimJson = false) : array{
		[$list, $filecount, $excluded, $notReadable] = self::getFileList($from);
		$generator = self::runInternal($list, $from, $to, $trimJson);
		$progressBar = new ProgressBar($output);
		foreach($progressBar->iterate($generator, count($list)) as $value){
			// do nothing
		}
		return [$filecount, $excluded, $notReadable];
	}

	//get files
	public static function getFileList(FileAccess $from) : array{
		$filecount = 0;
		$excluded = 0;
		$notReadable = 0;
		$list = $from->getFiles($filecount, $excluded, $notReadable);
		return [$list, $filecount, $excluded, $notReadable];
	}

	public static function runInternal(array $list, FileAccess $from, FileAccess $to, bool $trimJson) : \Generator{
		$maxSteps = count($list);
		foreach($list as $target => $path){
			$extension = pathinfo($path, PATHINFO_EXTENSION);
			self::$extensions[$extension] ??= 0;
			++self::$extensions[$extension];
			$to->makeDir($path);
			if($from->hasKey($path)){
				$data = $from->readAsset($path);
			}else{
				//key not found
				$data = $from->getContents($path);
			}

			if($trimJson&&(str_ends_with($path, ".json")||str_ends_with($path, ".lang")||str_ends_with($path, ".material")||str_ends_with($path, ".txt")||str_ends_with($path, ".attachable"))){
				$data = trim($data);
			}
			$to->writeAsset($path, $data);
			yield $path;
		}
		if($from->exists("manifest.json")) $to->putContents("manifest.json", $from->getContents("manifest.json"));
		if($from->exists("pack_icon.png")) $to->putContents("pack_icon.png", $from->getContents("pack_icon.png"));
	}

	public static function calculatePercentage() : array{
		$total_count = array_sum(self::$extensions);
		$output = [];
		foreach(self::$extensions as $extension => $count){
			$output[$extension] = [($count / $total_count) * 100, $count]; //percentage
		}
		arsort($output);
		return $output;
	}
}
