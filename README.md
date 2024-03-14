# cijic/phpmorphy

phpMorphy --- morphological analyzer library for Russian, English, German and Ukrainian languages.  
```cijic/phpMorphy``` is Laravel wrapper for phpMorphy library with PHP7 support.

Source website (in russian): http://phpmorphy.sourceforge.net/  
SF project: http://sourceforge.net/projects/phpmorphy  
Wrapper on Github: https://github.com/cijic/phpmorphy

This library allow retireve follow morph information for any word:
- Base (normal) form
- All forms
- Grammatical (part of speech, grammems) information

## Install

Via Composer
``` bash
$ composer require cijic/phpmorphy
```

## Usage

```php
$morphy = new cijic\phpMorphy\Morphy('en');
print_r($morphy->getPseudoRoot('FIGHTY'));
```
result 
```
Array
(
    [0] => FIGHTY
    [1] => FIGHT
)
```
## Laravel support
### Facade
``` php
Morphy::getPseudoRoot('БОЙЦОВЫЙ')
```

### Add russian facade support

Add to config/app.php:

Section ```providers```
``` php
cijic\phpMorphy\MorphyServiceProvider::class,
```

Section ```aliases```
``` php
'Morphy'    => cijic\phpMorphy\Facade\Morphy::class,
```

## Change log
Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security
If you discover any security related issues, please email altcode@ya.ru instead of using the issue tracker.

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
