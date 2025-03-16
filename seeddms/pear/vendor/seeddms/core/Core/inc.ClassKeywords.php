<?php
declare(strict_types=1);

/**
 * Implementation of keyword categories in the document management system
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010-2024 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to represent a keyword category in the document management system
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010-2024 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Core_KeywordCategory { /* {{{ */
	/**
	 * @var integer $_id id of keyword category
	 * @access protected
	 */
	protected $_id;

	/**
	 * @var integer $_ownerID id of user who is the owner
	 * @access protected
	 */
	protected $_ownerID;

	/**
	 * @var object $_owner user who is the owner
	 * @access protected
	 */
	protected $_owner;

	/**
	 * @var string $_name name of category
	 * @access protected
	 */
	protected $_name;

	/**
	 * @var SeedDMS_Core_DMS $_dms reference to dms this category belongs to
	 * @access protected
	 */
	protected $_dms;

	/**
	 * SeedDMS_Core_KeywordCategory constructor.
	 *
	 * @param $id
	 * @param $ownerID
	 * @param $name
	 */
	public function __construct($id, $ownerID, $name) { /* {{{ */
		$this->_id = $id;
		$this->_name = $name;
		$this->_ownerID = $ownerID;
		$this->_owner = null;
		$this->_dms = null;
	} /* }}} */

	/**
	 * @param SeedDMS_Core_DMS $dms
	 */
	public function setDMS($dms) { /* {{{ */
		$this->_dms = $dms;
	} /* }}} */

	/**
	 * Return internal id of keyword category
	 *
	 * @return int
	 */
	public function getID() { return $this->_id; }

	/**
	 * Return name of keyword category
	 *
	 * @return string
	 */
	public function getName() { return $this->_name; }

	/**
	 * Return owner of keyword category
	 *
	 * @return bool|SeedDMS_Core_User
	 */
	public function getOwner() { /* {{{ */
		if (!isset($this->_owner))
			$this->_owner = $this->_dms->getUser($this->_ownerID);
		return $this->_owner;
	} /* }}} */

	/**
	 * Set name of keyword category
	 *
	 * @param $newName
	 * @return bool
	 */
	public function setName($newName) { /* {{{ */
		$newName = trim($newName);
		if (!$newName)
			return false;

		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblKeywordCategories` SET `name` = ".$db->qstr($newName)." WHERE `id` = ". $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	/**
	 * Set owner of keyword category
	 *
	 * @param SeedDMS_Core_User $user
	 * @return bool
	 */
	public function setOwner($user) { /* {{{ */
		if (!$user || !$user->isType('user'))
			return false;

		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblKeywordCategories` SET `owner` = " . $user->getID() . " WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr))
			return false;

		$this->_ownerID = $user->getID();
		$this->_owner = $user;
		return true;
	} /* }}} */

	/**
	 * Get list of keywords in category
	 *
	 * @return array keywords of category
	 */
	public function getKeywordLists() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT * FROM `tblKeywords` WHERE `category` = " . $this->_id . " order by `keywords`";
		return $db->getResultArray($queryStr);
	}

	/**
	 * Return number of keywords in category
	 *
	 * @return integer number of keywords in this list
	 */
	public function countKeywordLists() { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "SELECT COUNT(*) as `c` FROM `tblKeywords` where `category`=".$this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && !$resArr)
			return false;

		return $resArr[0]['c'];
	} /* }}} */

	/**
	 * Change a keyword
	 *
	 * This method identifies the keyword by its id and also ensures that
	 * the keyword belongs to the category, though the keyword id would be
	 * sufficient to uniquely identify the keyword.
	 *
	 * @param $kid
	 * @param $keywords
	 * @return bool
	 */
	public function editKeywordList($kid, $keywords) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "UPDATE `tblKeywords` SET `keywords` = ".$db->qstr($keywords)." WHERE `id` = ".(int) $kid." AND `category`=".$this->_id;
		return $db->getResult($queryStr);
	} /* }}} */

	/**
	 * Add a new keyword to category
	 *
	 * @param $keywords new keyword
	 * @return bool
	 */
	public function addKeywordList($keywords) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "INSERT INTO `tblKeywords` (`category`, `keywords`) VALUES (" . $this->_id . ", ".$db->qstr($keywords).")";
		return $db->getResult($queryStr);
	} /* }}} */

	/**
	 * Remove keyword
	 *
	 * This method identifies the keyword by its id and also ensures that
	 * the keyword belongs to the category, though the keyword id would be
	 * sufficient to uniquely identify the keyword.
	 *
	 * @param $kid
	 * @return bool
	 */
	public function removeKeywordList($kid) { /* {{{ */
		$db = $this->_dms->getDB();

		$queryStr = "DELETE FROM `tblKeywords` WHERE `id` = ".(int) $kid." AND `category`=".$this->_id;
		return $db->getResult($queryStr);
	} /* }}} */

	/**
	 * Delete all keywords of category and category itself
	 *
	 * @return bool
	 */
	public function remove() { /* {{{ */
		$db = $this->_dms->getDB();

		$db->startTransaction();
		$queryStr = "DELETE FROM `tblKeywords` WHERE `category` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$queryStr = "DELETE FROM `tblKeywordCategories` WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			$db->rollbackTransaction();
			return false;
		}

		$db->commitTransaction();
		return true;
	} /* }}} */
} /* }}} */
