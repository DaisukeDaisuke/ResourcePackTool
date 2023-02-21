<?php
declare(strict_types=1);

namespace resourceTools\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use resourceTools\fileaccess\CryptoFileAccess;
use resourceTools\fileaccess\ZipCryptoFileAccess;
use Symfony\Component\Filesystem\Path;
use resourceTools\fileaccess\FileAccess;
use Symfony\Component\Console\Input\InputOption;
use resourceTools\helper\Reader;

class DebugContent extends Command{
	private FileAccess $fileAccess;

	protected function configure(){
		// コマンドの名前と説明を指定する
		$this
			->setName('c')
			->addArgument('path', InputArgument::REQUIRED, 'target dir or file')  //FallenDeadAssets
			->addOption('json', "j", InputOption::VALUE_NONE, "no print raw json")
			->addOption('var_dump', "d", InputOption::VALUE_NONE, "no run var_dump")
			->addOption('file_list', "f", InputOption::VALUE_NONE, "var_dump: output is file list only")
			->addOption('key', "k",InputOption::VALUE_REQUIRED, '32byte keys');
	}

	protected function execute(InputInterface $input, OutputInterface $output) : int{
		$path = $input->getArgument('path');
		$print_json = !$input->getOption("json");
		$print_var_dump = !$input->getOption("var_dump");
		$fileList_only = $input->getOption("file_list");
		$key = $input->getOption("key");

		if($output->isVerbose()){
			$print_var_dump = false;
		}

		if(!$print_json&&!$print_var_dump){
			$output->writeln("nothing to do.");
		}

		$cwd = getcwd().DIRECTORY_SEPARATOR;
		$dir = realpath($cwd.$path);
		if($dir === ""||$dir === false){
			throw new \RuntimeException("directory not found: $path");
		}

		$target = $dir;
		if(is_file($dir)&&!$this->isZip($dir)){
			$target = dirname($dir);
		}
		$reader = new Reader($target);
		$key = $key ?? $reader->getKey();
		if($key === null){
			throw new \RuntimeException("key not found: ".$target);
		}
		$reader->updateKey($key);
		$contentFile = $reader->getContentsFile();

		if($print_json){
			$output->writeln($contentFile->getJson());
		}
		if($print_var_dump){
			$content = $contentFile->getFiles();
			if($fileList_only){
				$content = $content["content"] ?? throw new \RuntimeException("array key \"content\" not found");
			}
			ob_start();
			var_dump($content);
			$output->writeln(ob_get_clean());
		}
		return 0;
	}

	function isZip(string $path) : bool{
		return str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".zip")||str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".mcpack");
	}

	protected function getInputSteam(InputInterface $input) : CryptoFileAccess{
		// 引数とオプションの値を取得する
		$targetdir = $input->getArgument('path');
		$dir = realpath($targetdir);
		if($dir === ""||$dir === false){
			throw new \RuntimeException("directory not found: $targetdir");
		}
		if(!is_dir($dir)&&!$this->isZip($dir)){
			throw new \RuntimeException("Invalid directory $dir");
		}

		//input FileAccess
		if($this->isZip($dir)){
			$from = new ZipCryptoFileAccess($dir);
		}else{
			//FileAccess is a wrapper for file access functions.
			$from = new CryptoFileAccess($dir);
		}
		if(!$from->exists("manifest.json")){
			throw new \RuntimeException(path::join($dir, "manifest.json")." not found.");
		}
		return $from;
	}
}