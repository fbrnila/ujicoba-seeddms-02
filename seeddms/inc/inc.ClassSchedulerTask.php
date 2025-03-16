<?php
/**
 * Implementation of an SchedulerTask.
 *
 * SeedDMS can be extended by extensions. Extension usually implement
 * hook.
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2018 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class to represent a SchedulerTask
 *
 * This class provides some very basic methods to manage extensions.
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  2011 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_SchedulerTask {
	/**
	 * Instanz of database
	 */
	protected $db;

	/**
	 * @var integer unique id of task
	 */
	protected $_id;

	/**
	 * @var string name of task
	 */
	protected $_name;

	/**
	 * @var string description of task
	 */
	protected $_description;

	/**
	 * @var string extension of task
	 */
	protected $_extension;

	/**
	 * @var string task of task
	 */
	protected $_task;

	/**
	 * @var string frequency of task
	 */
	protected $_frequency;

	/**
	 * @var integer set if disabled
	 */
	protected $_disabled;

	/**
	 * @var array list of parameters
	 */
	protected $_params;

	/**
	 * @var integer last run
	 */
	protected $_lastrun;

	/**
	 * @var integer next run
	 */
	protected $_nextrun;

	public static function getInstance($id, $db) { /* {{{ */
		$queryStr = "SELECT * FROM `tblSchedulerTask` WHERE `id` = " . (int) $id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) != 1)
			return null;
		$row = $resArr[0];

		$task = new self($row["id"], $row['name'], $row["description"], $row["extension"], $row["task"], $row["frequency"], $row['disabled'], json_decode($row['params'], true), $row["nextrun"], $row["lastrun"]);
		$task->setDB($db);

		return $task;
	} /* }}} */

	public static function getInstances($db) { /* {{{ */
		$queryStr = "SELECT * FROM `tblSchedulerTask`";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) == 0)
			return array();

		$tasks = array();
		foreach($resArr as $row) {
			$task = new self($row["id"], $row['name'], $row["description"], $row["extension"], $row["task"], $row["frequency"], $row['disabled'], json_decode($row['params'], true), $row["nextrun"], $row["lastrun"]);
			$task->setDB($db);
			$tasks[] = $task;
		}

		return $tasks;
	} /* }}} */

	public static function getInstancesByExtension($extname, $taskname, $db) { /* {{{ */
		$queryStr = "SELECT * FROM `tblSchedulerTask` WHERE `extension` = '".$extname."' AND `task` = '".$taskname."'";
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) == 0)
			return array();

		$tasks = array();
		foreach($resArr as $row) {
			$task = new self($row["id"], $row['name'], $row["description"], $row["extension"], $row["task"], $row["frequency"], $row['disabled'], json_decode($row['params'], true), $row["nextrun"], $row["lastrun"]);
			$task->setDB($db);
			$tasks[] = $task;
		}

		return $tasks;
	} /* }}} */

	function __construct($id, $name, $description, $extension, $task, $frequency, $disabled, $params, $nextrun, $lastrun) {
		$this->_id = $id;
		$this->_name = $name;
		$this->_description = $description;
		$this->_extension = $extension;
		$this->_task = $task;
		$this->_frequency = $frequency;
		$this->_disabled = $disabled;
		$this->_params = $params;
		$this->_nextrun = $nextrun;
		$this->_lastrun = $lastrun;
	}

	public function setDB($db) {
		$this->db = $db;
	}

	public function getID() {
		return $this->_id;
	}

	public function getName() {
		return $this->_name;
	}

	public function setName($newName) { /* {{{ */
		$db = $this->db;

		$queryStr = "UPDATE `tblSchedulerTask` SET `name` =".$db->qstr($newName)." WHERE `id` = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_name = $newName;
		return true;
	} /* }}} */

	public function getDescription() {
		return $this->_description;
	}

	public function setDescription($newDescripion) { /* {{{ */
		$db = $this->db;

		$queryStr = "UPDATE `tblSchedulerTask` SET `description` =".$db->qstr($newDescripion)." WHERE `id` = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_description = $newDescripion;
		return true;
	} /* }}} */

	public function getExtension() {
		return $this->_extension;
	}

	public function getTask() {
		return $this->_task;
	}

	public function getFrequency() {
		return $this->_frequency;
	}

	public function setFrequency($newFrequency) { /* {{{ */
		$db = $this->db;

		try {
			$cron = Cron\CronExpression::factory($newFrequency);
		} catch (Exception $e) {
			return false;
		}
		$nextrun = $cron->getNextRunDate()->format('Y-m-d H:i:s');

		$queryStr = "UPDATE `tblSchedulerTask` SET `frequency` =".$db->qstr($newFrequency).", `nextrun` = '".$nextrun."' WHERE `id` = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_frequency = $newFrequency;
		$this->_nextrun = $nextrun;
		return true;
	} /* }}} */

	public function getNextRun() {
		return $this->_nextrun;
	}

	public function getLastRun() {
		return $this->_lastrun;
	}

	public function getDisabled() {
		return $this->_disabled;
	}

	public function setDisabled($newDisabled) { /* {{{ */
		$db = $this->db;

		$queryStr = "UPDATE `tblSchedulerTask` SET `disabled` =".intval($newDisabled)." WHERE `id` = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_disabled = $newDisabled;
		return true;
	} /* }}} */

	public function setParameter($newParams) { /* {{{ */
		$db = $this->db;

		$queryStr = "UPDATE `tblSchedulerTask` SET `params` =".$db->qstr(json_encode($newParams))." WHERE `id` = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_params = $newParams;
		return true;
	} /* }}} */

	public function getParameter($name = '') {
		if($name)
			return isset($this->_params[$name]) ? $this->_params[$name] : null;
		return $this->_params;
	}

	/**
	 * Check if task is due
	 *
	 * This methods compares the current time with the time in the database
	 * field `nextrun`.
	 * If nextrun is smaller than the current time, the the task is due.
	 * The methode does not rely on the value in the class variable `_nextrun`,
	 * because that value could be 'very old', retrieved at a time
	 * when the task list was fetched for checking due tasks e.g. by the
	 * scheduler client. There is good reason to always take the current
	 * value of nextrun from the database.
	 *
	 * Assuming there are two tasks. Task 1 takes 13 mins and task 2 takes only
	 * 30 sec. Task 1 is run every hour and task 2 starts at 8:06. The cronjob
	 * runs every 5 min. At e.g. 8:00 the list of tasks is read from the database
	 * task 1 is due and starts running and before it runs it sets the database
	 * field nextrun to 9:00. Task 2 isn't due at that time.
	 * At 8:05 the cron job runs again, task 1 has already a new nextrun value
	 * and will not run again. Task 2 isn't due yet and task 1 started at 8:00 is
	 * still running.
	 * At 8:10 task 1 is still running an not due again, but task 2 is due and
	 * will be run. The database field `nextrun` of task 2 will be set to 8:06
	 * on the next day.
	 * At 8:13 task 1 which started at 8:00 is finished and the list of tasks
	 * from that time will be processed further. Task 2 still has the old value
	 * in the class variable `_nextrun` (8:06 the current day),
	 * though the database field `nextrun` has been updated in
	 * between. Taking the value of the class variable would rerun task 2 again,
	 * though it ran at 8:10 already.
	 * That's why this method always takes the current value of nextrun
	 * from the database.
	 *
	 * @return boolean true if task is due, otherwise false
	 */
	public function isDue() {
		$db = $this->db;

		$queryStr = "SELECT * FROM `tblSchedulerTask` WHERE `id` = " . $this->_id;
		$resArr = $db->getResultArray($queryStr);
		if (is_bool($resArr) && $resArr == false)
			return false;
		if (count($resArr) != 1)
			return false;
		$row = $resArr[0];
		$this->_nextrun = $row['nextrun'];

		return $this->_nextrun < date('Y-m-d H:i:s');
	}

	public function updateLastNextRun() {
		$db = $this->db;

		$lastrun = date('Y-m-d H:i:s');
		try {
			$cron = Cron\CronExpression::factory($this->_frequency);
			$nextrun = $cron->getNextRunDate()->format('Y-m-d H:i:s');
		} catch (Exception $e) {
			$nextrun = null;
		}

		$queryStr = "UPDATE `tblSchedulerTask` SET `lastrun`=".$db->qstr($lastrun).", `nextrun`=".($nextrun ? $db->qstr($nextrun) : "NULL")." WHERE `id` = " . $this->_id;
		$res = $db->getResult($queryStr);
		if (!$res)
			return false;

		$this->_lastrun = $lastrun;
		$this->_nextrun = $nextrun;
	}

	/**
	 * Delete task
	 *
	 * @return boolean true on success or false in case of an error
	 */
	function remove() { /* {{{ */
		$db = $this->db;

		$queryStr = "DELETE FROM `tblSchedulerTask` WHERE `id` = " . $this->_id;
		if (!$db->getResult($queryStr)) {
			return false;
		}

		return true;
	} /* }}} */

}
