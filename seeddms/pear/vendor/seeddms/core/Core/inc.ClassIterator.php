<?php
declare(strict_types=1);

namespace SeedDMS\Core;

/**
 * Implementation of the document iterartor
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2024 Uwe Steinmann
 * @version    Release: @package_version@
 */

class DocumentIterator implements \Iterator {
	/**
	 * @var object folder
	 */
	protected $_folder;

	/**
	 * @var object dms
	 */
	protected $_dms;

	/**
	 * @var array documents
	 */
	protected $_documents;

	/**
	 * @var int $_pointer
	 */
	protected $_pointer;

	/**
	 * @var array $_cache
	 */
	protected $_cache;

	public function __construct($folder) {
		$this->_folder = $folder;
		$this->_dms = $folder->getDMS();
		$this->_documents = array();
		$this->_pointer = 0;
		$this->_cache = array();
		$this->populate();
	}

	public function rewind(): void {
		$this->_pointer = 0;
	}

	public function valid(): bool {
		return isset($this->_documents[$this->_pointer]);
	}

	public function next(): void {
		$this->_pointer++;
	}

	public function key(): mixed {
		return $this->_documents[$this->_pointer];
	}

	public function current(): mixed {
		if ($this->_documents[$this->_pointer]) {
			$documentid = $this->_documents[$this->_pointer]['id'];
			if (!isset($this->_cache[$documentid])) {
//				echo $documentid." not cached<br />";
				$this->_cache[$documentid] = $this->_dms->getdocument($documentid);
			}
			return $this->_cache[$documentid];
		}
		return null;
	}

	private function populate($orderby = "", $dir = "asc", $limit = 0, $offset = 0) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT `id` FROM `tblDocuments` WHERE `folder` = " . $this->_folder->getID();

		if ($orderby && $orderby[0]=="n") $queryStr .= " ORDER BY `name`";
		elseif ($orderby && $orderby[0]=="s") $queryStr .= " ORDER BY `sequence`";
		elseif ($orderby && $orderby[0]=="d") $queryStr .= " ORDER BY `date`";
		if ($dir == 'desc')
			$queryStr .= " DESC";
		if (is_int($limit) && $limit > 0) {
			$queryStr .= " LIMIT ".$limit;
			if (is_int($offset) && $offset > 0)
				$queryStr .= " OFFSET ".$offset;
		}

		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$this->_documents = $resArr;
	} /* }}} */
}

class FolderIterator implements \Iterator { /* {{{ */
	/**
	 * @var object folder
	 */
	protected $_folder;

	/**
	 * @var object dms
	 */
	protected $_dms;

	/**
	 * @var array documents
	 */
	protected $_folders;

	/**
	 * @var int $_pointer
	 */
	protected $_pointer;

	/**
	 * @var array $_cache
	 */
	protected $_cache;

	public function __construct($folder) { /* {{{ */
		$this->_folder = $folder;
		$this->_dms = $folder->getDMS();
		$this->_folders = array();
		$this->_pointer = 0;
		$this->_cache = array();
		$this->populate();
	} /* }}} */

	#[\ReturnTypeWillChange]
	public function rewind() { /* {{{ */
		$this->_pointer = 0;
	} /* }}} */

	#[\ReturnTypeWillChange]
	public function valid() { /* {{{ */
		return isset($this->_folders[$this->_pointer]);
	} /* }}} */

	#[\ReturnTypeWillChange]
	public function next() { /* {{{ */
		$this->_pointer++;
	} /* }}} */

	#[\ReturnTypeWillChange]
	public function key() { /* {{{ */
		return $this->_folders[$this->_pointer];
	} /* }}} */

	#[\ReturnTypeWillChange]
	public function current() { /* {{{ */
		if ($this->_folders[$this->_pointer]) {
			$folderid = $this->_folders[$this->_pointer]['id'];
			if (!isset($this->_cache[$folderid])) {
//				echo $folderid." not cached<br />";
				$this->_cache[$folderid] = $this->_dms->getFolder($folderid);
			}
			return $this->_cache[$folderid];
		}
		return null;
	} /* }}} */

	private function populate($orderby = "", $dir = "asc", $limit = 0, $offset = 0) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT `id` FROM `tblFolders` WHERE `parent` = " . $this->_folder->getID();

		if ($orderby && $orderby[0]=="n") $queryStr .= " ORDER BY `name`";
		elseif ($orderby && $orderby[0]=="s") $queryStr .= " ORDER BY `sequence`";
		elseif ($orderby && $orderby[0]=="d") $queryStr .= " ORDER BY `date`";
		if ($dir == 'desc')
			$queryStr .= " DESC";
		if (is_int($limit) && $limit > 0) {
			$queryStr .= " LIMIT ".$limit;
			if (is_int($offset) && $offset > 0)
				$queryStr .= " OFFSET ".$offset;
		}

		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;

		$this->_folders = $resArr;
	} /* }}} */
} /* }}} */

/**
 * The FolderFilterIterator checks if the given user has access on
 * the current folder.
 * FilterIterator uses an inner iterator passed to the constructor
 * to iterate over the sub folders of a folder.
 *
		$iter = new FolderIterator($folder);
		$iter2 = new FolderFilterIterator($iter, $user);
		foreach($iter2 as $ff) {
			echo $ff->getName()."<br />";
		}
 */
class FolderFilterIterator extends \FilterIterator { /* {{{ */
	/**
	 * @var object filter
	 */
	protected $folderFilter;

	public function __construct(\Iterator $iterator, $filter) {
		parent::__construct($iterator);
		$this->folderFilter = $filter;
	}

	public function accept(): bool { /* {{{ */
		$folder = $this->getInnerIterator()->current();
		if (strcasecmp($folder->getName(), $this->folderFilter) == 0) {
				return false;
		}
		return true;
	} /* }}} */
} /* }}} */

/**
		$iter = new RecursiveFolderIterator($folder);
		$iter2 = new RecursiveIteratorIterator($iter, RecursiveIteratorIterator::SELF_FIRST);
		foreach($iter2 as $ff) {
			echo $ff->getID().': '.$ff->getName()."<br />";
		}
 */
class RecursiveFolderIterator extends FolderIterator implements \RecursiveIterator { /* {{{ */

	#[\ReturnTypeWillChange]
	public function hasChildren() { /* {{{ */
		$db = $this->_dms->getDB();
		$queryStr = "SELECT id FROM `tblFolders` WHERE `parent` = ".(int) $this->current()->getID();
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;
		return true;
	} /* }}} */

	#[\ReturnTypeWillChange]
	public function getChildren() { /* {{{ */
		return new RecursiveFolderIterator($this->current());
	} /* }}} */
} /* }}} */

