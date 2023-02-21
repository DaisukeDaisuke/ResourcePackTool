<?php

declare(strict_types=1);

namespace resourceTools\helper;

use resourceTools\fileaccess\CryptoFileAccess;
use resourceTools\fileaccess\FileAccess;
use resourceTools\fileaccess\ZipCryptoFileAccess;
use resourceTools\fileaccess\ZipFIleAccess;
use resourceTools\helper\ContentFile;
use resourceTools\helper\Transformer;
use Symfony\Component\Filesystem\Path;

class decipher extends Reader{
    private FileAccess $output_FileAccess;
	private string $output;


    /** @var string[] */
    private array $files = [];
    public function __construct(
		string $path,
		string         $outputDir,
		string         $outputFolderName,
        private bool   $trim
    ){
		parent::__construct($path);
		if($this->fileAccess->exists("manifest.json")){
			$this->uuid = ManifestReader::getUUID($this->fileAccess);
			$outputFolderName = ManifestReader::getName($this->fileAccess)."_".substr($this->uuid, 0, 8);
		}
		$this->output = Path::join($outputDir, $outputFolderName);
		$this->initOutputFileAccess();
    }

	public function initOutputFileAccess() : void{
		if($this->isZip($this->output)){
			$this->output_FileAccess = new ZipFIleAccess($this->output);
		}else{
			$this->output_FileAccess = new FileAccess($this->output);
		}
	}

    /**
     * @return int[]
     */
    public function collectFiles(): array{
        [$list, $filecount, $excluded, $notReadable] = Transformer::getFileList($this->fileAccess);
        $this->files = $list;
        return [$filecount, $excluded];
    }

    //run
    public function run() : \Generator{
        if(!isset($this->files)){
            $this->collectFiles();
        }
        return Transformer::runInternal($this->files, $this->fileAccess, $this->output_FileAccess, $this->trim);
    }
}
