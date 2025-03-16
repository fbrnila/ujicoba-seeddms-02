<?php
declare(strict_types=1);

/**
 * Implementation of various file system operations
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @license    GPL 2
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2024 Uwe Steinmann
 */

/**
 * Class with file operations in the document management system
 *
 * Use the methods of this class only for files below the content
 * directory but not for temporäry files, cache files or log files.
 *
 * @category   DMS
 * @package    SeedDMS_Core
 * @author     Markus Westphal, Malcolm Cowe, Uwe Steinmann <uwe@steinmann.cx>
 * @copyright  Copyright (C) 2002-2005 Markus Westphal,
 *             2006-2008 Malcolm Cowe, 2010 Matteo Lucarelli,
 *             2010-2024 Uwe Steinmann
 */
class SeedDMS_Core_File {
	/**
	 * Rename a file
	 *
	 * @param $old old name of file
	 * @param $new new name of file
	 * @return bool
	 */
	public static function renameFile($old, $new) { /* {{{ */
		return @rename($old, $new);
	} /* }}} */

	/**
	 * Delete a file
	 *
	 * @param $file name of file
	 * @return bool
	 */
	public static function removeFile($file) { /* {{{ */
		return @unlink($file);
	} /* }}} */

	/**
	 * Make copy of file
	 *
	 * @param $source name of file to be copied
	 * @param $target name of target file
	 * @return bool
	 */
	public static function copyFile($source, $target) { /* {{{ */
		return @copy($source, $target);
	} /* }}} */

	/**
	 * Create symbolic link
	 *
	 * @param $source name of link
	 * @param $target name of file to link
	 * @return bool
	 */
	public static function linkFile($source, $target) { /* {{{ */
		return symlink($source, $target);
	} /* }}} */

	/**
	 * Move file
	 *
	 * @param $source name of file to be moved
	 * @param $target name of target file
	 * @return bool
	 */
	public static function moveFile($source, $target) { /* {{{ */
		if (!self::copyFile($source, $target))
			return false;
		return self::removeFile($source);
	} /* }}} */

	/**
	 * Return size of file
	 *
	 * @param $file name of file
	 * @return bool|int
	 */
	public static function fileSize($file) { /* {{{ */
		if (!$a = @fopen($file, 'r'))
			return false;
		fseek($a, 0, SEEK_END);
		$filesize = ftell($a);
		fclose($a);
		return $filesize;
	} /* }}} */

	/**
	 * Return the mimetype of a given file
	 *
	 * This method uses finfo to determine the mimetype
	 * but will correct some mimetypes which are
	 * not propperly determined or could be more specific, e.g. text/plain
	 * when it is actually text/markdown. In thoses cases
	 * the file extension will be taken into account.
	 *
	 * @param string $filename name of file on disc
	 * @return string mimetype
	 */
	public static function mimetype($filename) { /* {{{ */
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimetype = finfo_file($finfo, $filename);

		$lastDotIndex = strrpos($filename, ".");
		if ($lastDotIndex === false) $fileType = ".";
		else $fileType = substr($filename, $lastDotIndex);

		switch ($mimetype) {
		case 'application/octet-stream':
		case 'text/plain':
			if ($fileType == '.md')
				$mimetype = 'text/markdown';
			elseif ($fileType == '.tex')
				$mimetype = 'text/x-tex';
			elseif ($fileType == '.docx')
				$mimetype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
			elseif ($fileType == '.pkpass')
				$mimetype = 'application/vnd.apple.pkpass';
			break;
		case 'application/zip':
			if ($fileType == '.pkpass')
				$mimetype = 'application/vnd.apple.pkpass';
			elseif ($fileType == '.numbers')
				$mimetype = 'application/vnd.apple.numbers';
			elseif ($fileType == '.pages')
				$mimetype = 'application/vnd.apple.pages';
			elseif ($fileType == '.key')
				$mimetype = 'application/vnd.apple.keynote';
			break;
		case 'application/gzip':
			if ($fileType == '.xopp')
				$mimetype = 'application/x-xopp';
			break;
		case 'text/xml':
			if ($fileType == '.html')
				$mimetype = 'text/html';
			break;
		}
		return $mimetype;
	} /* }}} */

