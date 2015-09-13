<?php
class ConstNames_Grammems_Ger {
	public
	// unknown 0..3
	$gNoaUnk = 0, 
	$gPredikBenutz = 1, 
	$gProUnk = 2,
	$gTmpUnk = 3,
	
	
	// eigennamen 4..14
	$gNac=4,
	$gMou=5,
	$gCou=6,
	$gGeo=7,
	$gWasser=8,
	$gGeb=9,
	$gStd=10,
	$gLok=11,
	$gVor=12,  
	
	//  reflexive Verben
	$gSichAcc=13,
	$gSichDat=14,
	
	
	
	// verb clasess 15..18
	$gSchwach=15,
	$gNichtSchwach=16,
	$gModal=17,
	$gAuxiliar=18,
	
	
	// verb forms 19..26
	$gKonj1=19,
	$gKonj2=20,
	$gPartizip1=21,
	$gPartizip2=22,
	$gZuVerbForm=23,
	$gImperativ=24,
	$gPraeteritum=25,
	$gPrasens=26,
	
	//adjective 27..29
	$gGrundform=27,
	$gKomparativ=28,
	$gSuperlativ=29,
	
	// konjunk 30..34
	$gProportionalKonjunktion=30,
	$gInfinitiv=31, // used also for verbs
	$gVergleichsKonjunktion=32,
	$gNebenordnende=33,
	$gUnterordnende=34,
	
	
	
	//pronouns 35..41
	$gPersonal=35,
	$gDemonstrativ=36,
	$gInterrogativ=37,
	$gPossessiv=38,
	$gReflexiv=39,
	$gRinPronomen=40,
	$gAlgPronomen=41,
	
	//adjective's articles 42.44
	$gAdjektiveOhneArtikel=42,
	$gAdjektiveMitUnbestimmte=43,
	$gAdjektiveMitBestimmte=44,
	
	
	
	//persons 44..47
	$gErstePerson=45,
	$gZweitePerson=46,
	$gDrittePerson=47,  
	
	//genus 48..50
	$gFeminin=48,
	$gMaskulin=49,
	$gNeutrum=50,
	
	
	
	// number 51..52
	$gPlural=51,
	$gSingular=52,
	
	
	//cases 53..56
	$gNominativ=53,
	$gGenitiv=54,
	$gDativ=55,
	$gAkkusativ=56,
	
	// abbreviation
	$gAbbreviation=57,
	
	//Einwohnerbezeichnung
	$gEinwohner=58,
	
	//
	$gTransitiv=59,
	$gIntransitiv=60,
	$gImpersonal=61;
}

class ConstNames_Poses_Ger {
	public
	  $gART  = 0, 
	  $gADJ = 1, 
	  $gADV = 2, 
	  $gEIG = 3, 
	  $gSUB = 4, 
	  $gVER = 5,
	  $gPA1  = 6, 
	  $gPA2 = 7, 
	  $gPRONOMEN = 8, 
	  $gPRP = 9, 
	  $gKON = 10, 
	  $gNEG = 11, 
	  $gINJ = 12, 
	  $gZAL = 13,  
	  $gZUS = 14, 
	  $gPRO_BEG = 15,
	  $gZU_INFINITIV = 16;
}

class ConstNames_Ger extends ConstNames_Base {
	protected $poses = array(
		"ART",
		"ADJ", 
		"ADV",
		"EIG",
		"SUB",
		"VER",
		"PA1",
		"PA2",
		"PRO",
		"PRP",
		"KON",
		"NEG",
		"INJ",
		"ZAL",
		"ZUS",
		"PROBEG",
		"INF"
	);
	
	protected $grammems = array(
		//common unknown 0..3
		"noa", // ohne artikel
		"prd", // predikativ
		"pro",
		"tmp",
		
		
		// eigennamen 4..12
		"nac","mou","cou","geo","wat","geb","std","lok","vor",  
		
		//  reflexive Verben 13..14
		"sich-akk","sich-dat",
		
		// verb clasess 15..18
		"sft","non","mod","aux",
		
		// verb forms 19..26
		"kj1","kj2","pa1","pa2","eiz","imp","prt","prae",
		
		//adjective 27..29
		"gru","kom","sup",
		
		// konjunk 30..34
		"pri","inf","vgl","neb","unt",
		
		
		//pronouns 35..41
		"per","dem","inr","pos","ref","rin","alg",
		
		//adjective's articles 42.44
		"sol","ind","def",
		
		//persons 45..47
		"1",  "2",  "3",  
		
		//genus 48..50
		"fem","mas","neu",
		
		
		// number 51..52
		"plu","sin",
		
		//cases 53..56
		"nom","gen","dat","akk",
		
		//abbreviation 57
		"abbr",
		
		//Einwohnerbezeichnung 58
		"ew",
		
		//Transitiv 59,60,61
		"trans", "intra", "imper"
	);
	
	function getPartsOfSpeech() {
		return $this->combineObjAndArray(new ConstNames_Poses_Ger(), $this->poses);
	}
	
	function getGrammems() {
		return $this->combineObjAndArray(new ConstNames_Grammems_Ger(), $this->grammems);
	}
}
