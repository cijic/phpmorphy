array(
				'term'  => <?php echo $helper->checkTerm('$rawTrans') ?> ? true : false,
				'llast' => <?php echo $helper->checkLLast('$rawTrans') ?> ? true : false,
				'rlast' => <?php echo $helper->checkRLast('$rawTrans') ?> ? true : false,
				'attr'  => <?php echo $helper->getChar('$rawTrans') ?>,
				'dest'  => <?php echo $helper->getDest('$rawTrans') ?>,
			)