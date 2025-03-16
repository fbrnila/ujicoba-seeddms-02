<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
//    Copyright (C) 2010 Matteo Lucarelli
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

/* Check if the form data comes for a trusted request */
if(!checkFormKey('revisedocument')) {
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

// verify if document maybe revised
if (!$accessop->mayRevise($document)){
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

$olddocstatus = $content->getStatus();

if (!isset($_POST["revisionStatus"]) || !is_numeric($_POST["revisionStatus"]) ||
		(!in_array(intval($_POST["revisionStatus"]), array(1, -1, 6)))) {
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText("invalid_revision_status"));
}

$controller->setParam('document', $document);
$controller->setParam('content', $content);
$controller->setParam('revisionstatus', $_POST["revisionStatus"]);
$controller->setParam('revisiontype', $_POST["revisionType"]);
if ($_POST["revisionType"] == "grp") {
	$group = $dms->getGroup($_POST['revisionGroup']);
} else {
	$group = null;
}
$controller->setParam('group', $group);
$controller->setParam('comment', $_POST["comment"]);
$controller->setParam('onevotereject', $settings->_enableRevisionOneVoteReject);
if(!$controller->run()) {
	$err = $controller->getErrorMsg();
	if(is_string($err))
		$errmsg = getMLText($err);
	elseif(is_array($err)) {
		$errmsg = getMLText($err[0], $err[1]);
	} else {
		$errmsg = $err;
	}
	UI::exitError(getMLText("document_title", array("documentname" => $document->getName())),getMLText($errmsg));
}

if($notifier) {
	if ($_POST["revisionType"] == "ind" || $_POST["revisionType"] == "grp") {
		$revisionlog = $latestContent->getRevisionLog();
		$notifier->sendSubmittedRevisionMail($latestContent, $user, $revisionlog ? $revisionlog[0] : false);
	}

	/* Send notification about status change only if status has actually changed */
	if($controller->oldstatus != $controller->newstatus)
		$notifier->sendChangedDocumentStatusMail($latestContent, $user, $controller->oldstatus);
}

add_log_line("documentid=".$documentid."&version=".$version);

header("Location:../out/out.ViewDocument.php?documentid=".$documentid."&currenttab=revision");
