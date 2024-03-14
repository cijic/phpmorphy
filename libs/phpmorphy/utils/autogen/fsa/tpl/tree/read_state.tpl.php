		$offset = <?php echo $helper->getOffsetInFsa($helper->idx2offset('$index')) ?>;
		
		// read first trans
		<?php $helper->out($helper->storage->seek('$offset'), ';'); ?> 
		list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read('$offset', $helper->getTransSize())) ?>;
		
		// check if first trans is pointer to annot, and not single in state
		if(<?php echo $helper->checkTerm('$trans') ?> && !(<?php echo $helper->checkLLast('$trans') ?> || <?php echo $helper->checkRLast('$trans') ?>)) {
			$result[] = $trans;
			
			list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read('$offset', $helper->getTransSize())) ?>;
			$offset += <?php echo $helper->getTransSize(); ?>;
		}
		
		// read rest
		for($expect = 1; $expect; $expect--) {
			if(!<?php echo $helper->checkLLast('$trans') ?>) $expect++;
			if(!<?php echo $helper->checkRLast('$trans') ?>) $expect++;
			
			$result[] = $trans;
			
			if($expect > 1) {
				list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read('$offset', $helper->getTransSize())) ?>;
				$offset += <?php echo $helper->getTransSize(); ?>;
			}
		}
