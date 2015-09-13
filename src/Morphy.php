<?php

namespace cijic\phpMorphy;

use phpMorphy;

class Morphy
{
    protected $language;
    protected $morphy;
    private $dictionaries = array('ru' => 'ru_RU', 'en' => 'en_EN');

    public function __construct($language = 'ru')
    {
        $this->dictsPath = __DIR__ . '/../libs/phpmorphy/dicts';
        $this->language = $this->dictionaries[$language];
        $options = [];

        if (defined('PHPMORPHY_STORAGE_FILE')) {
            $options = ['storage' => PHPMORPHY_STORAGE_FILE];
        } else {
            $options = ['storage' => 'file'];
        }

        try {
            $this->morphy = new phpMorphy($this->dictsPath, $this->language, $options);
        } catch(phpMorphy_Exception $e) {
            throw new Exception('Error occured while creating phpMorphy instance: ' . PHP_EOL . $e);
        }
    }

    public function getMorphy()
    {
        return $this->morphy;
    }
}
