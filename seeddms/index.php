<?php
/**
 * SeedDMS (Formerly MyDMS) Document Management System
 *
 * PHP version 8
 *
 * Copyright (C) 2002-2005  Markus Westphal
 * Copyright (C) 2006-2008 Malcolm Cowe
 * Copyright (C) 2010-2024 Uwe Steinmann
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 * @category SeedDMS
 * @package  SeedDMS
 * @author   Uwe Steinmann <info@seeddms.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://www.seeddms.org Main Site
 */

require "inc/inc.Settings.php";

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

if(true) {
	require_once("inc/inc.Utils.php");
	require_once("inc/inc.LogInit.php");
	require_once("inc/inc.Language.php");
	require_once("inc/inc.Init.php");
	require_once("inc/inc.Extension.php");
	require_once("inc/inc.DBInit.php");

	$containerBuilder = new \DI\ContainerBuilder();
	$c = $containerBuilder->build();
	/*
	$c['notFoundHandler'] = function ($c) use ($settings, $dms) {
		return function ($request, $response) use ($c, $settings, $dms) {
			$uri = $request->getUri();
			if($uri->getBasePath())
				$file = $uri->getPath();
			else
				$file = substr($uri->getPath(), 1);
			if(file_exists($file) && is_file($file)) {
				$_SERVER['SCRIPT_FILENAME'] = basename($file);
//				include($file);
				exit;
			}
			if($request->isXhr()) {
				exit;
			}
//			print_r($request->getUri());
//			exit;
			return $c['response']
				->withStatus(302)
				->withHeader('Location', isset($settings->_siteDefaultPage) && strlen($settings->_siteDefaultPage)>0 ? $settings->_httpRoot.$settings->_siteDefaultPage : $settings->_httpRoot."out/out.ViewFolder.php");
		};
	};
	 */
	AppFactory::setContainer($c);
	$app = AppFactory::create();
	/* put lots of data into the container, because if slim instanciates
	 * a class by itself (with the help from the DI container), it will
	 * pass the container to the constructor of the instanciated class.
	 */
	$container = $app->getContainer();
	$container->set('dms', $dms);
	$container->set('config', $settings);
	$container->set('conversionmgr', $conversionmgr);
	$container->set('logger', $logger);
	$container->set('fulltextservice', $fulltextservice);
	$container->set('notifier', $notifier);
	$container->set('authenticator', $authenticator);

	if(isset($GLOBALS['SEEDDMS_HOOKS']['initDMS'])) {
			foreach($GLOBALS['SEEDDMS_HOOKS']['initDMS'] as $hookObj) {
					if (method_exists($hookObj, 'addMiddleware')) {
							$hookObj->addMiddleware($app);
					}
			}
	}

	$app->get('/', function($request, $response) {
		return $response
			->withHeader('Location', '/out/out.ViewFolder.php')
			->withStatus(302);

	});
	if(isset($GLOBALS['SEEDDMS_HOOKS']['initDMS'])) {
		foreach($GLOBALS['SEEDDMS_HOOKS']['initDMS'] as $hookObj) {
			if (method_exists($hookObj, 'addRoute')) {
				// FIXME: pass $app only just like initRestAPI. $app has a container
				// which contains all other objects
				$hookObj->addRoute(array('dms'=>$dms, 'app'=>$app, 'settings'=>$settings, 'conversionmgr'=>$conversionmgr, 'authenticator'=>$authenticator, 'fulltextservice'=>$fulltextservice, 'logger'=>$logger));
			}
		}
	}

	/*
	$app->get('/out/[{path:.*}]', function($request, $response, $path = null) use ($app) {
		$uri = $request->getUri();
		if($uri->getBasePath())
			$file = $uri->getPath();
		else
			$file = substr($uri->getPath(), 1);
		if(file_exists($file) && is_file($file)) {
			$_SERVER['SCRIPT_FILENAME'] = basename($file);
			include($file);
			exit;
		}
	});
	 */

	$app->run();
} else {

	header("Location: ". (isset($settings->_siteDefaultPage) && strlen($settings->_siteDefaultPage)>0 ? $settings->_siteDefaultPage : "out/out.ViewFolder.php"));
?>
<html>
<head>
	<title>SeedDMS</title>
</head>

<body>


</body>
</html>
<?php } ?>
