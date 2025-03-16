<?php
/**
 * Implementation of SetExpires view
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
 * Class which outputs the html page for SetExpires view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2012 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_SetExpires extends SeedDMS_Theme_Style {

	function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
$(document).ready( function() {
	$('#presetexpdate').on('change', function(ev){
		if($(this).val() == 'date')
			$('#control_expdate').show();
		else
			$('#control_expdate').hide();
	});
});
<?php
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$folder = $this->params['folder'];
		$document = $this->params['document'];

		$this->htmlStartPage(getMLText("document_title", array("documentname" => htmlspecialchars($document->getName()))));
		$this->globalNavigation($folder);
		$this->contentStart();
		$this->pageNavigation($this->getFolderPathHTML($folder, true, $document), "view_document", $document);
		$this->contentHeading(getMLText("set_expiry"));

		if($document->expires())
			$expdate = getReadableDate($document->getExpires());
		else
			$expdate = '';
?>

<form class="form-horizontal" action="../op/op.SetExpires.php" method="post">
<input type="hidden" name="documentid" value="<?php print $document->getID();?>">
	<?php echo createHiddenFieldWithKey('setexpires'); ?>
<?php
		$this->contentContainerStart();
		$df = !empty($settings->_datetimeformat) ? $settings->_datetimeformat : 'Y-m-d H:i:s';
		$options = array();
		$options[] = array('never', getMLText('does_not_expire'));
		$options[] = array('date', getMLText('expire_by_date'), $expdate != '');
		$options[] = array('today', getMLText('expire_today').' ('.date($df, getTsByPeriod('today', 's')).')');
		$options[] = array('tomorrow', getMLText('expire_tomorrow').' ('.date($df, getTsByPeriod('tomorrow', 's')).')');
		$options[] = array('1w', getMLText('expire_in_1w').' ('.date($df, getTsByPeriod('1w', 's')).')');
		$options[] = array('1m', getMLText('expire_in_1m').' ('.date($df, getTsByPeriod('1m', 's')).')');
		$options[] = array('1y', getMLText('expire_in_1y').' ('.date($df, getTsByPeriod('1y', 's')).')');
		$options[] = array('2y', getMLText('expire_in_2y').' ('.date($df, getTsByPeriod('2y', 's')).')');
		$options[] = array('3y', getMLText('expire_in_3y').' ('.date($df, getTsByPeriod('3y', 's')).')');
		$this->formField(
			getMLText("preset_expires"),
			array(
				'element'=>'select',
				'id'=>'presetexpdate',
				'name'=>'presetexpdate',
				'options'=>$options
			),
			array(
				'help'=>getMLText('set_expiration_date_help')
			)
		);
		$this->formField(
			getMLText("expires"),
			$this->getDateChooser($expdate, "expdate", $this->params['session']->getLanguage(), '', '') // set last parameter to '+1d' if dates in the past are not allowed
		);
		$this->contentContainerEnd();
		$this->formSubmit("<i class=\"fa fa-save\"></i> ".getMLText('save'));
?>
</form>
<?php
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}
?>
