Info Configuration
==================

Dumper and parser for dot info file.

   > "The file format is "INI-like" for ease of authoring, but also includes 
   > some "Drupalisms" such as the array[] syntax so standard PHP functions 
   > for reading/writing INI files can't be used." [Source][blog]
   
File dot ini (.ini) adalah file untuk menyimpan konfigurasi buatan Microsoft 
dan digunakan oleh PHP untuk menyimpan konfigurasi global (php.ini). 
Kelemahan file dot ini adalah tidak bisa menyimpan konfigurasi secara array 
multidimensi (terbatas hanya 2 level kedalaman array). 

Parse Info adalah library PHP untuk serialize dan unserialize variable bertype 
array menjadi content file dot info.

Contoh file dot ini:
```ini
; global
key1 = value
key2 = value
[parent1]
; inside array parent1
key1 = value
key2 = value
[parent2]
; inside array parent2
key1 = value
key2 = value
```

Contoh file dot info:
```ini
; global
key1 = value
key2 = value
; inside array parent1
parent1[key1] = value
parent1[key2] = value
parent1[key3][child] = value
; inside array parent1
parent2[key1] = value
parent2[key2] = value
parent2[key3][child] = value
```

## Usage

```php
// Mengubah array menjadi isi file dot info, 
// seperti serialize() dan json_encode().
$content = ParseInfo::encode($array);
// Mengubah isi file dot info menjadi array,
// seperti unserialize() dan json_decode().
$array = ParseInfo::decode($content);
```

## Reference

 *   [Writing module .info files](https://www.drupal.org/node/542202)
 *   [Acquia Blog][blog]

[blog]: https://www.acquia.com/blog/ultimate-guide-drupal-8-episode-7-code-changes-drupal-8
