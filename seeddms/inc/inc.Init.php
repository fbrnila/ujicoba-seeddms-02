<?php

/**
 * MyDMS. Document Management System
 * Copyright (C) 2002-2005  Markus Westphal
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

use Symfony\Component\HttpFoundation\Request;

/* Actually not needed anymore, but some old extension may still use
 * S_RELEASED, S_REJECTED, etc. from SeedDMS_Core_Document. So we keep
 * it for a while. Should be removed von 6.0.31 and 5.1.38 is released.
 */
if (!empty($settings->_coreDir)) {
	require_once $settings->_coreDir . '/Core.php';
} else {
	require_once 'vendor/seeddms/core/Core.php';
}

$request = Request::createFromGlobals();
