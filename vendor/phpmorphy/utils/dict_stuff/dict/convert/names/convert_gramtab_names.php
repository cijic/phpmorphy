<?php
interface ConstNames_Interface {
	function getPartsOfSpeech();
	function getGrammems();
}

abstract class ConstNames_Base implements ConstNames_Interface {
	protected function combineObjAndArray($obj, $ary) {
		$result = array();
		
		foreach($obj as $k => $v) {
			if(!isset($ary[$v])) {
				throw new Exception("Can`t find short name for $k(id = $v)");
			}
			
			$name = $this->convertLongName($k);
			$short_name = $this->convertShortName($ary[$v]);
			
			$result[] = array(
				'id' => $v,
				'long_name' => $name,
				'short_name' => $short_name
			);
		}
		
		return $result;
	}
	
	protected function convertLongName($name) {
		return $this->lmb_under_scores(substr($name, 1));
	}

	protected function convertShortName($name) {
		return mb_convert_case($name, MB_CASE_UPPER, 'utf-8');
	}
	
	private function lmb_under_scores($str) {
		$items = preg_split('~([A-Z][a-z0-9]+)~', $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$res = '';
		
		foreach($items as $item) {
			$res .= ($item == '_' ? '' : '_') . strtoupper($item);
		}
		
		return substr($res, 1);
	}
}

function dump_xml_file($lang, $outDir, $grammemsPrefix, $posesPrefix) {
	$lang = strtolower($lang);
	$out_file = "$outDir/$lang.xml";
	$clazz = 'ConstNames_' . ucfirst($lang);
	$php_file = dirname(__FILE__) . '/' . $lang . '.php';
	
	require_once($php_file);
	
	$obj = new $clazz();
	
	$writer = new XMLWriter();
	$writer->openUri($out_file);
	$writer->setIndent(true);
	$writer->setIndentString("    ");
	
	$writer->startDocument('1.0', 'UTF-8');
	
	$writer->startElement('gramtab');
	{
		// parts of speech
		$writer->startElement('part_of_speech');
		foreach($obj->getPartsOfSpeech() as $pos) {
			$writer->startElement('pos');
			{
				$writer->writeAttribute('name', $pos['short_name']);
				$writer->writeAttribute('const_name', $posesPrefix . $pos['long_name']);
				$writer->writeAttribute('id', $pos['id']);
			}
			$writer->endElement();
		}
		$writer->endElement();
		
		// grammems
		$writer->startElement('grammems');
		$shift = 0;
		foreach($obj->getGrammems() as $grammem) {
			$writer->startElement('grammem');
			{
				$writer->writeAttribute('name', $grammem['short_name']);
				$writer->writeAttribute('const_name', $grammemsPrefix . $grammem['long_name']);
				$writer->writeAttribute('id', $grammem['id']);
				$writer->writeAttribute('shift', $shift);
				
				$shift++;
			}
			$writer->endElement();
		}
		$writer->endElement();
	}
	$writer->endElement();
	
	$writer->endDocument();
}

dump_xml_file('Rus', dirname(__FILE__), 'PMY_RG_', 'PMY_RP_');
dump_xml_file('Eng', dirname(__FILE__), 'PMY_EG_', 'PMY_EP_');
dump_xml_file('Ger', dirname(__FILE__), 'PMY_GG_', 'PMY_GP_');
dump_xml_file('Unk', dirname(__FILE__), 'PMY_UG_', 'PMY_UP_');
