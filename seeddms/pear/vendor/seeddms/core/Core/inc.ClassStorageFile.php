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
class SeedDMS_Core_Storage_File implements SeedDMS_Core_Storage {

	protected $basedir;

	protected $maxdirid;

	protected $forcerename;

	protected $forcelink;

	public function __construct($basedir, $maxdirid = 0, $forcerename = false, $forcelink = false) { /* {{{ */
		$this->forcerename = $forcerename;
		$this->forcelink = $forcelink;
		if (substr($basedir, -1) == DIRECTORY_SEPARATOR)
			$this->basedir = $basedir;
		else
			$this->basedir = $basedir.DIRECTORY_SEPARATOR;
		$this->maxdirid = $maxdirid;
	} /* }}} */

	public function deleteContentDir() {
		$err = true;
		$dir = $this->basedir;
		if (SeedDMS_Core_File::file_exists($dir))
			$err = SeedDMS_Core_File::removeDir($dir);
		return $err;
	}

	protected function getDocDir($document) {
		if ($this->maxdirid) {
			$dirid = (int) (($document->getId()-1) / $this->maxdirid) + 1;
			return $dirid.DIRECTORY_SEPARATOR.$document->getId().DIRECTORY_SEPARATOR;
		} else {
			return $document->getId().DIRECTORY_SEPARATOR;
		}
	}

