<?php
class ConstNames_Grammems_Rus {
	public
		 $rPlural     = 0,
		 $rSingular   = 1,

		 $rNominativ  = 2,
		 $rGenitiv    = 3,
		 $rDativ      = 4,
		 $rAccusativ  = 5,
		 $rInstrumentalis = 6,
		 $rLocativ    = 7,
		 $rVocativ    = 8,

		 $rMasculinum = 9,
		 $rFeminum    = 10,
		 $rNeutrum    = 11,
		 $rMascFem    = 12,


		 $rPresentTense = 13,
		 $rFutureTense = 14,
		 $rPastTense = 15,

		 $rFirstPerson = 16,
		 $rSecondPerson = 17,
		 $rThirdPerson = 18,

		 $rImperative = 19,

		 $rAnimative = 20,
		 $rNonAnimative = 21,

		 $rComparative = 22,

		 $rPerfective = 23,
		 $rNonPerfective = 24,

		 $rNonTransitive = 25,
		 $rTransitive = 26,

		 $rActiveVoice = 27,
		 $rPassiveVoice = 28,


		 $rIndeclinable = 29,
		 $rInitialism = 30,

		 $rPatronymic = 31,

		 $rToponym = 32,
		 $rOrganisation = 33,

		 $rQualitative = 34,
		 $rDeFactoSingTantum = 35,

		 $rInterrogative = 36,
		 $rDemonstrative = 37,

		 $rName	    = 38,
		 $rSurName	= 39,
		 $rImpersonal = 40,
		 $rSlang	= 41,
		 $rMisprint = 42,
		 $rColloquial = 43,
		 $rPossessive = 44,
		 $rArchaism = 45,
		 $rSecondCase = 46,
		 $rPoetry = 47,
		 $rProfession = 48,
		 $rSuperlative = 49,
		 $rPositive = 50;
}

class ConstNames_Poses_Rus {
	public
		$rNOUN  = 0, 
		$rADJ_FULL = 1, 
		$rVERB = 2, 
		$rPRONOUN = 3, 
		$rPRONOUN_P = 4, 
		$rPRONOUN_PREDK = 5,
		$rNUMERAL  = 6, 
		$rNUMERAL_P = 7, 
		$rADV = 8, 
		$rPREDK  = 9, 
		$rPREP = 10,
		$rPOSL = 11,
		$rCONJ = 12,
		$rINTERJ = 13,
		$rINP = 14,
		$rPHRASE = 15,
		$rPARTICLE = 16,
		$rADJ_SHORT = 17,
		$rPARTICIPLE = 18,
		$rADVERB_PARTICIPLE = 19,
		$rPARTICIPLE_SHORT = 20,
		$rINFINITIVE = 21;
}

class ConstNames_Rus extends ConstNames_Base {
	protected $poses = array(
		"С",  // 0
		"П", // 1
		"Г", // 2
		"МС", // 3
		"МС-П", // 4
		"МС-ПРЕДК", // 5
		"ЧИСЛ", // 6
		"ЧИСЛ-П", // 7
		"Н", // 8
		"ПРЕДК", //9 
		"ПРЕДЛ", // 10
		"ПОСЛ", // 11
		"СОЮЗ", // 12
		"МЕЖД", // 13
		"ВВОДН",// 14
		"ФРАЗ", // 15
		"ЧАСТ", // 16
		"КР_ПРИЛ",  // 17
		"ПРИЧАСТИЕ", //18
		"ДЕЕПРИЧАСТИЕ", //19
		"КР_ПРИЧАСТИЕ", // 20
		"ИНФИНИТИВ"  //21
	);
	
	protected $grammems = array(
		// 0..1
	   	"мн","ед",
		// 2..8
		"им","рд","дт","вн","тв","пр","зв",
		// род 9-12
		"мр","жр","ср","мр-жр",
		// 13..15
		"нст","буд","прш",
		// 16..18
		"1л","2л","3л",	
		// 19
		"пвл",
		// 20..21
		"од","но",	
		// 22
		"сравн",
		// 23..24
		"св","нс",	
		// 25..26
		"нп","пе",
		// 27..28
		"дст","стр",
		// 29-31
		"0", "аббр", "отч",
		// 32-33
		"лок", "орг",
		// 34-35
		"кач", "дфст",
		// 36-37 (наречия)
		"вопр", "указат",
		// 38..39
		"имя","фам",
		// 40
		"безл",
		// 41,42
		"жарг", "опч",
		// 43,44,45
		"разг", "притяж", "арх",
		// для второго родительного и второго предложного
		"2",
		"поэт", "проф",
		"прев", "полож"
	);
	
	function getPartsOfSpeech() {
		return $this->combineObjAndArray(new ConstNames_Poses_Rus(), $this->poses);
	}
	
	function getGrammems() {
		return $this->combineObjAndArray(new ConstNames_Grammems_Rus(), $this->grammems);
	}
}
