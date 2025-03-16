<?php
/**
 * Implementation of ClearCache view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Include parent class
 */
//require_once("class.Bootstrap.php");

/**
 * Class which outputs the html page for ClearCache view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_ClearCache extends SeedDMS_Theme_Style {

	protected function output($name, $title, $space, $c) {
		echo '<p><input type="checkbox" name="'.$name.'" value="1" checked> '.$title.($space !== NULL || $c != NULL ? '<br />' : '').($space !== NULL ? SeedDMS_Core_File::format_filesize($space) : '').($c !== NULL ? ' in '.$c.' Files' : '').'</p>';
	}

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$cachedir = $this->params['cachedir'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("clear_cache"));
		$this->warningMsg(getMLText("confirm_clear_cache", array('cache_dir'=>$cachedir)));
?>
<form action="../op/op.ClearCache.php" name="form1" method="post">
<?php echo createHiddenFieldWithKey('clearcache'); ?>
<?php
		$this->contentContainerStart('warning');
?>
<?php
		$totalc = 0;
		$totalspace = 0;
		// Preview for png, pdf, and txt */
		foreach(['png', 'pdf', 'txt'] as $t) {
			$path = addDirSep($cachedir).$t;
			if(file_exists($path)) {
				$space = dskspace($path);
				$fi = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
				$c = iterator_count($fi);
			} else {                                                           
				$space = $c = 0;                                                 
			} 
			$totalc += $c;
			$totalspace += $space;
			$this->output('preview'.$t, getMLText('preview_'.$t), $space, $c);
		}

		/* Javascript */
		$path = addDirSep($cachedir).'js';
		if(file_exists($path)) {
			$space = dskspace($path);
			$fi = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
			$c = iterator_count($fi);
		} else {                                                           
			$space = $c = 0;                                                 
		} 
		$totalc += $c;
		$totalspace += $space;
		$this->output('js', getMLText('temp_jscode'), $space, $c);

		/* Cache dirѕ added by extensions */
		$addcache = array();
		if($addcache = $this->callHook('additionalCache')) {
			foreach($addcache as $c) {
				$this->output($c[0], $c[1], isset($c[2]) ? $c[2] : NULL, isset($c[3]) ? $c[3] : NULL);
				$totalc += $c[3];
				$totalspace += $c[2];
			}
		}
		$this->contentContainerEnd();
		$this->infoMsg(SeedDMS_Core_File::format_filesize($totalspace).' in '.$totalc.' Files');
		$this->formSubmit("<i class=\"fa fa-remove\"></i> ".getMLText('clear_cache'), '', '', 'danger');
?>
</form>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