	public function deleteDocDir($document) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		if (SeedDMS_Core_File::file_exists($dir))
			$err = SeedDMS_Core_File::removeDir($dir);
		return $err;
	}

	public function saveAttachment($document, $attachment, $tmpFile) {
		$dir = $this->basedir . $this->getDocDir($document);
		$fileType = $attachment->getFileType();
		if (!SeedDMS_Core_File::makeDir($dir)) return false;
		if ($this->forcerename)
			$err = SeedDMS_Core_File::renameFile($tmpFile, $dir . "f" .$attachment->getId() . $fileType);
		else
			$err = SeedDMS_Core_File::copyFile($tmpFile, $dir . "f" .$attachment->getId() . $fileType);

		return $err;
	}

	public function deleteAttachment($document, $attachment) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		$fileType = $attachment->getFileType();
		if (SeedDMS_Core_File::file_exists($dir . "f" . $attachment->getId() . $fileType)) {
			$err = SeedDMS_Core_File::removeFile($dir . "f" . $attachment->getId() . $fileType);
		}
		return $err;
	}

	public function getAttachment($document, $attachment) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		$fileType = $attachment->getFileType();
		if (SeedDMS_Core_File::file_exists($dir . "f" . $attachment->getId() . $fileType)) {
			$err = file_get_contents($dir . "f" . $attachment->getId() . $fileType);
		}
		return $err;
	}

	public function getAttachmentName($document, $attachment) {
		$dir = $this->basedir . $this->getDocDir($document);
		return dir.'f'.$attachment->getId().$attachment->getFileType();
	}

	public function getAttachmentFilesize($document, $attachment) {
		$dir = $this->basedir . $this->getDocDir($document);
		$filesize = SeedDMS_Core_File::fileSize($dir . "f" . $attachment->getId());
		return $filesize;
	}

	public function getAttachmentMimeType($document, $attachment) {
		$dir = $this->basedir . $this->getDocDir($document);
		$filesize = SeedDMS_Core_File::fileSize($dir . "f" . $attachment->getId());
		return $filesize;
	}

	public function saveContent($document, $content, $tmpFile) {
		$dir = $this->basedir . $this->getDocDir($document);
		$version = $content->getVersion();
		$fileType = $content->getFileType();
		if (!SeedDMS_Core_File::makeDir($dir)) {
			return false;
		}
		if ($this->forcerename)
			$err = SeedDMS_Core_File::renameFile($tmpFile, $dir . $version . $fileType);
		elseif ($this->forcelink)
			$err = SeedDMS_Core_File::linkFile($tmpFile, $dir . $version . $fileType);
		else
			$err = SeedDMS_Core_File::copyFile($tmpFile, $dir . $version . $fileType);

		return $err;
	}

	public function setFileType($document, $content, $newfiletype) {
		$dir = $this->basedir . $this->getDocDir($document);
		$version = $content->getVersion();
		$fileType = $content->getFileType();
		if (!SeedDMS_Core_File::makeDir($dir)) {
			return false;
		}
		$err = SeedDMS_Core_File::renameFile($dir . $version . $fileType, $dir . $version . $newfiletype);

		return $err;
	}

	public function replaceContent($document, $content, $tmpFile) {
		$dir = $this->basedir . $this->getDocDir($document);
		$version = $content->getVersion();
		$fileType = $content->getFileType();
		$err = SeedDMS_Core_File::copyFile($tmpFile, $dir . $version . $fileType);
		return $err;
	}

	public function deleteContent($document, $content) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		if (SeedDMS_Core_File::file_exists($dir . $content->getVersion() . $content->getFileType()))
			$err = SeedDMS_Core_File::removeFile($dir . $content->getVersion() . $content->getFileType());
		return $err;
	}

	public function getContent($document, $content) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		if (SeedDMS_Core_File::file_exists($dir . $content->getVersion() . $content->getFileType()))
			$err = file_get_contents($dir . $content->getVersion() . $content->getFileType());
		return $err;
	}

	public function getContentName($document, $content) {
		$dir = $this->basedir . $this->getDocDir($document);
		return dir.$content->getVersion().$content->getFileType();
	}

	public function getContentStream($document, $content) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		if (SeedDMS_Core_File::file_exists($dir . $content->getVersion() . $content->getFileType()))
			$err = fopen($dir . $content->getVersion() . $content->getFileType(), 'r');
		return $err;
	}

	public function getContentFilesize($document, $content) {
		$dir = $this->basedir . $this->getDocDir($document);
		$filesize = SeedDMS_Core_File::fileSize($dir . $content->getVersion() . $content->getFileType());
		return $filesize;
	}

	public function getContentMimetype($document, $content) {
		$dir = $this->basedir . $this->getDocDir($document);
		$filesize = SeedDMS_Core_File::mimetype($dir . $content->getVersion() . $content->getFileType());
		return $filesize;
	}

	public function getContentChecksum($document, $content) {
		$dir = $this->basedir . $this->getDocDir($document);
		$filesize = SeedDMS_Core_File::checksum($dir . $content->getVersion() . $content->getFileType());
		return $filesize;
	}

	public function saveReview($document, $id, $tmpFile) {
		$dir = $this->basedir . $this->getDocDir($document);
		$file = dir.'r'.$id;
		return SeedDMS_Core_File::copyFile($tmpFile, $file);
	}

	public function deleteReview($document, $id) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		$file = dir.'r'.$id;
		if (SeedDMS_Core_File::file_exists($file))
			$err = SeedDMS_Core_File::removeFile($file);
		return $err;
	}

	public function getReview($document, $id) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		$file = dir.'r'.$id;
		if (SeedDMS_Core_File::file_exists($file))
			$err = file_get_contents($file);
		return $err;
	}

	public function getReviewName($document, $id) {
		$dir = $this->basedir . $this->getDocDir($document);
		return dir.'r'.$id;
	}

	public function saveApproval($document, $id, $tmpFile) {
		$dir = $this->basedir . $this->getDocDir($document);
		$file = dir.'a'.$id;
		return SeedDMS_Core_File::copyFile($tmpFile, $file);
	}

	public function deleteApproval($document, $id) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		$file = dir.'a'.$id;
		if (SeedDMS_Core_File::file_exists($file))
			$err = SeedDMS_Core_File::removeFile($file);
		return $err;
	}

	public function getApproval($document, $id) {
		$err = true;
		$dir = $this->basedir . $this->getDocDir($document);
		$file = dir.'a'.$id;
		if (SeedDMS_Core_File::file_exists($file))
			$err = file_get_contents($file);
		return $err;
	}

	public function getApprovalName($document, $id) {
		$dir = $this->basedir . $this->getDocDir($document);
		return dir.'a'.$id;
	}
}
