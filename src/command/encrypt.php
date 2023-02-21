<?php
declare(strict_types=1);

namespace resourceTools\command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use resourceTools\helper\CryptoHelper;
use resourceTools\helper\ContentFile;
use resourceTools\fileaccess\ZipFIleAccess;
use resourceTools\fileaccess\FileAccess;
use resourceTools\fileaccess\ZipCryptoFileAccess;
use resourceTools\fileaccess\CryptoFileAccess;
use resourceTools\helper\Transformer;
use resourceTools\helper\ManifestReader;

class encrypt extends Command {
	protected function configure()
	{
		// コマンドの名前と説明を指定する
		$this
			->setName('encrypt')
			->setDescription('encrypt resource')
			->addArgument('path', InputArgument::REQUIRED, 'target dir')  //FallenDeadAssets
			->addOption('key', "k",InputOption::VALUE_REQUIRED, '32byte keys')
			->addOption('output', "o", InputOption::VALUE_REQUIRED, 'output dir', "encrypt")
			->addOption('with-progress', "p", InputOption::VALUE_NONE, 'use progress bar')
			->addOption('with-resource-packs', "r", InputOption::VALUE_NONE, 'use resource_packs template');
	}
			
	protected function execute(InputInterface $input, OutputInterface $output): int{
		$contentsFile = $this->getContentFile($input, null);
		$from = $this->getInputSteam($input);
		$to = $this->getOutputSteam($input, $contentsFile);

		$output->writeln("mode: encrypt");
		$output->writeln("target: ".$from->getSource());
		$output->writeln("output: ".$to->getSource());
		$output->writeln("key: ".$contentsFile->getKey());

		if($input->getOption('with-progress')){
			[$filecount, $excluded, $notReadable] = Transformer::runWithProgressBar($from, $to, $output);
		}else{
			[$filecount, $excluded, $notReadable] = Transformer::run($from, $to, $output);
		}
		
		$this->WriteContentsFile($to, $contentsFile, manifestReader::getUUID($from));
		$output->writeln("\ndone.");
		$output->writeln("filecount: $filecount");
		$output->writeln("excluded: $excluded");
		$output->writeln("notReadable: $notReadable");
		return 0;
	}

	protected function getInputSteam(InputInterface $input) : FileAccess{
		// 引数とオプションの値を取得する
		$targetdir = $input->getArgument('path');
		$dir = realpath($targetdir);
		if($dir === ""||$dir === false){
			throw new \InvalidArgumentException("directory not found: $targetdir");
		}
		if(!is_dir($dir)&&!$this->isZip($dir)){
			throw new \InvalidArgumentException("Invalid directory $dir");
		}

		//input FileAccess
		if($this->isZip($dir)){
			$from = new ZipFIleAccess($dir);
		}else{
			//FileAccess is a wrapper for file access functions.
			$from = new FileAccess($dir);
		}
		if(!$from->exists("manifest.json")){
			throw new \InvalidArgumentException(path::join($dir, "manifest.json")." not found.");
		}
		return $from;
	}

	protected function getOutputSteam(InputInterface $input, ?ContentFile $contentFile) : FileAccess{
		$dir = getcwd();
		$outputdir = $input->getOption('output') ?? "encrypt";
		$with_resource_packs = $input->getOption('with-resource-packs');

		if($with_resource_packs){
			if(!extension_loaded("yaml")){
				throw new \RuntimeException("with-resource-packs requires a yaml extension, but it's not loaded.");
			}
			$outputdir = Path::join($dir, "resource_packs", $outputdir);
			$config =  Path::join($dir, "resource_packs", "resource_packs.yml");
			if(!file_exists($config)){
				if( \Phar::running() !== ""){
					$file = Path::join(\Phar::running(), "resources", "resource_packs.yml");
				}else{
					$file = Path::join(__DIR__, "..", "..", "resources", "resource_packs.yml");
				}
				if(!file_exists($file)){
					throw new \RuntimeException("resource_packs.yml not found: ".$file);
				}
				@mkdir(dirname($config), 0777, true);
				copy($file, $config);
			}

			$array = yaml_parse(file_get_contents($config));
			if($array === false){
				throw new \RuntimeException( "cannot parse resource_packs.yml");
			}
			if(!array_key_exists("resource_stack", $array)){
				throw new \RuntimeException( "resource_packs.yml: resource_stack not found");
			}
			if($array["resource_stack"] === null){
				$array["resource_stack"] = [];
			}
			$name = basename($outputdir, ".zip").".zip";
			$keyLocation = Path::join($dir, "resource_packs", $name.".key");
			if(!in_array($name, $array["resource_stack"])){
				file_put_contents($config, "   - ".$name."\n", FILE_APPEND);
			}
			if(file_exists($keyLocation)){
				$key = trim(file_get_contents($keyLocation));
				if(strlen($key) === 32){
					$contentFile->setKey($key);
				}
			}else{
				file_put_contents($keyLocation, $contentFile->getKey());
			}
		}else{
			$outputdir = Path::join($dir, $outputdir);
		}


		//output FileAccess
		if($this->isZip($outputdir)){
			$to = new ZipCryptoFileAccess($outputdir, $contentFile);
		}else{
			//CryptoFileAccess provides read/write access to encrypted files.
			$to = new CryptoFileAccess($outputdir, $contentFile);
		}
		return $to;
	}

	protected function getContentFile(InputInterface $input, ?FileAccess $fileAccess) : ContentFile{
		$key = $input->getOption('key') ?? CryptoHelper::random();
		//"contents.json" is an encrypted list of encrypted files used in the game.
		//The contentsFile object provides for reading and writing to the "contents.json" file.
		return new ContentFile($key);
	}

	protected function WriteContentsFile(FileAccess $to, ContentFile $contentFile, string $uuid) : void{
		$to->putContents("contents.json", $contentFile->WriteContentsFile($uuid));
		// Save key.txt. This will be used on the production server.
		$name = basename($to->getSource());
		if(!str_ends_with($name, ".zip")){
			$name .= ".zip";
		}
		$to->putContents($name.".key", $contentFile->getKey());
	}

	function isZip(string $path) : bool{
		return str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".zip")||str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".mcpack");
	}
};
