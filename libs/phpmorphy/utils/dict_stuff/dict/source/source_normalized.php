<?php
require_once(dirname(__FILE__) . '/source.php');
require_once(dirname(__FILE__) . '/utils/gramtab/helper.php');
require_once(dirname(__FILE__) . '/../../../libs/decorator.php');

class phpMorphy_Dict_Ancode_Normalized {
    private
        $id,
        $name,
        $pos_id,
        $grammems_ids
        ;
    
    function __construct($id, $name, $posId, $grammemsIds) {
        $this->id = (int)$id;
        $this->pos_id = (int)$posId;
        $this->grammems_ids = array_map('intval', (array)$grammemsIds);
        $this->name = (string)$name;
    }
    
    function getId() {
        return $this->id;
    }
    
    function getPartOfSpeechId() {
        return $this->pos_id;
    }
    
    function getGrammemsIds() {
        return $this->grammems_ids;
    }

    function getName() {
        return $this->name;
    }
}

class phpMorphy_Dict_PartOfSpeech {
    private
        $id,
        $name,
        $is_predict;
    
    function __construct($id, $name, $isPredict) {
        $this->id = (int)$id;
        $this->is_predict = (bool)$isPredict;
        $this->name = (string)$name;
    }
    
    function getName() {
        return $this->name;
    }
    
    function getId() {
        return $this->id;
    }
    
    function isPredict() {
        return $this->is_predict;
    }
}

class phpMorphy_Dict_Grammem {
    private
        $id,
        $name,
        $shift;
    
    function __construct($id, $name, $shift) {
        $this->id = (int)$id;
        $this->shift = (int)$shift;
        $this->name = (string)$name;
    }
    
    function getName() {
        return $this->name;
    }
    
    function getId() {
        return $this->id;
    }
    
    function getShift() {
        return $this->shift;
    }
}

lmbDecorator::generate('phpMorphy_Dict_FlexiaModel', 'phpMorphy_Dict_FlexiaModel_Decorator');

class phpMorphy_Dict_FlexiaModel_Normalized extends phpMorphy_Dict_FlexiaModel_Decorator {
    protected $manager;

    function __construct(phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, phpMorphy_Dict_FlexiaModel $inner) {
        parent::__construct($inner);
        $this->manager = $manager;
    }

    function getIterator() {
        return new phpMorphy_Iterator_TransformCallback(
            parent::getIterator(),
            array($this, '__decorate'),
            phpMorphy_Iterator_TransformCallback::CALL_WITHOUT_KEY
        );
    }

    function offsetGet($offset) {
        return $this->decorate(parent::offsetGet($offset));
    }

    function __decorate(phpMorphy_Dict_Flexia_Interface $flexia) {
        return new phpMorphy_Dict_Flexia_Normalized($this->manager, $flexia);
    }
}

lmbDecorator::generate('phpMorphy_Dict_Flexia_Interface', 'phpMorphy_Dict_Flexia_Decorator');

// Decorator over flexia
class phpMorphy_Dict_Flexia_Normalized extends phpMorphy_Dict_Flexia_Decorator {
    protected
        $manager;

    function __construct(phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, phpMorphy_Dict_Flexia $inner) {
        parent::__construct($inner);
        $this->manager = $manager;
    }

    function getAncodeId() { return $this->manager->resolveAncode(parent::getAncodeId()); }
}

lmbDecorator::generate('phpMorphy_Dict_Lemma_Interface', 'phpMorphy_Dict_Lemma_Decorator');

// Decorator over lemma
class phpMorphy_Dict_Lemma_Normalized extends phpMorphy_Dict_Lemma_Decorator {
    protected
        $manager;

    function __construct(phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, phpMorphy_Dict_Lemma $inner) {
        parent::__construct($inner);
        $this->manager = $manager;
    }
    
    function getAncodeId() {
        return $this->manager->resolveAncode(parent::getAncodeId());
    }
}

class phpMorphy_Dict_Source_Normalized_DecoratingIterator extends IteratorIterator {
    protected
        $manager,
        $new_class;

    function __construct(Traversable $it, phpMorphy_Dict_Source_Normalized_Ancodes_Manager $manager, $newClass) {
        parent::__construct($it);

        $this->manager = $manager;
        $this->new_class = $newClass;
    }

    function current() {
        return $this->decorate(parent::current());
    }

    protected function decorate($object) {
        $new_class = $this->new_class;

        return new $new_class($this->manager, $object);
    }
};

class phpMorphy_Dict_Source_Normalized_Ancodes_Manager {
    private
        $ancodes_map = array(),
        $poses_map = array(),
        $grammems_map = array(),
        $ancodes = array(),
        $helper
        ;
        
