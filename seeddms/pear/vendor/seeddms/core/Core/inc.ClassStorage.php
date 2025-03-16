<?php
declare(strict_types=1);

/**
 * Implementation of document storage
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @license    GPL 2
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2024 Uwe Steinmann
 */

/**
 * Class with operations to put documents into the storage
 *
 * Use the methods to access the document storage
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2010-2024 Uwe Steinmann
 */
interface SeedDMS_Core_Storage {

	public function saveAttachment($document, $attachment, $tmpFile);

	public function deleteAttachment($document, $attachment);
}
