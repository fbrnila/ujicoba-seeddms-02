<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
//    Copyright (C) 2010-2016 Uwe Steinmann
//
//    This program is free software; you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation; either version 2 of the License, or
//    (at your option) any later version.
//
//    This program is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with this program; if not, write to the Free Software
//    Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

include("../inc/inc.Settings.php");
include("../inc/inc.Utils.php");
include("../inc/inc.LogInit.php");
include("../inc/inc.Language.php");
include("../inc/inc.Init.php");
include("../inc/inc.Extension.php");
include("../inc/inc.DBInit.php");
include("../inc/inc.Authentication.php");
include("../inc/inc.ClassUI.php");
include("../inc/inc.ClassController.php");

$tmp = explode('.', basename($_SERVER['SCRIPT_FILENAME']));
$controller = Controller::factory($tmp[1], array('dms'=>$dms, 'user'=>$user));
$accessop = new SeedDMS_AccessOperation($dms, $user, $settings);

/* Check if the form data comes from a trusted request */
if(!checkFormKey('reviewdocument')) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_request_token"))),getMLText("invalid_request_token"));
}

if (!isset($_POST["documentid"]) || !is_numeric($_POST["documentid"]) || intval($_POST["documentid"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

$documentid = $_POST["documentid"];
$document = $dms->getDocument($documentid);

if (!is_object($document)) {
	UI::exitError(getMLText("document_title", array("documentname" => getMLText("invalid_doc_id"))),getMLText("invalid_doc_id"));
}

if ($document->getAccessMode($user) < M_READ) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

$folder = $document->getFolder();

if (!isset($_POST["version"]) || !is_numeric($_POST["version"]) || intval($_POST["version"])<1) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

$version = $_POST["version"];
$content = $document->getContentByVersion($version);

if (!is_object($content)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

// operation is only allowed for the last document version
$latestContent = $document->getLatestContent();
if ($latestContent->getVersion()!=$version) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_version"));
}

// verify if document may be reviewed
if (!$accessop->mayReview($document)){
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("access_denied"));
}

if (!isset($_POST["reviewStatus"]) || !is_numeric($_POST["reviewStatus"]) ||
		(intval($_POST["reviewStatus"])!=1 && intval($_POST["reviewStatus"])!=-1)) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_review_status"));
}

if($_FILES["reviewfile"]["tmp_name"]) {
	if (is_uploaded_file($_FILES["reviewfile"]["tmp_name"]) && $_FILES['reviewfile']['error']!=0){
		UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("uploading_failed"));
	}
}

$controller->setParam('comment', $_POST['comment']);
$controller->setParam('type', $_POST['reviewType']);
$controller->setParam('status', $_POST['reviewStatus']);
$controller->setParam('content', $latestContent);
$controller->setParam('file', !empty($_FILES["reviewfile"]["tmp_name"]) ? $_FILES["reviewfile"]["tmp_name"] : '');
$controller->setParam('group', !empty($_POST['reviewGroup']) ?	$dms->getGroup($_POST['reviewGroup']) : null);
if(!$controller()) {
	$err = $controller->getErrorMsg();
	if(is_string($err))
		$errmsg = getMLText($err);
	elseif(is_array($err)) {
		$errmsg = getMLText($err[0], $err[1]);
	} else {
		$errmsg = $err;
	}
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),$errmsg);
} else {
	if($notifier) {
		$reviewlog = $latestContent->getReviewLog();
		$notifier->sendSubmittedReviewMail($latestContent, $user, $reviewlog ? $reviewlog[0] : false);
		if($controller->oldstatus != $controller->newstatus)
			$notifier->sendChangedDocumentStatusMail($latestContent, $user, $controller->oldstatus);

		// Notify approvers, if necessary.
		if ($controller->newstatus == S_DRAFT_APP) {
			$notifier->sendApprovalRequestMail($latestContent, $user);
		}
	}
}

add_log_line("documentid=".$documentid."&version=".$version."&reviewType=".$_POST['reviewType']."&reviewStatus=".$_POST['reviewStatus']);

header("Location:../out/out.ViewDocument.php?documentid=".$documentid."&currenttab=revapp");
