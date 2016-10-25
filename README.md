Parse INI
==================

ParseINI adalah library PHP untuk mempersing string berformat [INI][2] menjadi
variable. Library ini di-design untuk terintegrasi dengan Library
[Configuration Editor][1]. Untuk kebutuhan parse dan unparse sekaligus, maka
sebaiknya gunakan Library [Configuration Editor][1]. Namun jika hanya untuk
parsing, maka library ini sudah cukup memenuhi kebutuhan tersebut.

[1]: https://github.com/ijortengab/configuration-editor
[2]: https://en.wikipedia.org/wiki/INI_file

## Requirements
 - PHP > 5.4
 - ijortengab/tools

## Comparison

PHP telah memiliki fungsi untuk memparsing file dot ini (```parse_ini_file```)
atau string berformat ini  (```parse_ini_string```). Tujuan utama dibuat library
ini adalah untuk mempertahankan *comment* yang terdapat pada informasi di format
INI agar tetap exists saat dilakukan dump/unparse. Untuk mendapatkan fitur ini,
gunakan library [Configuration Editor][1].

Library ParseINI dapat mempersing format yang tidak bisa di-handle oleh fungsi
parsing bawaan PHP, contohnya pada format sbb:
```ini
key[child][] = value
key[child][] = other
```

Format diatas terinspirasi pada file [dot info][4] dari Drupal 7.

[4]: https://www.drupal.org/node/542202

## Repository

Tambahkan code berikut pada composer.json jika project anda membutuhkan library
ini. Perhatikan _trailing comma_ agar format json anda tidak rusak.

```json
{
    "require": {
        "ijortengab/parse-ini": "master"
    },
    "minimum-stability": "dev",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/ijortengab/parse-ini"
        }
    ]
}
```

## Usage

Disarankan untuk menghandle RuntimeException saat menjalankan method ::parse()
apabila format diragukan kevalidasiannya.

```php
use IjorTengab\ParseINI\ParseINI;
use IjorTengab\ParseINI\RuntimeException;
require 'vendor/autoload.php'; // Sesuaikan dgn path anda.
$ini = file_get_contents('file.ini');
$obj = new ParseINI($ini);
try {
    $obj->parse();
}
catch(RuntimeException $e) {
    var_dump($e);
}
$result = $obj->getResult();
var_dump($result);
```
