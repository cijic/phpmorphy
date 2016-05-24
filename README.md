# vladkolodka/phpmorphy

phpMorphy --- morphological analyzer library for Russisan, English and German languages.  
```vladkolodka/phpMorphy``` is wrapper for phpMorphy library.

Source website (in russian): http://phpmorphy.sourceforge.net/  
SF project: http://sourceforge.net/projects/phpmorphy  
Wrapper on Github: https://github.com/vladkolodka/phpmorphy

This library allow retireve follow morph information for any word:
- Base (normal) form
- All forms
- Grammatical (part of speech, grammems) information

## Install

Via Composer
``` bash
$ composer require vladkolodka/phpmorphy
```

## Usage
``` php
$morphy = new vladkolodka\phpMorphy\Morphy('en');
echo $morphy->getPseudoRoot('FIGHTY');
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
vladkolodka\phpMorphy\MorphyServiceProvider::class,
```

Section ```aliases```
``` php
'Morphy'    => vladkolodka\phpMorphy\Facade\Morphy::class,
```

## Change log
Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security
If you discover any security related issues, please email vladkolodka@gmail.com instead of using the issue tracker.

## License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.