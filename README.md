# phpmorphy

phpMorphy -- morphological analyzer library for Russisan, English and German languages

Website (in russian): http://phpmorphy.sourceforge.net/
SF project: http://sourceforge.net/projects/phpmorphy


This library allow retireve follow morph information for any word:
 - Base(normal) form
 - All forms
 - Grammatical(part of speech, grammems) information

------------ SPEED -------------
 *-------------------*
 | Single word mode: |
 *-----------------------------------------------------------*
 | mode | base form  | all forms | all forms with gram. info |
 |-----------------------------------------------------------|
 | FILE | 1000       | 800       | 600                       |
 |-----------------------------------------------------------|
 | SHM  | 2200       | 1100      | 800                       |
 |-----------------------------------------------------------|
 | MEM  | 2500       | 1200      | 900                       |
 *-----------------------------------------------------------*

 *------------*
 | Bulk mode: |
 *-----------------------------------------------------------*
 | mode | base form | all forms  | all forms with gram. info |
 |-----------------------------------------------------------|
 | FILE | 1700      | 800        | 700                       |
 |-----------------------------------------------------------|
 | SHM  | 3200      | 800        | 700                       |
 |-----------------------------------------------------------|
 | MEM  | 3500      | 800        | 700                       |
 *-----------------------------------------------------------*

Note:
  All values are words per second speed.
  Test platform: PHP 5.2.3, AMD Duron 800 with 512Mb memory, WinXP

------------ INSTALLATION -------------
 See INSTALL file in current directory

------------ USAGE -------------
 See example in ./examples directory

## Install

Via Composer

``` bash
$ composer require cijic/phpmorphy
```

## Usage

``` php
$morphy = new Cijic\PHPMorphy();
var_dump($morphy);
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email altcode@ya.ru instead of using the issue tracker.

## Credits

- [Chiril Teterea][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.