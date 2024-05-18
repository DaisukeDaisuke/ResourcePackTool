<?php

declare(strict_types=1);

namespace resourceTools\command;

use resourceTools\helper\decipher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;
use resourceTools\exception\ContentsFileException;
use resourceTools\helper\Transformer;
use resourceTools\helper\ManifestReader;
use resourceTools\fileaccess\FileAccess;

class DecryptRecursive extends Command{
    private const TYPE_ZIP = "zip";
    private const TYPE_DIR = "dir";

    protected function configure(){
        $this
            ->setName('rdecrypt')
            ->setDescription('decrypt resource recursively')
            ->addArgument('path', InputArgument::REQUIRED, 'target dir')  //FallenDeadAssets
            ->addOption('output', "o", InputOption::VALUE_REQUIRED, 'output dir', "decrypt")
            ->addOption('with-progress', "p", InputOption::VALUE_NONE, 'use progress bar')
            ->addOption('trim', "t", InputOption::VALUE_NONE, "no trim the json file.")
            ->addOption('key', "k", InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'This key is used to decrypt preferentially')
            ->addOption('originalName', "d", InputOption::VALUE_NONE, 'Do not change the output name folder name');
        //->addOption('override', "w", InputOption::VALUE_NONE, 'Output and input are the same');
        //->addOption('thread', "j", InputOption::VALUE_REQUIRED, 'thread count', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int{
        if(!$output instanceof ConsoleOutputInterface){
            throw new \LogicException('This command accepts only an instance of "ConsoleOutputInterface".');
        }

        $path = $input->getArgument('path');
        $outputDir = $input->getOption('output');
        $withProgress = $input->getOption('with-progress');
        $trim = $input->getOption('trim');
        $preferential_keys = $input->getOption('key');
        $useOriginalName = $input->getOption('originalName');
        //$mustOverride = $input->getOption('override');
        //$thread = $input->getOption('thread');

        $output->writeln("mode: decrypt");
        $output->writeln("target: ".$path);
        $output->writeln("output: ".$outputDir);
        //$output->writeln("thread: ".$thread);

        $originalPath = $path;
        $path = realpath($path);
        if($path === false){
            $output->writeln("<error>$originalPath not found</error>");
            return 1;
        }
        if(!is_dir($path)){
            $output->writeln("<error>$path is not directory</error>");
            return 1;
        }

        if($outputDir[-1] !== "/"&&$outputDir[-1] !== "\\"){
            $outputDir .= DIRECTORY_SEPARATOR;
        }

        $keys = [];
        $files = [];
        foreach(scandir($path) as $file){
            $key = null;
            if($file === "."||$file === ".."){
                continue;
            }
            //ex
            $extention = pathinfo($file, PATHINFO_EXTENSION);
            $file = Path::join($path, $file);
            if(($extention === "key"||$extention === "keys"||$extention === "txt"||$extention === "log"||$extention === "yml")&&filesize($file) >= 32&&filesize($file) < 1024 * 8){
                //key + uuid4
                //keyanduuid
                if(preg_match('/(([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12}))/iu', basename($file), $match)){
                    $output->writeln("scaning keys: skinpng  $file");
                    continue;
                }
                $output->writeln("scaning keys list: $file");
                $uuid = null;
                $key = null;
                $counter = 0;
                foreach(file($file) as $line){
                    $uuid = null;
                    $key = null;
                    $line = trim($line);
                    if(strlen($line) === 36&&preg_match('/(([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12}))/iu', $line, $match)){
                        $uuid = $match[1];
                        continue;
                    }
                    if(strlen($line) === 32){
                        $preferential_keys[] = $line;
                        $output->writeln("Primary key found: ".$line);
                        continue;
                    }
                    if(strlen($line) > 36){
                        if(preg_match('/(([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12}))(.*)$/iu', $line, $match)&&strlen($match[7]) > 32){
                            $uuid1 = $match[1];
                            $key1 = substr(trim($match[7]), -32);
                            if(strlen($key1) !== 32){
                                continue;
                            }
                            $keys[$uuid1] = $key1;
                            $uuid = null;
                            $key = null;
                            $output->writeln("found key: $uuid1/$key1");
                        }
                    }
                    if($uuid !== null&&$key !== null){
                        $keys[$uuid] = $key;
                        $uuid = null;
                        $key = null;
                    }
                }
                $output->writeln("found ".count($keys)." keys");
                continue;
            }

            if(is_dir($file)){
                $contents = Path::join($file, "contents.json");
                $manifest = Path::join($file, "manifest.json");
                if(!is_file($contents)||!is_file($manifest)){
                    $output->writeln($file." was skipped. reason: not resource pack");
                    continue;
                }
                $files[basename($file)] = $file;
                $output->writeln("scaning keys: $file");
                foreach(scandir($file) as $file1){
                    $extention = pathinfo($file1, PATHINFO_EXTENSION);
                    if($extention === "key"&&filesize($file1) >= 32){
                        try{
                            $uuid1 = ManifestReader::getUUID(new FileAccess($file));
                        }catch(\Throwable $e){
                            $output->writeln("scaning keys: ".$file." was skipped. reason: Unable to read ".$file."/".$file1."/manifest.json");
                            continue;
                        }
                        foreach(file($file1) as $line){
                            if(strlen($line) === 32){
                                $keys[$uuid1] = $line;
                                $output->writeln("found key: $uuid1/$line ($file1)");
                            }
                        }
                    }
                }
                continue;
            }
            if($this->isZip($file)){
                $files[pathinfo(basename($file), PATHINFO_FILENAME)] = $file;
                continue;
            }
        }
        $deciphers = [];
        foreach($files as $name => $file){
            $decipher = new decipher($file, $outputDir, $name, !$trim, $useOriginalName);
            $key = null;
            //check primary key
            foreach($preferential_keys as $preferential_key){
                try{
                    $decipher->updateKey($preferential_key);
                    $key = $preferential_key;
                    break;
                }catch(ContentsFileException $exception){
                    $output->writeln($name.": not match ".$preferential_key);
                }
            }
            $key = $key ?? $decipher->getKey() ?? $keys[$decipher->getUUID()] ?? null;
            if($key === null){
                $output->writeln($name." was skipped. reason: key not found");
                continue;
            }
            try{
                $decipher->updateKey($key);
            }catch(ContentsFileException $exception){
                $output->writeln($name.": Key mismatch! skipped, key: ".$key.", exception: ".$exception->getMessage());
                continue;
            }
            $deciphers[$name] = $decipher;
        }
        //collecting files


        $output->writeln("total: ".count($deciphers));
        $output->writeln("collecting files");
        $totalFiles = 0;
        /**
         * @var decipher $decipher
         */
        foreach($deciphers as $name => $decipher){
            [$count, $excluded] = $decipher->collectFiles();
            $output->writeln($name.": ".$count." files, ".$excluded." excluded");
            $totalFiles += $count;
        }
        $output->writeln("total files: ".$totalFiles);
        if($totalFiles === 0){
            $output->writeln("\nnothing to do.");
            return 0;
        }
        $output->writeln("start decrypting...");
        $section1 = $output->section();
        $section2 = $output->section();

        $bar1 = new ProgressBar($section1, $totalFiles);
        $bar2 = new ProgressBar($section2, count($deciphers));
        $bar2->setProgressCharacter('#');
        /**
         * @var decipher $decipher
         */
        foreach($bar2->iterate($deciphers) as $decipher){
            foreach($decipher->run() as $file){
                $bar1->advance();
            }
        }
        $bar1->finish();
		$output->writeln("\ndone.\n");

        foreach(Transformer::calculatePercentage() as $extension => $array){
            [$percentage, $count] = $array;
            $output->writeln(sprintf("%s: %.2f%% (%d)", $extension, $percentage, $count));
        }
        return 0;
    }

    function isZip(string $path) : bool{
        return str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".zip")||str_ends_with(trim($path, DIRECTORY_SEPARATOR), ".mcpack");
    }
}