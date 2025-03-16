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

/* Middleware for authentication based on session */
class SeedDMS_Auth_Middleware_Session { /* {{{ */

	private $container;

	public function __construct($container) {
		$this->container = $container;
	}

	/**
	 * Example middleware invokable class
	 *
	 * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
	 * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
	 * @param  callable                                 $next     Next middleware
	 *
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function __invoke($request, $handler) {
		// $this->container has the DI
		$dms = $this->container->get('dms');
		$settings = $this->container->get('config');
		$logger = $this->container->get('logger');
		$userobj = null;
		if ($this->container->has('userobj')) {
			$userobj = $this->container->get('userobj');
		}

		if ($userobj) {
			$response = $handler->handle($request);
			return $response;
		}

		$logger->log("Invoke middleware for method " . $request->getMethod() . " on '" . $request->getUri()->getPath() . "'", PEAR_LOG_INFO);
		require_once("inc/inc.ClassSession.php");
		$session = new SeedDMS_Session($dms->getDb());
		if (isset($_COOKIE["mydms_session"])) {
			$dms_session = $_COOKIE["mydms_session"];
			$logger->log("Session key: " . $dms_session, PEAR_LOG_DEBUG);
			if (!$resArr = $session->load($dms_session)) {
				/* Delete Cookie */
				setcookie("mydms_session", $dms_session, time() - 3600, $settings->_httpRoot);
				$logger->log("Session for id '" . $dms_session . "' has gone", PEAR_LOG_ERR);
				return $response->withStatus(403);
			}

			/* Load user data */
			$userobj = $dms->getUser($resArr["userID"]);
			if (!is_object($userobj)) {
				/* Delete Cookie */
				setcookie("mydms_session", $dms_session, time() - 3600, $settings->_httpRoot);
				if ($settings->_enableGuestLogin) {
					if (!($userobj = $dms->getUser($settings->_guestID))) {
						return $response->withStatus(403);
					}
				} else {
					return $response->withStatus(403);
				}
			}
			if ($userobj->isAdmin()) {
				if ($resArr["su"]) {
					if (!($userobj = $dms->getUser($resArr["su"]))) {
						return $response->withStatus(403);
					}
				}
			}
			$dms->setUser($userobj);
		} else {
			return $response->withStatus(403);
		}
		$this->container->set('userobj', $userobj);

		$response = $handler->handle($request);
		return $response;
	}
} /* }}} */
