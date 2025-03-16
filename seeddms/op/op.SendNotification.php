<?php
//    MyDMS. Document Management System
//    Copyright (C) 2002-2005  Markus Westphal
//    Copyright (C) 2006-2008 Malcolm Cowe
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
if (!$accessop->check_controller_access($controller, $_GET)) {
	header('Content-Type: application/json');
	echo json_encode(array('success'=>false, 'message'=>getMLText('access_denied')));
	exit;
}

/* Check if the form data comes from a trusted request */
if(!checkFormKey('sendnotification', 'GET')) {
	header('Content-Type: application/json');
	echo json_encode(array('success'=>false, 'message'=>getMLText('invalid_request_token')));
	exit;
}

if (!isset($_GET["userid"]) || !is_numeric($_GET["userid"]) || intval($_GET["userid"])<1) {
	header('Content-Type: application/json');
	echo json_encode(array('success'=>false, 'message'=>getMLText('invalid_user_id')));
}
$userid = $_GET["userid"];
$newuser = $dms->getUser($userid);

if (!is_object($newuser)) {
	header('Content-Type: application/json');
	echo json_encode(array('success'=>false, 'message'=>getMLText('invalid_user_id')));
	exit;
}

$recvtype = 1;
if (isset($_GET["recvtype"])) {
	$recvtype = (int) $_GET["recvtype"];
}
$template = 'send_notification';
if (isset($_GET["template"])) {
	$template = $_GET["template"];
}

if($notifier) {
	header('Content-Type: application/json');
	if($notifier->toIndividual($user, $newuser, $template.'_email_subject', $template.'_email_body', [], $recvtype)) {
		echo json_encode(array('success'=>true, 'message'=>getMLText('splash_send_notification')));
	} else {
		echo json_encode(array('success'=>false, 'message'=>getMLText('error_send_notification')));
	}
}

