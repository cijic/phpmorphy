<?php
 /**
 * This file is part of phpMorphy library
 *
 * Copyright c 2007-2008 Kamaev Vladimir <heromantor@users.sourceforge.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the
 * Free Software Foundation, Inc., 59 Temple Place - Suite 330,
 * Boston, MA 02111-1307, USA.
 */

class phpMorphy_Link_Base {
	protected
		$fsa,
		$trans,
		$raw_trans;

	function __construct(phpMorphy_Fsa_Interface $fsa, $trans, $rawTrans) {
		$this->fsa = $fsa;
		$this->trans = $trans;
		$this->raw_trans = $rawTrans;
	}

	function isAnnotation() { }
	function getTrans() { return $this->trans; }
	function getFsa() { return $this->fsa; }
	function getRawTrans() { return $this->raw_trans; }
};

/**
 * This class represent "normal" link i.e. link that points to automat state
 */
class phpMorphy_Link extends phpMorphy_Link_Base {
	function isAnnotation() { return false; }

	function getDest() { return $this->trans['dest']; }
	function getAttr() { return $this->trans['attr']; }

	function getTargetState() {
		return $this->createState($this->trans['dest']);
	}

	protected function createState($index) {
		return new phpMorphy_State($this->fsa, $index);
	}
}

class phpMorphy_Link_Annot extends phpMorphy_Link_Base {
	function isAnnotation() { return true; }

	function getAnnotation() {
		return $this->fsa->getAnnot($this->raw_trans);
	}
};

class phpMorphy_State {
	protected
		$fsa,
		$transes,
		$raw_transes;

	function __construct(phpMorphy_Fsa_Interface $fsa, $index) {
		$this->fsa = $fsa;

		$this->raw_transes = $fsa->readState($index);
		$this->transes = $fsa->unpackTranses($this->raw_transes);
	}

	function getLinks() {
		$result = array();

		for($i = 0, $c = count($this->transes); $i < $c; $i++) {
			$trans = $this->transes[$i];

			if(!$trans['term']) {
				$result[] = $this->createNormalLink($trans, $this->raw_transes[$i]);
			} else {
				$result[] = $this->createAnnotLink($trans, $this->raw_transes[$i]);
			}
		}

		return $result;
	}

	function getSize() { return count($this->transes); }

	protected function createNormalLink($trans, $raw) {
		return new phpMorphy_Link($this->fsa, $trans, $raw);
	}

	protected function createAnnotLink($trans, $raw) {
		return new phpMorphy_Link_Annot($this->fsa, $trans, $raw);
	}
};
