			// tree version
			$result = true;
			$start_offset = <?php echo $helper->getOffsetByTrans('$trans', '$char') ?>;
			
			// read first trans in state
			<?php $helper->out($helper->storage->seek('$start_offset'), ';'); ?> 
			list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read('$start_offset', $helper->getTransSize())) ?>;
			
			// If first trans is term(i.e. pointing to annot) then skip it
			if(<?php echo $helper->checkTerm('$trans'); ?>) {
				// When this is single transition in state then break
				if(<?php echo $helper->checkLLast('$trans'); ?> && <?php echo $helper->checkRLast('$trans'); ?>) {
					$result = false;
				} else {
					$start_offset += <?php echo $helper->getTransSize() ?>;
					<?php $helper->out($helper->storage->seek('$start_offset'), ';'); ?> 
					list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read('$start_offset', $helper->getTransSize())) ?>;
				}
			}
			
			// if all ok process rest transitions in state
			if($result) {
				// walk through state
				for($idx = 1, $j = 0; ; $j++) {
					$attr = <?php echo $helper->getChar('$trans') ?>;
					
					if($attr == $char) {
						$result = true;
						break;
					} else if($attr > $char) {
						if(<?php echo $helper->checkLLast('$trans') ?>) {
							$result = false;
							break;
						}
						
						$idx = $idx << 1;
					} else {
						if(<?php echo $helper->checkRLast('$trans') ?>) {
							$result = false;
							break;
						}
						
						$idx = ($idx << 1) + 1;
					}
					
					if($j > 255) {
						throw new phpMorphy_Exception('Infinite recursion possible');
					}
			
					<?php $offsetExp = '$start_offset + ' . $helper->idx2offset('$idx - 1') ?> 
					// read next trans
					<?php $helper->out($helper->storage->seek($offsetExp), ';'); ?> 
					list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read($offsetExp, $helper->getTransSize())) ?>;
				}
			}
			
