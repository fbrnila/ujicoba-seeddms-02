<?php
declare(strict_types=1);

/**
 * Implementation of user and group access object
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
 * Class to represent a user access right.
 * This class cannot be used to modify access rights.
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010-2024 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Core_UserAccess { /* {{{ */

	/**
	 * @var SeedDMS_Core_User
	 */
	protected $_user;

	/**
	 * @var
	 */
	protected $_mode;

	/**
	 * SeedDMS_Core_UserAccess constructor.
	 * @param $user
	 * @param $mode
	 */
	public function __construct($user, $mode) {
		$this->_user = $user;
		$this->_mode = $mode;
	}

	/**
	 * @return int
	 */
	public function getUserID() { return $this->_user->getID(); }

	/**
	 * @return mixed
	 */
	public function getMode() { return $this->_mode; }

	/**
	 * @return bool
	 */
	public function isAdmin() {
		return ($this->_user->isAdmin());
	}

	/**
	 * @return SeedDMS_Core_User
	 */
	public function getUser() {
		return $this->_user;
	}
} /* }}} */


/**
 * Class to represent a group access right.
 * This class cannot be used to modify access rights.
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal, 2006-2008 Malcolm Cowe,
 *             2010-2024 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Core_GroupAccess { /* {{{ */

	/**
	 * @var SeedDMS_Core_Group
	 */
	protected $_group;

	/**
	 * @var
	 */
	protected $_mode;

	/**
	 * SeedDMS_Core_GroupAccess constructor.
	 * @param $group
	 * @param $mode
	 */
	public function __construct($group, $mode) {
		$this->_group = $group;
		$this->_mode = $mode;
	}

	/**
	 * @return int
	 */
	public function getGroupID() { return $this->_group->getID(); }

	/**
	 * @return mixed
	 */
	public function getMode() { return $this->_mode; }

	/**
	 * @return SeedDMS_Core_Group
	 */
	public function getGroup() {
		return $this->_group;
	}
} /* }}} */
