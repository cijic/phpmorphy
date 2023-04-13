        $start_offset = <?php echo $helper->getOffsetInFsa($helper->idx2offset('$index')) ?>;
        
        // first try read annot transition
        <?php $helper->out($helper->storage->seek('$start_offset'), ';'); ?> 
        list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read('$start_offset', $helper->getTransSize())) ?>;
        
        if(<?php echo $helper->checkTerm('$trans') ?>) {
            $result[] = $trans;
        }
        
        // read rest
        $start_offset += <?php echo $helper->getTransSize() ?>;
        foreach($this->getAlphabetNum() as $char) {
<?php $offset = '$start_offset + ' . $helper->idx2offset('$char') ?>
            <?php $helper->out($helper->storage->seek($offset), ';'); ?> 
            list(, $trans) = <?php echo $helper->unpackTrans($helper->storage->read($offset, $helper->getTransSize())) ?>;
            
//            if(!<?php echo $helper->checkEmpty('$trans') ?> && <?php echo $helper->getChar('$trans') ?> == $char) {
// TODO: check term and empty flags at once i.e. $trans & 0x0300
            if(!(<?php echo $helper->checkEmpty('$trans') ?> || <?php echo $helper->checkTerm('$trans') ?>) && <?php echo $helper->getChar('$trans') ?> == $char) {

                $result[] = $trans;
            }
        }
