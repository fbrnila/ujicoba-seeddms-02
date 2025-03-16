<?php
/**
 * Implementation of ClearCache controller
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class which does the busines logic for clearing the cache
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2013 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Controller_ClearCache extends SeedDMS_Controller_Common {

	public function run() {
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$post = $this->params['post'];

		$ret = '';
		if(!empty($post['previewpng'])) {
			$cmd = 'rm -rf '.addDirSep($settings->_cacheDir).'png'.DIRECTORY_SEPARATOR.'[1-9]*';
			system($cmd, $ret);
		}

		if(!empty($post['previewpdf'])) {
			$cmd = 'rm -rf '.addDirSep($settings->_cacheDir).'pdf'.DIRECTORY_SEPARATOR.'[1-9]*';
			system($cmd, $ret);
		}

		if(!empty($post['previewtxt'])) {
			$cmd = 'rm -rf '.addDirSep($settings->_cacheDir).'txt'.DIRECTORY_SEPARATOR.'[1-9]*';
			system($cmd, $ret);
		}

		if(!empty($post['js'])) {
			/* system('rm ...') does not work anymore if the number of files is too large */
			array_map('unlink', array_filter((array) glob(addDirSep($settings->_cacheDir).'js'.DIRECTORY_SEPARATOR.'*')));
		}

		if(false === $this->callHook('clear', $post)) {
			if(empty($this->errormsg))
				$this->errormsg = 'hook_clear_failed';
			return false;
		}

		return true;
	}
}

