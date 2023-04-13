			// sparse version
			$result = true;
			<?php $helper->out($helper->seekTrans('$trans', '$char'), ';'); ?> 
			list(, $trans) = <?php echo $helper->readTrans('$trans', '$char') ?>;
			
			if(<?php echo $helper->checkEmpty('$trans') ?> || <?php echo $helper->getChar('$trans') ?> != $char) {
				$result = false;
			}
