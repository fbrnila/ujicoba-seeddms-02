<?php
/**
 * Implementation of Send Notification view
 *
 * @category   DMS
 * @package    SeedDMS
 * @license    GPL 2
 * @version    @version@
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2024 Uwe Steinmann
 * @version    Release: @package_version@
 */

/**
 * Class which outputs the html page for sending a notification
 *
 * @category   DMS
 * @package    SeedDMS
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2016 Uwe Steinmann
 * @version    Release: @package_version@
 */
class SeedDMS_View_SendNotification extends SeedDMS_Theme_Style {

	var $subjects;

	var $recvtypes;

	public function __construct($params, $theme) { /* {{{ */
		parent::__construct($params, $theme);
		$this->subjects = array();
		$this->subjects[] = 'review_request';
		$this->subjects[] = 'approval_request';
		$this->subjects[] = 'new_document';
		$this->subjects[] = 'document_updated';
		$this->subjects[] = 'document_deleted';
		$this->subjects[] = 'version_deleted';
		$this->subjects[] = 'new_subfolder';
		$this->subjects[] = 'folder_deleted';
		$this->subjects[] = 'new_file';
		$this->subjects[] = 'replace_content';
		$this->subjects[] = 'remove_file';
		$this->subjects[] = 'document_attribute_changed';
		$this->subjects[] = 'document_attribute_added';
		$this->subjects[] = 'folder_attribute_changed';
		$this->subjects[] = 'folder_attribute_added';
		$this->subjects[] = 'document_comment_changed';
		$this->subjects[] = 'folder_comment_changed';
		$this->subjects[] = 'version_comment_changed';
		$this->subjects[] = 'document_renamed';
		$this->subjects[] = 'folder_renamed';
		$this->subjects[] = 'document_moved';
		$this->subjects[] = 'folder_moved';
		$this->subjects[] = 'document_transfered';
		$this->subjects[] = 'document_status_changed';
		$this->subjects[] = 'document_notify_added';
		$this->subjects[] = 'folder_notify_added';
		$this->subjects[] = 'document_notify_deleted';
		$this->subjects[] = 'folder_notify_deleted';
		$this->subjects[] = 'review_submit';
		$this->subjects[] = 'approval_submit';
		$this->subjects[] = 'review_deletion';
		$this->subjects[] = 'approval_deletion';
		$this->subjects[] = 'review_request';
		$this->subjects[] = 'approval_request';
		$this->subjects[] = 'document_ownership_changed';
		$this->subjects[] = 'folder_ownership_changed';
		$this->subjects[] = 'document_access_permission_changed';
		$this->subjects[] = 'folder_access_permission_changed';
		$this->subjects[] = 'transition_triggered';
		$this->subjects[] = 'request_workflow_action';
		$this->subjects[] = 'rewind_workflow';
		$this->subjects[] = 'rewind_workflow';

		$this->recvtypes = array();
		$this->recvtypes[] = array(SeedDMS_NotificationService::RECV_ANY, getMLText('notification_recv_any'));
		$this->recvtypes[] = array(SeedDMS_NotificationService::RECV_NOTIFICATION, getMLText('notification_recv_notification'));
		$this->recvtypes[] = array(SeedDMS_NotificationService::RECV_OWNER, getMLText('notification_recv_owner'));
		$this->recvtypes[] = array(SeedDMS_NotificationService::RECV_REVIEWER, getMLText('notification_recv_reviewer'));
		$this->recvtypes[] = array(SeedDMS_NotificationService::RECV_APPROVER, getMLText('notification_recv_approver'));
		$this->recvtypes[] = array(SeedDMS_NotificationService::RECV_WORKFLOW, getMLText('notification_recv_workflow'));
		$this->recvtypes[] = array(SeedDMS_NotificationService::RECV_UPLOADER, getMLText('notification_recv_uploader'));
	} /* }}} */