    function __construct(phpMorphy_Dict_Source_Interface $source) {
        $this->helper = phpMorphy_GramTab_Const_Factory::create($source->getLanguage());
        
        foreach($source->getAncodes() as $ancode) {
            $this->ancodes[] = $this->createAncode($ancode);
        }
    }
    
    protected function registerAncodeId($ancodeId) {
        if(!isset($this->ancodes_map[$ancodeId])) {
            $new_id = count($this->ancodes_map);

            $this->ancodes_map[$ancodeId] = $new_id;
        }
        
        return $this->ancodes_map[$ancodeId];
    }
    
    protected function registerPos($pos, $isPredict) {
        $pos = mb_convert_case($pos, MB_CASE_UPPER, 'utf-8');
        
        if(!isset($this->poses_map[$pos])) {
            $pos_id = $this->helper->getPartOfSpeechIdByName($pos);
            
            $this->poses_map[$pos] = $this->createPos($pos_id, $pos, $isPredict);
        }
        
        return $this->poses_map[$pos]->getId();
    }
    
    protected function createPos($id, $name, $isPredict) {
        return new phpMorphy_Dict_PartOfSpeech($id, $name, $isPredict);
    }
    
    protected function createGrammem($id, $name, $shift) {
        return new phpMorphy_Dict_Grammem($id, $name, $shift);
    }
    
    protected function registerGrammems(Traversable $it) {
        $result = array();
        
        foreach($it as $grammem) {
            $grammem = mb_convert_case($grammem, MB_CASE_UPPER, 'utf-8');
            
            if(!isset($this->grammems_map[$grammem])) {
                $grammem_id = $this->helper->getGrammemIdByName($grammem);
                $shift = $this->helper->getGrammemShiftByName($grammem);
                
                $this->grammems_map[$grammem] = $this->createGrammem($grammem_id, $grammem, $shift);
            }
            
            $result[] = $this->grammems_map[$grammem]->getId();
        }
        
        return $result;
    }
    
    function getAncodesMap() {
        return $this->ancodes_map;
    }
    
    function getPosesMap() {
        return $this->poses_map;
    }
    
    function getGrammemsMap() {
        return $this->grammems_map;
    }
    
    function resolveAncode($ancodeId) {
        if(!isset($this->ancodes_map[$ancodeId])) {
            throw new Exception("Unknown ancode_id '$ancodeId' given");
        }
        
        return $this->ancodes_map[$ancodeId];
    }
    
    function getAncodes() {
        return $this->ancodes;
    }
    
    function getAncode($ancodeId, $resolve = true) {
        $ancode_id = $resolve ? $this->resolveAncode($ancodeId) : (int)$ancodeId;
        
        return $this->ancodes[$ancode_id];
    }
    
    
    protected function createAncode(phpMorphy_Dict_Ancode $ancode) {
        return new phpMorphy_Dict_Ancode_Normalized(
            $this->registerAncodeId($ancode->getId()),
            $ancode->getId(),
            $this->registerPos($ancode->getPartOfSpeech(), $ancode->isPredict()),
            $this->registerGrammems($ancode->getGrammems()),
            $ancode->getId()
        );
    }
};

lmbDecorator::generate('phpMorphy_Dict_Source_Interface', 'phpMorphy_Dict_Source_Normalized_Decorator');

class phpMorphy_Dict_Source_Normalized_Ancodes extends phpMorphy_Dict_Source_Normalized_Decorator {
    protected
        $manager;

    static function wrap(phpMorphy_Dict_Source_Interface $source) {
        $self = __CLASS__;

        if($source instanceof $self) {
            return $source;
        }

        return new $self($source);
    }

    function __construct(phpMorphy_Dict_Source_Interface $inner) {
        parent::__construct($inner);

        $this->manager = $this->createManager($inner);
    }

    protected function createManager($inner) {
        return new phpMorphy_Dict_Source_Normalized_Ancodes_Manager($inner);
    }

    function getPoses() {
        return array_values($this->manager->getPosesMap());
    }

    function getGrammems() {
        return array_values($this->manager->getGrammemsMap());
    }

    function getAncodesNormalized() {
        return $this->manager->getAncodes();
    }

    function getFlexiasNormalized() {
        return $this->createDecoratingIterator($this->getFlexias(), 'phpMorphy_Dict_FlexiaModel_Normalized');
    }

    function getLemmasNormalized() {
        return $this->createDecoratingIterator($this->getLemmas(), 'phpMorphy_Dict_Lemma_Normalized');
    }

    protected function createDecoratingIterator(Traversable $it, $newClass) {
        return new phpMorphy_Dict_Source_Normalized_DecoratingIterator($it, $this->manager, $newClass);
    }
}
