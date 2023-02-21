# resourceTools
Tools to encrypt/decrypt ResourcePack
#### Required Environment
- php 8.0 or php 8.1
- openssl extension
- ext-zip extension (optional)

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