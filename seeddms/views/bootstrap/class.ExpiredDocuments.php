<?php
/**
 * Implementation of ExpiredDocuments view
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
 * Class which outputs the html page for ExpiredDocuments view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_ExpiredDocuments extends SeedDMS_Theme_Style {

	function js() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];

		header('Content-Type: application/javascript; charset=UTF-8');
		parent::jsTranslations(array('cancel', 'splash_move_document', 'confirm_move_document', 'move_document', 'confirm_transfer_link_document', 'transfer_content', 'link_document', 'splash_move_folder', 'confirm_move_folder', 'move_folder'));
		$this->printDeleteDocumentButtonJs();
		/* Add js for catching click on document in one page mode */
		$this->printClickDocumentJs();
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$orderby = $this->params['orderby'];
		$orderdir = $this->params['orderdir'];
		$conversionmgr = $this->params['conversionmgr'];
		$cachedir = $this->params['cachedir'];
		$previewwidth = $this->params['previewWidthList'];
		$timeout = $this->params['timeout'];
		$xsendfile = $this->params['xsendfile'];
		$order = $orderby.$orderdir;
		$days = $this->params['days'];
		$startts = $this->params['startts'];
		$endts = $this->params['endts'];

		$db = $dms->getDB();
		$previewer = new SeedDMS_Preview_Previewer($cachedir, $previewwidth, $timeout, $xsendfile);
		if($conversionmgr)
			$previewer->setConversionMgr($conversionmgr);

		$this->htmlStartPage(getMLText("expired_documents"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("expired_documents"), "admin_tools");

		$this->rowStart();
		$this->columnStart(4);
		$this->contentHeading(getMLText("expired_documents"));
?>
<form class="form-horizontal">
<?php
		$this->formField(
			getMLText("days"),
			array(
				'element'=>'input',
				'type'=>'number',
				'name'=>'days',
				'id'=>'days',
				'value'=>$days
			)
		);
		$this->formField(
			getMLText("startdate"),
			$this->getDateChooser(getReadableDate($startts), "startdate", $this->params['session']->getLanguage(), '', '')
		);
		$this->formField(
			getMLText("enddate"),
			$this->getDateChooser(getReadableDate($endts), "enddate", $this->params['session']->getLanguage(), '', '')
		);
		$this->formSubmit("<i class=\"fa fa-refresh\"></i> ".getMLText('update'));
?>
</form>
<?php
		$this->columnEnd();
		$this->columnStart(8);

		if(is_numeric($days)) {
			$docs = $dms->getDocumentsExpired($days, null, $orderby, $orderdir, true);
			$this->contentHeading(''.$days);
		} else {
			$d = [];
			if($startts)
				$d['start'] = $startts;
			if($endts)
				$d['end'] = $endts+86400;
			$docs = $dms->getDocumentsExpired($d, null, $orderby, $orderdir, true);
			$this->contentHeading(getReadableDate($startts)." - ".getReadableDate($endts));
		}
		if($docs) {

			print "<table class=\"table table-condensed table-sm\">";
			print "<thead>\n<tr>\n";
			print "<th></th>";
			print "<th>".getMLText("name");
			print " <a class=\"order-btn\" href=\"../out/out.ExpiredDocuments.php?".($order=="na"?"&orderby=n&orderdir=d":"&orderby=n&orderdir=a")."&days=".$days."&startdate=".getReadableDate($startts)."&enddate=".getReadableDate($endts)."\" \"title=\"".getMLText("sort_by_name")."\">".($order=="na"?' <i class="fa fa-sort-alpha-asc selected"></i>':($order=="nd"?' <i class="fa fa-sort-alpha-desc selected"></i>':' <i class="fa fa-sort-alpha-asc"></i>'))."</a>";
			print " <a class=\"order-btn\" href=\"../out/out.ExpiredDocuments.php?".($order=="ea"?"&orderby=e&orderdir=d":"&orderby=e&orderdir=a")."&days=".$days."&startdate=".getReadableDate($startts)."&enddate=".getReadableDate($endts)."\" \"title=\"".getMLText("sort_by_expiration_date")."\">".($order=="ea"?' <i class="fa fa-sort-numeric-asc selected"></i>':($order=="ed"?' <i class="fa fa-sort-numeric-desc selected"></i>':' <i class="fa fa-sort-numeric-asc"></i>'))."</a>";
			print "</th>\n";
			print "<th>".getMLText("status")."</th>\n";
			print "<th>".getMLText("action")."</th>\n";
			print "</tr>\n</thead>\n<tbody>\n";

			foreach ($docs as $document) {
				echo $this->documentListRow($document, $previewer);
			}
			print "</tbody></table>";
		}
		else $this->infoMsg(getMLText("no_docs_expired"));
		
		$this->columnEnd();
		$this->rowEnd();

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
