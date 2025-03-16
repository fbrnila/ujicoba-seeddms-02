<?php
/**
 * Implementation of ReviewDocument controller
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
 * Class which does the busines logic for reviewing a document
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2023 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_Controller_ReviewDocument extends SeedDMS_Controller_Common {

	public function run() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$settings = $this->params['settings'];
		$content = $this->params['content'];
		$reviewtype = $this->params['type'];
		$reviewstatus = $this->params['status'];
		$reviewcomment = $this->params['comment'];
		$reviewfile = $this->params['file'];
		$reviewgroup = $this->params['group'];
		$overallStatus = $content->getStatus();
		$this->oldstatus = $overallStatus['status'];
		$this->newstatus = $this->oldstatus;

		if(!$this->callHook('preReviewDocument', $content)) {
		}

		$result = $this->callHook('reviewDocument', $content);
		if($result === null) {
			if ($reviewtype == "ind") {
				$reviewLogID = $content->setReviewByInd($user, $user, $reviewstatus, $reviewcomment, $reviewfile);
			} elseif($reviewtype == "grp") {
				$reviewLogID = $content->setReviewByGrp($reviewgroup, $user, $reviewstatus, $reviewcomment, $reviewfile);
			} else {
				$this->errormsg = "review_wrong_type";
				return false;
			}
			if($reviewLogID === false || 0 > $reviewLogID) {
				$this->errormsg = "review_update_failed";
				return false;
			}
		}

		$result = $this->callHook('reviewUpdateDocumentStatus', $content);
		if($result === null) {
			if($reviewstatus == -1) {
				$this->newstatus = S_REJECTED;
				if($content->setStatus(S_REJECTED, $reviewcomment, $user)) {
					if(isset($GLOBALS['SEEDDMS_HOOKS']['reviewDocument'])) {
						foreach($GLOBALS['SEEDDMS_HOOKS']['reviewDocument'] as $hookObj) {
							if (method_exists($hookObj, 'postReviewDocument')) {
								$hookObj->postReviewDocument(null, $content, S_REJECTED);
							}
						}
					}
				}
			} else {
				$docReviewStatus = $content->getReviewStatus();
				if (is_bool($docReviewStatus) && !$docReviewStatus) {
					$this->errormsg = "cannot_retrieve_review_snapshot";
					return false;
				}
				$reviewCT = 0;
				$reviewTotal = 0;
				foreach ($docReviewStatus as $drstat) {
					if ($drstat["status"] == 1) {
						$reviewCT++;
					}
					if ($drstat["status"] != -2) {
						$reviewTotal++;
					}
				}
				// If all reviews have been received and there are no rejections, retrieve a
				// count of the approvals required for this document.
				if ($reviewCT == $reviewTotal) {
					$docApprovalStatus = $content->getApprovalStatus();
					if (is_bool($docApprovalStatus) && !$docApprovalStatus) {
						$this->errormsg = "cannot_retrieve_approval_snapshot";
						return false;
					}
					$approvalCT = 0;
					$approvalTotal = 0;
					foreach($docApprovalStatus as $dastat) {
						if($dastat["status"] == 1) {
							$approvalCT++;
						}
						if($dastat["status"] != -2) {
							$approvalTotal++;
						}
					}
					// If the approvals received is less than the approvals total, then
					// change status to pending approval.
					if($approvalCT < $approvalTotal) {
						$this->newstatus = S_DRAFT_APP;
					} else {
						// Otherwise, change the status to released.
						$this->newstatus = S_RELEASED;
					}
					if($content->setStatus($this->newstatus, getMLText("automatic_status_update"), $user)) {
						if(isset($GLOBALS['SEEDDMS_HOOKS']['reviewDocument'])) {
							foreach($GLOBALS['SEEDDMS_HOOKS']['reviewDocument'] as $hookObj) {
								if (method_exists($hookObj, 'postReviewDocument')) {
									$hookObj->postReviewDocument(null, $content, $this->newstatus);
								}
							}
						}
					}
				}
			}
		}

		if(!$this->callHook('postReviewDocument', $content)) {
		}

		return true;
	} /* }}} */
}