	/**
	 * Turn an integer into a string with units for bytes
	 *
	 * @param integer $size
	 * @param array $sizes list of units for 10^0, 10^3, 10^6, ..., 10^(n*3) bytes
	 * @return string
	 */
	public static function format_filesize($size, $sizes = array('Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB')) { /* {{{ */
		if ($size == 0) return('0 Bytes');
		if ($size == 1) return('1 Byte');
		return (round($size/pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $sizes[$i]);
	} /* }}} */

	/**
	 * Parses a string like '[0-9]+ *[BKMGT]*' into an integer
	 *
	 * B,K,M,G,T stand for byte, kilo byte, mega byte, giga byte, tera byte
	 * If the last character is omitted, bytes are assumed. Arbitrary
	 * spaces between the number and the unit are allowed.
	 *
	 * @param $str string to be parsed
	 * @return bool|int
	 */
	public static function parse_filesize($str) { /* {{{ */
		if (!preg_match('/^([0-9]+) *([BKMGT]*)$/', trim($str), $matches))
			return false;
		$value = $matches[1];
		$unit = $matches[2] ? $matches[2] : 'B';
		switch ($unit) {
			case 'T':
				return $value * 1024 * 1024 * 1024 *1024;
				break;
			case 'G':
				return $value * 1024 * 1024 * 1024;
				break;
			case 'M':
				return $value * 1024 * 1024;
				break;
			case 'K':
				return $value * 1024;
				break;
			default:
				return (int) $value;
				break;
		}
		return false;
	} /* }}} */

	/**
	 * Check if file exists
	 *
	 * @param $file name of file to be checked
	 * @return bool true if file exists
	 */
	public static function file_exists($file) { /* {{{ */
		return file_exists($file);
	} /* }}} */

	/**
	 * Calculate the checksum of a file
	 *
	 * This method calculates the md5 sum of the file's content.
	 *
	 * @param $file name of file
	 * @return string md5 sum of file
	 */
	public static function checksum($file) { /* {{{ */
		return md5_file($file);
	} /* }}} */

	/**
	 * Return file extension by mimetype
	 *
	 * This methods returns the common file extension for a given mime type.
	 *
	 * @param $string mimetype
	 * @return string file extension with the dot or an empty string
	 */
	public static function fileExtension($mimetype) { /* {{{ */
		switch ($mimetype) {
		case "application/pdf":
		case "image/png":
		case "image/gif":
		case "image/jpg":
			$expect = substr($mimetype, -3, 3);
			break;
		default:
			$mime_map = [
				'video/3gpp2' => '3g2',
				'video/3gp' => '3gp',
				'video/3gpp' => '3gp',
				'application/x-compressed' => '7zip',
				'audio/x-acc'=> 'aac',
				'audio/ac3' => 'ac3',
				'application/postscript' => 'ps',
				'audio/x-aiff' => 'aif',
				'audio/aiff' => 'aif',
				'audio/x-au' => 'au',
				'video/x-msvideo' => 'avi',
				'video/msvideo' => 'avi',
				'video/avi' => 'avi',
				'application/x-troff-msvideo' => 'avi',
				'application/macbinary' => 'bin',
				'application/mac-binary' => 'bin',
				'application/x-binary' => 'bin',
				'application/x-macbinary' => 'bin',
				'image/bmp' => 'bmp',
				'image/x-bmp' => 'bmp',
				'image/x-bitmap' => 'bmp',
				'image/x-xbitmap' => 'bmp',
				'image/x-win-bitmap' => 'bmp',
				'image/x-windows-bmp' => 'bmp',
				'image/ms-bmp' => 'bmp',
				'image/x-ms-bmp' => 'bmp',
				'application/bmp' => 'bmp',
				'application/x-bmp' => 'bmp',
				'application/x-win-bitmap' => 'bmp',
				'application/cdr' => 'cdr',
				'application/coreldraw' => 'cdr',
				'application/x-cdr' => 'cdr',
				'application/x-coreldraw' => 'cdr',
				'image/cdr' => 'cdr',
				'image/x-cdr' => 'cdr',
				'zz-application/zz-winassoc-cdr' => 'cdr',
				'application/mac-compactpro' => 'cpt',
				'application/pkix-crl' => 'crl',
				'application/pkcs-crl' => 'crl',
				'application/x-x509-ca-cert' => 'crt',
				'application/pkix-cert' => 'crt',
				'text/css' => 'css',
				'text/x-comma-separated-values' => 'csv',
				'text/comma-separated-values' => 'csv',
				'application/vnd.msexcel' => 'xls',
				'application/x-director' => 'dcr',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
				'application/x-dvi' => 'dvi',
				'message/rfc822' => 'eml',
				'application/x-msdownload' => 'exe',
				'video/x-f4v' => 'f4v',
				'audio/x-flac' => 'flac',
				'video/x-flv' => 'flv',
				'image/gif' => 'gif',
				'application/gpg-keys' => 'gpg',
				'application/x-gtar' => 'tar.gz',
				'application/x-gzip' => 'gzip',
				'application/mac-binhex40' => 'hqx',
				'application/mac-binhex' => 'hqx',
				'application/x-binhex40' => 'hqx',
				'application/x-mac-binhex40' => 'hqx',
				'text/html' => 'html',
				'image/x-icon' => 'ico',
				'image/x-ico' => 'ico',
				'image/vnd.microsoft.icon' => 'ico',
				'text/calendar' => 'ics',
				'application/java-archive' => 'jar',
				'application/x-java-application' => 'jar',
				'application/x-jar' => 'jar',
				'image/jp2' => 'jp2',
				'video/mj2'=> 'jp2',
				'image/jpx' => 'jp2',
				'image/jpm' => 'jp2',
				'image/jpeg' => 'jpeg',
				'image/pjpeg' => 'jpeg',
				'application/x-javascript' => 'js',
				'application/json' => 'json',
				'text/json' => 'json',
				'application/vnd.google-earth.kml+xml' => 'kml',
				'application/vnd.google-earth.kmz' => 'kmz',
				'text/x-log' => 'log',
				'audio/x-m4a' => 'm4a',
				'application/vnd.mpegurl' => 'm4u',
				'text/markdown' => 'md',
				'audio/midi' => 'mid',
				'application/vnd.mif' => 'mif',
				'video/quicktime' => 'mov',
				'video/x-sgi-movie' => 'movie',
				'audio/mpeg' => 'mp3',
				'audio/mpg' => 'mp3',
				'audio/mpeg3' => 'mp3',
				'audio/mp3' => 'mp3',
				'video/mp4' => 'mp4',
				'video/mpeg' => 'mpeg',
				'application/oda' => 'oda',
				'audio/ogg' => 'ogg',
				'video/ogg' => 'ogg',
				'application/ogg' => 'ogg',
				'application/x-pkcs10' => 'p10',
				'application/pkcs10' => 'p10',
				'application/x-pkcs12' => 'p12',
				'application/x-pkcs7-signature' => 'p7a',
				'application/pkcs7-mime' => 'p7c',
				'application/x-pkcs7-mime' => 'p7c',
				'application/x-pkcs7-certreqresp' => 'p7r',
				'application/pkcs7-signature' => 'p7s',
				'application/pdf' => 'pdf',
				'application/octet-stream' => 'pdf',
				'application/x-x509-user-cert' => 'pem',
				'application/x-pem-file' => 'pem',
				'application/pgp' => 'pgp',
				'application/x-httpd-php' => 'php',
				'application/php' => 'php',
				'application/x-php' => 'php',
				'text/php' => 'php',
				'text/x-php' => 'php',
				'application/x-httpd-php-source' => 'php',
				'image/png' => 'png',
				'image/x-png' => 'png',
				'application/powerpoint' => 'ppt',
				'application/vnd.ms-powerpoint' => 'ppt',
				'application/vnd.ms-office' => 'ppt',
				'application/msword' => 'doc',
				'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
				'application/x-photoshop' => 'psd',
				'image/vnd.adobe.photoshop' => 'psd',
				'audio/x-realaudio' => 'ra',
				'audio/x-pn-realaudio' => 'ram',
				'application/x-rar' => 'rar',
				'application/rar' => 'rar',
				'application/x-rar-compressed' => 'rar',
				'audio/x-pn-realaudio-plugin' => 'rpm',
				'application/x-pkcs7' => 'rsa',
				'text/rtf' => 'rtf',
				'text/richtext' => 'rtx',
				'video/vnd.rn-realvideo' => 'rv',
				'application/x-stuffit' => 'sit',
				'application/smil' => 'smil',
				'text/srt' => 'srt',
				'image/svg+xml' => 'svg',
				'application/x-shockwave-flash' => 'swf',
				'application/x-tar' => 'tar',
				'application/x-gzip-compressed' => 'tgz',
				'image/tiff' => 'tiff',
				'text/plain' => 'txt',
				'text/x-vcard' => 'vcf',
				'application/videolan' => 'vlc',
				'text/vtt' => 'vtt',
				'audio/x-wav' => 'wav',
				'audio/wave' => 'wav',
				'audio/wav' => 'wav',
				'application/wbxml' => 'wbxml',
				'video/webm' => 'webm',
				'audio/x-ms-wma' => 'wma',
				'application/wmlc' => 'wmlc',
				'video/x-ms-wmv' => 'wmv',
				'video/x-ms-asf' => 'wmv',
				'application/xhtml+xml' => 'xhtml',
				'application/excel' => 'xl',
				'application/msexcel' => 'xls',
				'application/x-msexcel' => 'xls',
				'application/x-ms-excel' => 'xls',
				'application/x-excel' => 'xls',
				'application/x-dos_ms_excel' => 'xls',
				'application/xls' => 'xls',
				'application/x-xls' => 'xls',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
				'application/vnd.ms-excel' => 'xlsx',
				'application/xml' => 'xml',
				'text/xml' => 'xml',
				'text/xsl' => 'xsl',
				'application/xspf+xml' => 'xspf',
				'application/x-compress' => 'z',
				'application/x-zip' => 'zip',
				'application/zip' => 'zip',
				'application/x-zip-compressed' => 'zip',
				'application/s-compressed' => 'zip',
				'multipart/x-zip' => 'zip',
				'text/x-scriptzsh' => 'zsh',
			];
			$expect = isset($mime_map[$mimetype]) === true ? $mime_map[$mimetype] : '';
		}
		return $expect;
	} /* }}} */

	/**
	 * Rename a directory
	 *
	 * @param $old name of directory to be renamed
	 * @param $new new name of directory
	 * @return bool
	 */
	public static function renameDir($old, $new) { /* {{{ */
		return @rename($old, $new);
	} /* }}} */

	/**
	 * Create a directory
	 *
	 * @param $path path of new directory
	 * @return bool
	 */
	public static function makeDir($path) { /* {{{ */
		if (!is_dir($path)) {
			$res = @mkdir($path, 0777, true);
			if (!$res) return false;
		}

		return true;

/* some old code
		if (strncmp($path, DIRECTORY_SEPARATOR, 1) == 0) {
			$mkfolder = DIRECTORY_SEPARATOR;
		}
		else {
			$mkfolder = "";
		}
		$path = preg_split("/[\\\\\/]/", $path);
		for($i = 0; isset( $path[$i]); $i++)
		{
			if (!strlen(trim($path[$i])))continue;
			$mkfolder .= $path[$i];

			if (!is_dir( $mkfolder)){
				$res = @mkdir("$mkfolder", 0777);
				if (!$res) return false;
			}
			$mkfolder .= DIRECTORY_SEPARATOR;
		}

		return true;

		// patch from alekseynfor safe_mod or open_basedir

		global $settings;
		$path = substr_replace ($path, "/", 0, strlen($settings->_contentDir));
		$mkfolder = $settings->_contentDir;

		$path = preg_split( "/[\\\\\/]/", $path);

		for($i = 0; isset($path[$i]); $i++)
		{
			if (!strlen(trim($path[$i])))continue;
			$mkfolder .= $path[$i];

			if (!is_dir( $mkfolder)){
				$res = @mkdir("$mkfolder", 0777);
				if (!$res) return false;
			}
			$mkfolder .= DIRECTORY_SEPARATOR;
		}

		return true;
*/
	} /* }}} */

	/**
	 * Delete directory
	 *
	 * This method recursively deletes a directory including all its files.
	 *
	 * @param $path path of directory to be deleted
	 * @return bool
	 */
	public static function removeDir($path) { /* {{{ */
		$handle = @opendir($path);
		if (!$handle)
			return false;
		while ($entry = @readdir($handle)) {
			if ($entry == ".." || $entry == ".")
				continue;
			elseif (is_dir($path . DIRECTORY_SEPARATOR . $entry)) {
				if (!self::removeDir($path . DIRECTORY_SEPARATOR . $entry))
					return false;
			} else {
				if (!@unlink($path . DIRECTORY_SEPARATOR . $entry))
					return false;
			}
		}
		@closedir($handle);
		return @rmdir($path);
	} /* }}} */

	/**
	 * Copy a directory
	 *
	 * This method copies a directory recursively including all its files.
	 *
	 * @param $sourcePath path of directory to copy
	 * @param $targetPath path of target directory
	 * @return bool
	 */
	public static function copyDir($sourcePath, $targetPath) { /* {{{ */
		if (mkdir($targetPath, 0777)) {
			$handle = @opendir($sourcePath);
			while ($entry = @readdir($handle)) {
				if ($entry == ".." || $entry == ".")
					continue;
				elseif (is_dir($sourcePath . $entry)) {
					if (!self::copyDir($sourcePath . DIRECTORY_SEPARATOR . $entry, $targetPath . DIRECTORY_SEPARATOR . $entry))
						return false;
				} else {
					if (!@copy($sourcePath . DIRECTORY_SEPARATOR . $entry, $targetPath . DIRECTORY_SEPARATOR . $entry))
						return false;
				}
			}
			@closedir($handle);
		} else {
			return false;
		}

		return true;
	} /* }}} */

	/**
	 * Move a directory
	 *
	 * @param $sourcePath path of directory to be moved
	 * @param $targetPath new name of directory
	 * @return bool
	 */
	public static function moveDir($sourcePath, $targetPath) { /* {{{ */
		if (!self::copyDir($sourcePath, $targetPath))
			return false;
		return self::removeDir($sourcePath);
	} /* }}} */

	// code by Kioob (php.net manual)
	/**
	 * Compress a file with gzip
	 *
	 * @param $source path of file to be compressed
	 * @param bool $level level of compression
	 * @return bool|string file name of compressed file or false in case of an error
	 */
	public static function gzcompressfile($source, $level = false) { /* {{{ */
		$dest = $source.'.gz';
		$mode = 'wb'.$level;
		$error = false;
		if ($fp_out = @gzopen($dest, $mode)) {
			if ($fp_in = @fopen($source, 'rb')) {
				while (!feof($fp_in))
					@gzwrite($fp_out, fread($fp_in, 1024*512));
				@fclose($fp_in);
			} else {
				$error = true;
			}
			@gzclose($fp_out);
		} else {
			$error = true;
		}

		if ($error) return false;
		else return $dest;
	} /* }}} */
}
