<?php
/**
 * Implementation of ApproveDocument controller
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
 * Class which does the busines logic for approving a document
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2023 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Controller_ApproveDocument extends SeedDMS_Controller_Common {

	public $oldstatus;

	public $newstatus;

	public function run() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$content = $this->params['content'];
		$approvaltype = $this->params['type'];
		$approvalstatus = $this->params['status'];
		$approvalcomment = $this->params['comment'];
		$approvalfile = $this->params['file'];
		$approvalgroup = $this->params['group'];
		$overallStatus = $content->getStatus();
		$this->oldstatus = $overallStatus['status'];
		$this->newstatus = $this->oldstatus;

		if(!$this->callHook('preApproveDocument', $content)) {
		}

		$result = $this->callHook('approveDocument', $content);
		if($result === null) {
			if ($approvaltype == "ind") {
				$approvalLogID = $content->setApprovalByInd($user, $user, $approvalstatus, $approvalcomment, $approvalfile);
			} elseif ($approvaltype == "grp") {
				$approvalLogID = $content->setApprovalByGrp($approvalgroup, $user, $approvalstatus, $approvalcomment, $approvalfile);
			} else {
				$this->errormsg = "approval_wrong_type";
				return false;
			}
			if($approvalLogID === false || 0 > $approvalLogID) {
				$this->errormsg = "approval_update_failed";
				return false;
			}
		}

		$result = $this->callHook('approveUpdateDocumentStatus', $content);
		if($result === null) {
			if($approvalstatus == -1) {
				$this->newstatus = S_REJECTED;
				if($content->setStatus(S_REJECTED, $approvalcomment, $user)) {
					if(isset($GLOBALS['SEEDDMS_HOOKS']['approveDocument'])) {
						foreach($GLOBALS['SEEDDMS_HOOKS']['approveDocument'] as $hookObj) {
							if (method_exists($hookObj, 'postApproveDocument')) {
								$hookObj->postApproveDocument(null, $content, S_REJECTED);
							}
						}
					}
				}
			} else {
				$docApprovalStatus = $content->getApprovalStatus();
				if (is_bool($docApprovalStatus) && !$docApprovalStatus) {
					$this->errormsg = "cannot_retrieve_approval_snapshot";
					return false;
				}
				$approvalCT = 0;
				$approvalTotal = 0;
				foreach ($docApprovalStatus as $drstat) {
					if ($drstat["status"] == 1) {
						$approvalCT++;
					}
					if ($drstat["status"] != -2) {
						$approvalTotal++;
					}
				}
				// If all approvals have been received and there are no rejections, retrieve a
				// count of the approvals required for this document.
				if ($approvalCT == $approvalTotal) {
					// Change the status to released.
					$this->newstatus=S_RELEASED;
					if($content->setStatus($this->newstatus, getMLText("automatic_status_update"), $user)) {
						if(isset($GLOBALS['SEEDDMS_HOOKS']['approveDocument'])) {
							foreach($GLOBALS['SEEDDMS_HOOKS']['approveDocument'] as $hookObj) {
								if (method_exists($hookObj, 'postApproveDocument')) {
									$hookObj->postApproveDocument(null, $content, S_RELEASED);
								}
							}
						}
					}
				}
			}
		}

		if(!$this->callHook('postApproveDocument', $content)) {
		}

		return true;
	} /* }}} */
}

