<?php

namespace cijic\PHPMorphy;

use phpMorphy;

class Morphy
{
    protected $language;
    protected $morphy;
    private $dictionaries = array('ru' => 'ru_RU', 'en' => 'en_EN');

    public function __construct($language)
    {
        $this->dictsPath = __DIR__ . '/../../../vendor/phpmorphy/dicts';

        try {
            $this->morphy = new phpMorphy($this->dictsPath, $this->dictionaries[$this->language], array('storage' => PHPMORPHY_STORAGE_FILE));
        } catch(phpMorphy_Exception $e) {
            throw new Exception('Error occured while creating phpMorphy instance: ' . PHP_EOL . $e);
        }
    }

    public function getMorphy()
    {
        return $this->morphy;
    }
}
