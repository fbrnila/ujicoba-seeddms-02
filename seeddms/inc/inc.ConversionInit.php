<?php

/**
 * MyDMS. Document Management System
 * Copyright (C) 2002-2005 Markus Westphal
 * Copyright (C) 2006-2008 Malcolm Cowe
 * Copyright (C) 2010 Matteo Lucarelli
 * Copyright (C) 2010-2024 Uwe Steinmann
 *
 * PHP version 8
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

$conversionmgr = null;
require_once "inc.ClassConversionMgr.php";
$conversionmgr = new SeedDMS_ConversionMgr();

if (!empty($settings->_converters['preview'])) {
	foreach ($settings->_converters['preview'] as $mimetype => $cmd) {
		$conversionmgr->addService(new SeedDMS_ConversionServiceExec($mimetype, 'image/png', $cmd), $settings->_cmdTimeout)->setLogger($logger);
	}
}

if (!empty($settings->_converters['pdf'])) {
	foreach ($settings->_converters['pdf'] as $mimetype => $cmd) {
		$conversionmgr->addService(new SeedDMS_ConversionServiceExec($mimetype, 'application/pdf', $cmd, $settings->_cmdTimeout))->setLogger($logger);
	}
}

if (!empty($settings->_converters['fulltext'])) {
	foreach ($settings->_converters['fulltext'] as $mimetype => $cmd) {
		$conversionmgr->addService(new SeedDMS_ConversionServiceExec($mimetype, 'text/plain', $cmd, $settings->_cmdTimeout))->setLogger($logger);
	}
}

if (extension_loaded('imagick')) {
	$conversionmgr->addService(new SeedDMS_ConversionServicePdfToImage('application/pdf', 'image/png'))->setLogger($logger);
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/tiff', 'image/png'))->setLogger($logger);
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/svg+xml', 'image/png'))->setLogger($logger);
}

if (extension_loaded('gd') || extension_loaded('imagick')) {
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/jpeg', 'image/png'))->setLogger($logger);
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/png', 'image/png'))->setLogger($logger);
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/jpg', 'image/png'))->setLogger($logger);
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/gif', 'image/png'))->setLogger($logger);
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/webp', 'image/png'))->setLogger($logger);
	$conversionmgr->addService(new SeedDMS_ConversionServiceImageToImage('image/avif', 'image/png'))->setLogger($logger);
}

if (extension_loaded('imagick')) {
	$conversionmgr->addService(new SeedDMS_ConversionServiceTextToImage('text/plain', 'image/png'))->setLogger($logger);
}

$conversionmgr->addService(new SeedDMS_ConversionServiceImageToText('image/jpeg', 'text/plain'))->setLogger($logger);
$conversionmgr->addService(new SeedDMS_ConversionServiceImageToText('image/jpg', 'text/plain'))->setLogger($logger);

$conversionmgr->addService(new SeedDMS_ConversionServiceTextToText('text/plain', 'text/plain'))->setLogger($logger);
$conversionmgr->addService(new SeedDMS_ConversionServiceTextToText('text/markdown', 'text/plain'))->setLogger($logger);
$conversionmgr->addService(new SeedDMS_ConversionServiceTextToText('text/x-rst', 'text/plain'))->setLogger($logger);

$conversionmgr->addService(new SeedDMS_ConversionServiceHtmlToText('text/html', 'text/plain'))->setLogger($logger);

if (isset($GLOBALS['SEEDDMS_HOOKS']['initConversion'])) {
	foreach ($GLOBALS['SEEDDMS_HOOKS']['initConversion'] as $hookObj) {
		if (method_exists($hookObj, 'getConversionServices')) {
			if ($services = $hookObj->getConversionServices(array('dms' => $dms, 'settings' => $settings, 'logger' => $logger))) {
				foreach ($services as $service) {
					$conversionmgr->addService($service)->setLogger($logger);
				}
			}
		}
	}
}
