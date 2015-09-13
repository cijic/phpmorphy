@call gen_graminfo.bat
@%PHPRC%/php.exe -f %~pd0\extract_ancodes_map.php -- %*
