# resourceTools
Tools to encrypt/decrypt ResourcePack(minecraft)

Rust version(Other authors): https://github.com/valaphee/mcrputil  

This repository does not reference any literature, I discovered these on my own  
Therefore, the Unlicense license is legal, since I am not citing any code  
  
Please make a backup of your resource pack before running this code, it can even destroy your original resource pack  

#### Required Environment
- php 8.0 or php 8.1, 8.2 and php 8.3 are maybe supported
- openssl extension
- ext-zip extension (optional)

## command
```
  c           decode contents.json
  decrypt     decrypt resource
  encrypt     encrypt resource
  rdecrypt    decrypt resource recursively
```

## build
(and Install dependencies)
```
composer make-phar
```
To build a phar that does not use `symfony/console`, the following command can be used
```
composer make-mini
```
In case composer is not installed globally, a portable composer.phar can be used to install dependencies
```
php composer.phar make-phar
```
> The portable `composer.phar` can be downloaded from the following link  
> https://getcomposer.org/download/latest-stable/composer.phar  

## usage
### encrypt

```
php resourceTools.phar e input -o output
```
### decrypt
If the key is not specified with the `-k` option, the program will search for the `*.key` file in the root folder of the resource pack
```
php resourceTools.phar d input -o output
```

## options
```
-k, --key=KEY         32byte keys
-o, --output=OUTPUT   output dir [default: "encrypt"]
-p, --with-progress   use progress bar
```

## Other usage
This script supports reading and writing zip files
```
php resourceTools.phar encrypt input.zip -o output.zip
php resourceTools.phar decrypt input.zip -o output.zip
```
The modularized program can also be executed directly
```
php encrypt.php input -o output
php decrypt.php input -o output
```

### override
(All contents.json should be deleted after processing)
```
php resourceTools.phar rd -d original -o original
```
