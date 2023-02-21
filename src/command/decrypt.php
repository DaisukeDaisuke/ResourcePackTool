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
use resourceTools\fileaccess\FileAccess;
use resourceTools\fileaccess\ZipCryptoFileAccess;
use resourceTools\fileaccess\CryptoFileAccess;
use resourceTools\helper\Transformer;
use resourceTools\helper\ManifestReader;
use resourceTools\fileaccess\ZipFIleAccess;
use resourceTools\helper\decipher;

class decrypt extends Command {
	protected function configure()
	{
		// コマンドの名前と説明を指定する
		$this
			->setName('decrypt')
			->setDescription('decrypt resource')
			->addArgument('path', InputArgument::REQUIRED, 'target dir')  //FallenDeadAssets
			->addOption('key', "k",InputOption::VALUE_REQUIRED, '32byte keys')
			->addOption('output', "o", InputOption::VALUE_REQUIRED, 'output dir', "decrypt")
			->addOption('no-trim', "t", InputOption::VALUE_NONE, "Don't trim the json file.")
			->addOption('with-progress', "p", InputOption::VALUE_NONE, 'use progress bar')
			->addOption('recursive', "r", InputOption::VALUE_NONE, 'recursive [path]/*');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int{
//		$this->decipher = new decipher(
//			$input->getOption('key'),
//			$input->getArgument('path'),
//			$input->getOption('output'),
//			//$input->getOption('with-progress'),
//			!$input->getOption("no-trim")
//		);
		$trimJson = !$input->getOption("no-trim");
		$from = $this->getInputSteam($input);
		$to = $this->getOutputSteam($input, null);

		$contentsFile = $this->getContentFile($input, $from, $output);
		$from->setContentFile($contentsFile);

		$output->writeln("mode: decrypt");
		$output->writeln("target: ".$from->getSource());
		$output->writeln("output: ".$to->getSource());
		$output->writeln("key: ".$contentsFile->getKey());

		if($input->getOption('with-progress')){
			[$filecount, $excluded, $notReadable] = Transformer::runWithProgressBar($from, $to, $output, $trimJson);
		}else{
			[$filecount, $excluded, $notReadable] = Transformer::run($from, $to, $output, $trimJson);
		}

		$output->writeln("\ndone.");
		$output->writeln("filecount: $filecount");
		$output->writeln("excluded: $excluded");
		$output->writeln("notReadable: $notReadable");
		return 0;
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

	protected function getOutputSteam(InputInterface $input, ?ContentFile $contentFile) : FileAccess{
		$dir = getcwd();
		$outputdir = $input->getOption('output') ?? "encrypt";
		$outputdir = Path::join($dir, $outputdir);
		//output FileAccess
		if($this->isZip($outputdir)){
			$to = new ZipFileAccess($outputdir, $contentFile);
		}else{
			//CryptoFileAccess provides read/write access to encrypted files.
			$to = new FileAccess($outputdir, $contentFile);
		}
		return $to;
	}

	protected function getContentFile(InputInterface $input, ?FileAccess $fileAccess, OutputInterface $output) : ContentFile{
		if($fileAccess === null) throw new \LogicException("This point is never reached");
		$key = $input->getOption('key') ?? $this->searchKey($fileAccess, $output) ?? throw new \RuntimeException("enter key not specified\nPlease use the -k option to specify the key\n");
		//"contents.json" is an encrypted list of encrypted files used in the game.
		//The contentsFile object provides for reading and writing to the "contents.json" file.
		$contentFile = new ContentFile($key);
		$contentFile->ReadContentsFile($fileAccess);
		return $contentFile;
	}

	function isZip(string $path) : bool{
		return str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".zip")||str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".mcpack");
	}

	function searchKey(FileAccess $fileAccess, OutputInterface $output) : ?string{
		$keyFile = null;
		$key = null;
		$output->writeln("scanning *.key files...");
		[$key, $keyFile] = $fileAccess->searchKey();
		if($keyFile !== null){
			$output->writeln("found keyFile: ".$keyFile);
		}else{
			$output->writeln("keyFile not found");
		}
		return $key;
	}
};
