	function getAlphabetNum() {
		if(!isset($this->alphabet_num)) {
			$this->alphabet_num = array_map('ord', $this->getAlphabet());
		}
		
		return $this->alphabet_num;
	}
