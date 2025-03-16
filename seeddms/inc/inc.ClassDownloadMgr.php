<?php
/**
 * Implementation of a download management.
 *
 * This class handles downloading of document lists.
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2015 Uwe Steinmann
 * @version    Release: @package_version@
 */

require_once("vendor/autoload.php");

/**
 * Class to represent an download manager
 *
 * This class provides some very basic methods to download document lists.
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2015 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Download_Mgr {
	/**
	 * @var string $tmpdir directory where download archive is temp. created
	 * @access protected
	 */
	protected $tmpdir;

	/**
	 * @var array $items list of document content items
	 * @access protected
	 */
	protected $items;

	/**
	 * @var array $folder_items list of folder content items
	 * @access protected
	 */
	protected $folder_items;

	/**
	 * @var array $extracols list of arrays with extra columns per item
	 * @access protected
	 */
	protected $extracols;

	/**
	 * @var array $folder_extracols list of arrays with extra columns per folder item
	 * @access protected
	 */
	protected $folder_extracols;

	/**
	 * @var array $header list of entries in header (first line)
	 * @access protected
	 */
	protected $header;

	/**
	 * @var array $folder_header list of entries in header (first line)
	 * @access protected
	 */
	protected $folder_header;

	/**
	 * @var array $extraheader list of extra entries in header
	 * @access protected
	 */
	protected $extraheader;

	/**
	 * @var array $folder_extraheader list of extra entries in header
	 * @access protected
	 */
	protected $folder_extraheader;

	/**
	 * @var array $rawcontents list of content used instead of document content
	 * @access protected
	 */
	protected $rawcontents;

	/**
	 * @var array $filenames filename used in archive
	 * @access protected
	 */
	protected $filenames;

	function __construct($tmpdir = '') {
		$this->tmpdir = $tmpdir;
		$this->items = array();
		$this->folder_items = array();
		$this->header = array(getMLText('download_header_document_no'), getMLText('download_header_document_name'), getMLText('download_header_filename'), getMLText('download_header_state'), getMLText('download_header_internal_version'), getMLText('download_header_reviewer'), getMLText('download_header_review_date'), getMLText('download_header_review_comment'), getMLText('download_header_review_state'), getMLText('download_header_approver'), getMLText('download_header_approval_date'), getMLText('download_header_approval_comment'), getMLText('download_header_approval_state'));
		$this->folder_header = array(getMLText('download_header_folder_no'), getMLText('download_header_folder_name'));
		$this->extracols = array();
		$this->folder_extracols = array();
		$this->rawcontents = array();
		$this->extraheader = array();
		$this->folder_extraheader = array();
		$this->filenames = array();
	}

	public function addHeader($extraheader) { /* {{{ */
		$this->extraheader = $extraheader;
	} /* }}} */

	public function addFolderHeader($extraheader) { /* {{{ */
		$this->folder_extraheader = $extraheader;
	} /* }}} */

	public function addItem($item, $extracols=array(), $rawcontent='', $filename='') { /* {{{ */
		$this->items[$item->getID()] = $item;
		$this->extracols[$item->getID()] = $extracols;
		$this->rawcontents[$item->getID()] = $rawcontent;
		$this->filenames[$item->getID()] = $filename;
	} /* }}} */

	public function addFolderItem($item, $extracols=array()) { /* {{{ */
		$this->folder_items[$item->getID()] = $item;
		$this->folder_extracols[$item->getID()] = $extracols;
	} /* }}} */

	public function createToc($file) { /* {{{ */
		$objPHPExcel = new PhpOffice\PhpSpreadsheet\Spreadsheet();
		$objPHPExcel->setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());
		$objPHPExcel->getProperties()->setCreator("SeedDMS")->setTitle("Metadata");
		if($items = $this->items) {
		$sheet = $objPHPExcel->setActiveSheetIndex(0);
		$sheet->setTitle(getMLText('documents'));

		$i = 1;
		$col = 1;
		foreach($this->header as $h)
			$sheet->setCellValue([$col++, $i], $h);
		foreach($this->extraheader as $h)
			$sheet->setCellValue([$col++, $i], $h);
		$i++;
		foreach($items as $item) {
			if($item->isType('documentcontent')) {
			$document = $item->getDocument();
			$dms = $document->_dms;
			$status = $item->getStatus();
			$reviewStatus = $item->getReviewStatus();
			$approvalStatus = $item->getApprovalStatus();

			$col = 1;
			$sheet->setCellValue([$col++, $i], $document->getID());
			$sheet->setCellValue([$col++, $i], $document->getName());
			$sheet->setCellValue([$col++, $i], $document->getID()."-".$item->getOriginalFileName());
			$sheet->setCellValue([$col++, $i], getOverallStatusText($status['status']));
			$sheet->setCellValue([$col++, $i], $item->getVersion());
			$l = $i;
			$k = $i;
			if($reviewStatus) {
				foreach ($reviewStatus as $r) {
					switch ($r["type"]) {
						case 0: // Reviewer is an individual.
							$required = $dms->getUser($r["required"]);
							if (!is_object($required)) {
								$reqName = getMLText("unknown_user")." '".$r["required"]."'";
							} else {
								$reqName = htmlspecialchars($required->getFullName()." (".$required->getLogin().")");
							}
							break;
						case 1: // Reviewer is a group.
							$required = $dms->getGroup($r["required"]);
							if (!is_object($required)) {
								$reqName = getMLText("unknown_group")." '".$r["required"]."'";
							} else {
								$reqName = htmlspecialchars($required->getName());
							}
							break;
					}
					$tcol = $col;
					$sheet->setCellValue([$tcol++, $l], $reqName);
					$sheet->setCellValue([$tcol, $l], ($r['status']==1 || $r['status']==-1) ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($r['date'])) : null);
					$sheet->getStyleByColumnAndRow($tcol++, $l)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DATETIME);
					$sheet->setCellValue([$tcol++, $l], $r['comment']);
					$sheet->setCellValue([$tcol++, $l], getReviewStatusText($r["status"]));
					$l++;
				}
				$l--;
			}
			$col += 4;
			if($approvalStatus) {
				foreach ($approvalStatus as $r) {
					switch ($r["type"]) {
						case 0: // Reviewer is an individual.
							$required = $dms->getUser($r["required"]);
							if (!is_object($required)) {
								$reqName = getMLText("unknown_user")." '".$r["required"]."'";
							} else {
								$reqName = htmlspecialchars($required->getFullName()." (".$required->getLogin().")");
							}
							break;
						case 1: // Reviewer is a group.
							$required = $dms->getGroup($r["required"]);
							if (!is_object($required)) {
								$reqName = getMLText("unknown_group")." '".$r["required"]."'";
							} else {
								$reqName = htmlspecialchars($required->getName());
							}
							break;
					}
					$tcol = $col;
					$sheet->setCellValue([$tcol++, $k], $reqName);
					$sheet->setCellValue([$tcol, $k], ($r['status']==1 || $r['status']==-1) ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($r['date'])) : null);
					$sheet->getStyleByColumnAndRow($tcol++, $k)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_DATE_DATETIME);
					$sheet->setCellValue([$tcol++, $k], $r['comment']);
					$sheet->setCellValue([$tcol++, $k], getApprovalStatusText($r["status"]));
					$k++;
				}
				$k--;
			}
			$col += 4;
			if(isset($this->extracols[$item->getID()]) && $this->extracols[$item->getID()]) {
				foreach($this->extracols[$item->getID()] as $column)
					$sheet->setCellValue([$col++, $i], is_array($column) ? implode("\n", $column) : $column );
			}
			$i = max($l, $k);
			$i++;
		}
		}
		}

		if($items = $this->folder_items) {
			if($this->items)
				$sheet = $objPHPExcel->createSheet($i);
			else
				$sheet = $objPHPExcel->setActiveSheetIndex(0);
			$sheet->setTitle(getMLText('folders'));

			$i = 1;
			$col = 1;
			foreach($this->folder_header as $h)
				$sheet->setCellValue([$col++, $i], $h);
			foreach($this->folder_extraheader as $h)
				$sheet->setCellValue([$col++, $i], $h);
			$i++;
			$items = $this->folder_items;
			foreach($items as $item) {
				if($item->isType('folder')) {
					$folder = $item;
					$dms = $folder->_dms;

					$col = 1;
					$sheet->setCellValue([$col++, $i], $folder->getID());
					$sheet->setCellValue([$col++, $i], $folder->getName());
					if(isset($this->folder_extracols[$item->getID()]) && $this->folder_extracols[$item->getID()]) {
						foreach($this->folder_extracols[$item->getID()] as $column)
							$sheet->setCellValue([$col++, $i], is_array($column) ? implode("\n", $column) : $column );
					}
					$i++;
				}
			}
		}

		$objWriter = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($objPHPExcel);
		$objWriter->save($file);

		return true;
	} /* }}} */

	public function createArchive($filename) { /* {{{ */
		if(!$this->items) {
			return false;
		}

		$file = tempnam(sys_get_temp_dir(), "export-list-");
		if(!$file)
			return false;
		$this->createToc($file);

		$zip = new ZipArchive();
		$prefixdir = date('Y-m-d', time());

		if(($errcode = $zip->open($filename, ZipArchive::OVERWRITE)) !== TRUE) {
			echo $errcode;
			return false;
		}

		foreach($this->items as $item) {
			$document = $item->getDocument();
			$dms = $document->_dms;
			if($this->filenames[$item->getID()]) {
				$filename = $this->filenames[$item->getID()];
			} else {
				$ext = pathinfo($document->getName(), PATHINFO_EXTENSION);
				$oext = pathinfo($item->getOriginalFileName(), PATHINFO_EXTENSION);
				if($ext == $oext)
					$filename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $document->getName());
				else {
					$filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $document->getName()).'.'.$oext;
				}
				$filename = $document->getID().'-'.$item->getVersion().'-'.$filename; //$lc->getOriginalFileName();
			}
			$filename = $prefixdir."/".$filename;
			if($this->rawcontents[$item->getID()]) {
				$zip->addFromString(utf8_decode($filename), $this->rawcontents[$item->getID()]);
			} else
				$zip->addFile($dms->contentDir.$item->getPath(), utf8_decode($filename));
		}

		$zip->addFile($file, $prefixdir."/metadata.xlsx");
		$zip->close();
		unlink($file);
		return true;
	} /* }}} */
}
