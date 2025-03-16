<?php
/**
 * Implementation of Conversion Services view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2023 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class which outputs the html page for Conversion Services view
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2016 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_ConversionServices extends SeedDMS_Theme_Style {

	/**
	 * List all registered conversion services
	 *
	 */
	function list_conversion_services($allservices) { /* {{{ */
		echo "<table class=\"table table-condensed table-sm\">\n";
		echo "<thead>";
		echo "<tr><th>".getMLText('service_list_from')."</th><th>".getMLText('service_list_to')."</th><th>".getMLText('class_name')."</th><th>".getMLText('service_list_info')."</th></tr>\n";
		echo "</thead>";
		echo "<tbody>";
		foreach($allservices as $from=>$tos) {
			foreach($tos as $to=>$services) {
				foreach($services as $service) {
					echo "<tr><td>".$from."</td><td>".$to."</td><td>".get_class($service)."</td><td>".$service->getInfo()."</td></tr>";
				}
			}
		}
		echo "</tbody>";
		echo "</table>\n";
	} /* }}} */

	function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$conversionmgr = $this->params['conversionmgr'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");

		if($conversionmgr) {
			$allservices = $conversionmgr->getServices();
			if($data = $dms->getStatisticalData('docspermimetype')) {
				$this->contentHeading(getMLText("list_conversion_overview"));
				echo "<table class=\"table table-condensed table-sm\">\n";
				echo "<thead>";
				echo "<tr><th>".getMLText('mimetype')."</th><th>".getMLText('preview')."</th><th>".getMLText('fullsearch')."</th><th>".getMLText('preview_pdf')."</th></tr>\n";
				echo "</thead>";
				echo "<tbody>";
				foreach($data as $d) {
					$key = $d['key'];
					$t = explode('/', $key);
					if(isset($allservices[$key]) || isset($allservices[$t[0].'/*'])) {
						echo "<tr><td>".$key." (".$d['total'].")</td>";
						echo "<td>";
						if(!empty($allservices[$key]['image/png'])) {
							foreach($allservices[$key]['image/png'] as $object)
								echo '<i class="fa fa-check" title="'.get_class($object).'"></i> ';
						}
						echo "</td>";
						echo "<td>";
						if(!empty($allservices[$key]['text/plain'])) {
							foreach($allservices[$key]['text/plain'] as $object)
								echo '<i class="fa fa-check" title="'.get_class($object).'"></i> ';
						}
						echo "</td>";
						echo "<td>";
						if(!empty($allservices[$key]['application/pdf'])) {
							foreach($allservices[$key]['application/pdf'] as $object)
								echo '<i class="fa fa-check" title="'.get_class($object).'"></i> ';
						}
						echo "</td>";
						echo "</tr>";
					}
				}
				echo "</tbody></table>";
			}

			$this->contentHeading(getMLText("list_conversion_services"));
			self::list_conversion_services($allservices);
		}

		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}