	public function js() { /* {{{ */
		header('Content-Type: application/javascript; charset=UTF-8');
?>
$(document).ready( function() {
    $('body').on('click', '#send_notification', function(ev){
      ev.preventDefault();
      var data = $('#form1').serializeArray().reduce(function(obj, item) {
          obj[item.name] = item.value;
          return obj;
      }, {});
      $.get("../op/op.SendNotification.php", $('#form1').serialize()+"&formtoken=<?= createFormKey('sendnotification'); ?>", function(response) {
        noty({
          text: response.message,
          type: response.success === true ? 'success' : 'error',
          dismissQueue: true,
          layout: 'topRight',
          theme: 'defaultTheme',
          timeout: 1500,
        });
      });
    });
});
<?php
	} /* }}} */

	public function checkfilter() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$notifier = $this->params['notifier'];
		$allusers = $this->params['allusers'];
		$seluser = $this->params['seluser'];

		$services = $notifier->getServices();
		foreach($services as $name => $service) {
			$this->contentHeading($name);
			if(is_callable([$service, 'filter'])) {
				$content = '';
				$content .= "<table class=\"table table-condensed table-sm\">";
				$content .= "<tr><th>".getMLText('notification_msg_tmpl')."/".getMLText('notification_recvtype')."</th>";
				array_shift($this->recvtypes);
				foreach($this->recvtypes as $recvtype) {
					$content .= "<th>".$recvtype[1]."</th>";
				}
				$content .= "</tr>";
				foreach($this->subjects as $subject) {
					$content .= "<tr><td>".$subject."</td>";
					foreach($this->recvtypes as $recvtype) {
						if($service->filter($user, $seluser, $subject.'_email_subject', $subject.'_email_body', [], $recvtype[0])) {
							$content .= "<td><i class=\"fa fa-check success\"></i></td>";
						} else {
							$content .= "<td><i class=\"fa fa-minus error\"></i></td>";
						}
					}
					$content .= "</tr>";
				}
				$content .= "</table>";
				$this->printAccordion(getMLText('click_to_expand_filter_results'), $content);
			} else {
				$this->infoMsg(getMLText('notification_service_no_filter'));
			}
		}

	} /* }}} */

	public function show() { /* {{{ */
		$dms = $this->params['dms'];
		$user = $this->params['user'];
		$notifier = $this->params['notifier'];
		$allusers = $this->params['allusers'];
		$seluser = $this->params['seluser'];

		$this->htmlStartPage(getMLText("admin_tools"));
		$this->globalNavigation();
		$this->contentStart();
		$this->pageNavigation(getMLText("admin_tools"), "admin_tools");
		$this->contentHeading(getMLText("send_notification"));

		$this->rowStart();
		$this->columnStart(4);
?>
<form class="form-horizontal" name="form1" id="form1" method="get">
<?php
		$this->contentContainerStart();
		$options = array();
		foreach ($allusers as $currUser) {
			if ($currUser->isGuest() )
				continue;

			$options[] = array($currUser->getID(), htmlspecialchars($currUser->getLogin()." - ".$currUser->getFullName()), $seluser && $seluser->getId() == $currUser->getId());
		}
		$this->formField(
			getMLText("user"),
			array(
				'element'=>'select',
				'name'=>'userid',
				'class'=>'chzn-select',
				'options'=>$options
			)
		);

		$options = array();
		$options[] = array(SeedDMS_NotificationService::RECV_ANY, getMLText('notification_recv_any'));
		$options[] = array(SeedDMS_NotificationService::RECV_NOTIFICATION, getMLText('notification_recv_notification'));
		$options[] = array(SeedDMS_NotificationService::RECV_OWNER, getMLText('notification_recv_owner'));
		$options[] = array(SeedDMS_NotificationService::RECV_REVIEWER, getMLText('notification_recv_reviewer'));
		$options[] = array(SeedDMS_NotificationService::RECV_APPROVER, getMLText('notification_recv_approver'));
		$options[] = array(SeedDMS_NotificationService::RECV_WORKFLOW, getMLText('notification_recv_workflow'));
		$options[] = array(SeedDMS_NotificationService::RECV_UPLOADER, getMLText('notification_recv_uploader'));
		$this->formField(
			getMLText("notification_recvtype"),
			array(
				'element'=>'select',
				'name'=>'recvtype',
				'class'=>'chzn-select',
				'options'=>$options
			)
		);
		$options = array();
		$options[] = array('review_request', 'review_request');
		$options[] = array('approval_request', 'approval_request');
		$options[] = array('new_document', 'new_document');
		$options[] = array('document_updated', 'document_updated');
		$options[] = array('document_deleted', 'document_deleted');
		$options[] = array('version_deleted', 'version_deleted');
		$options[] = array('new_subfolder', 'new_subfolder');
		$options[] = array('folder_deleted', 'folder_deleted');
		$options[] = array('new_file', 'new_file');
		$options[] = array('replace_content', 'replace_content');
		$options[] = array('remove_file', 'remove_file');
		$options[] = array('document_attribute_changed', 'document_attribute_changed');
		$options[] = array('document_attribute_added', 'document_attribute_added');
		$options[] = array('folder_attribute_changed', 'folder_attribute_changed');
		$options[] = array('folder_attribute_added', 'folder_attribute_added');
		$options[] = array('document_comment_changed', 'document_comment_changed');
		$options[] = array('folder_comment_changed', 'folder_comment_changed');
		$options[] = array('version_comment_changed', 'version_comment_changed');
		$options[] = array('document_renamed', 'document_renamed');
		$options[] = array('folder_renamed', 'folder_renamed');
		$options[] = array('document_moved', 'document_moved');
		$options[] = array('folder_moved', 'folder_moved');
		$options[] = array('document_transfered', 'document_transfered');
		$options[] = array('document_status_changed', 'document_status_changed');
		$options[] = array('document_notify_added', 'document_notify_added');
		$options[] = array('folder_notify_added', 'folder_notify_added');
		$options[] = array('document_notify_deleted', 'document_notify_deleted');
		$options[] = array('folder_notify_deleted', 'folder_notify_deleted');
		$options[] = array('review_submit', 'review_submit');
		$options[] = array('approval_submit', 'approval_submit');
		$options[] = array('review_deletion', 'review_deletion');
		$options[] = array('approval_deletion', 'approval_deletion');
		$options[] = array('review_request', 'review_request');
		$options[] = array('approval_request', 'approval_request');
		$options[] = array('document_ownership_changed', 'document_ownership_changed');
		$options[] = array('folder_ownership_changed', 'folder_ownership_changed');
		$options[] = array('document_access_permission_changed', 'document_access_permission_changed');
		$options[] = array('folder_access_permission_changed', 'folder_access_permission_changed');
		$options[] = array('transition_triggered', 'transition_triggered');
		$options[] = array('request_workflow_action', 'request_workflow_action');
		$options[] = array('rewind_workflow', 'rewind_workflow');
		$this->formField(
			getMLText("notification_msg_tmpl"),
			array(
				'element'=>'select',
				'name'=>'template',
				'class'=>'chzn-select',
				'options'=>$options
			)
		);
		$this->contentContainerEnd();
		$buttons = [];
		$names = [];
		$buttons[] = '<i class="fa fa-rotate-left"></i> '.getMLText('send_notification');
		$names[] = "send_notification";
		$buttons[] = '<i class="fa fa-rotate-left"></i> '.getMLText('check_notification_filter');
		$names[] = "check_filter";
		$this->formSubmit($buttons, $names);
?>
</form>
<?php
		$this->columnEnd();
		$this->columnStart(8);
?>
	<div class="ajax" style="margin-bottom: 15px;" data-view="SendNotification" data-action="checkfilter" data-query="userid=<?= $seluser ? $seluser->getId() : $user->getId() ?>"></div>
<?php
		$this->columnEnd();
		$this->rowEnd();
		$this->contentEnd();
		$this->htmlEndPage();
	} /* }}} */
}


