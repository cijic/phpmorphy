<?php
class ConstNames_Grammems_Eng {
	public
		$eSingular = 0,
		$ePlural = 1,
		$eMasculinum = 2,
		$eFeminum = 3,
		$eAnimative = 4, 
		$ePerfective = 5, 
		$eNominative = 6, 
		$eObjectCase = 7, 
		$eNarrative = 8, 
		$eGeographics = 9, 
		$eProper = 10, 
		$ePersonalPronoun = 11, 
		$ePossessive = 12, 
		$ePredicative = 13, 
		$eUncountable = 14, 
		$eReflexivePronoun = 15, 
		$eDemonstrativePronoun = 16, 
		$eMass = 17, 
		$eComparativ  = 18, 
		$eSupremum = 19,
		$eFirstPerson = 20, 
		$eSecondPerson = 21, 
		$eThirdPerson = 22, 
		$ePresentIndef = 23, 
		$eInfinitive = 24,
		$ePastIndef = 25,
		$ePastParticiple = 26,
		$eGerund = 27,
		$eFuturum = 28,
		$eConditional = 29,

		$eApostropheS = 30,
        $eApostrophe = 31,
		$eNames = 32,
        $eOrganisation = 33;
}

class ConstNames_Poses_Eng {
	public
	  $eNOUN  = 0, 
	  $eADJ = 1, 
	  $eVERB = 2, 
	  $eVBE = 3, 
	  $eMOD = 4, 
	  $eNUMERAL = 5,
	  $eCONJ  = 6, 
	  $eINTERJ = 7, 
	  $ePREP = 8, 
	  $ePARTICLE = 9, 
	  $eART = 10, 
	  $eADV = 11, 
	  $ePN = 12, 
	  $eORDNUM = 13,  
	  $ePRON = 14,
	  $ePOSS = 15,
	  $ePN_ADJ = 16;
}

class ConstNames_Eng extends ConstNames_Base {
	protected $poses = array(
		"NOUN",
		"ADJECTIVE", // полное прилагательное
		"VERB",
		"VBE",
		"MOD",
		"NUMERAL",
		"CONJ",
		"INT",
		"PREP",
		"PART",
		"ARTICLE",
		"ADVERB",
		"PN",
		"ORDNUM",
		"PRON",
		"POSS",
		"PN_ADJ"
	);
	
	protected $grammems = array(
      "sg", "pl", "m", "f", "anim", "perf", "nom", "obj", "narr","geo", 
      "prop" ,"pers", "poss", "pred", "uncount", "ref", "dem", "mass", "comp", "sup", 
      "1", "2", "3", "prsa", "inf", "pasa", "pp", "ing", "fut", "if", "plsq", "plsgs", "name","org"
	);
	
	function getPartsOfSpeech() {
		return $this->combineObjAndArray(new ConstNames_Poses_Eng(), $this->poses);
	}
	
	function getGrammems() {
		return $this->combineObjAndArray(new ConstNames_Grammems_Eng(), $this->grammems);
	}
}
