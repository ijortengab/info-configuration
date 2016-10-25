nanti jika 
value awal
autorun = off

dan user mengedit
autorun = true

maka di file tertulis
autorun = on


Contoh Perbedaan hasil:

1. 
$ini = <<<INI
aa = bb
'cc = dd' = ee
INI;
Hasil dari parse_ini_string adalah
var_dump(b): array(2) {
  ["aa"]=>
  string(2) "bb"
  ["'cc"]=>
  string(2) "dd"
}
</pre>
Hasil dari ParseINI adalah
var_dump(b): array(2) {
  ["aa"]=>
  string(2) "bb"
  ["cc = dd"]=>
  string(2) "ee"
}

2.
$ini = <<<INI
aa = bb
cc = ;dd
INI;