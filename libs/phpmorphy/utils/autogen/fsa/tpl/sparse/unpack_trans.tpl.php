array(
				'term'  => <?php echo $helper->checkTerm('$rawTrans') ?> ? true : false,
				'empty' => <?php echo $helper->checkEmpty('$rawTrans') ?> ? true : false,
				'attr'  => <?php echo $helper->getChar('$rawTrans') ?>,
				'dest'  => <?php echo $helper->getDest('$rawTrans') ?>,
			)