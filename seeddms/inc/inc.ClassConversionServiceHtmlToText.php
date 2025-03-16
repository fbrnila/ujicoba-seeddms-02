<?php
/**
 * Implementation of conversion service class
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2021 Uwe Steinmann
 * @version    Release: @package_version@
 */

require_once("inc/inc.ClassConversionServiceBase.php");

/**
 * Implementation of conversion service class for text to text
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2021 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_ConversionServiceHtmlToText extends SeedDMS_ConversionServiceBase {
	public function __construct($from, $to) {
		parent::__construct();
		$this->from = $from;
		$this->to = $to;
	}

	public function getInfo() {
		return "Strip tags from document contents";
	}

	public function convert($infile, $target = null, $params = array()) {
		$d = new DOMDocument;
		libxml_use_internal_errors(true);
		$d->loadHTMLFile($infile);
		libxml_clear_errors();
		$body = $d->getElementsByTagName('body')->item(0);
		$str = '';
		foreach($body->childNodes as $childNode) {
			$str .= $d->saveHTML($childNode);
		}
		if($target) {
			file_put_contents($target, strip_tags($str));
			return true;
		} else
			return strip_tags($str);
	}
}

