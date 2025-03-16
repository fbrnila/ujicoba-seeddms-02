<?php
if(isset($_SERVER['SEEDDMS_HOME'])) {
	ini_set('include_path', $_SERVER['SEEDDMS_HOME'].'/utils'. PATH_SEPARATOR .ini_get('include_path'));
	$myincpath = $_SERVER['SEEDDMS_HOME'];
} else {
	ini_set('include_path', dirname($argv[0]). PATH_SEPARATOR .ini_get('include_path'));
	$myincpath = dirname($argv[0]);
}

function usage() { /* {{{ */
	echo "Usage:".PHP_EOL;
	echo "  seeddms-importfs [--config <file>] [-h] [-v] -F <folder id> -d <dirname>".PHP_EOL;
	echo PHP_EOL;
	echo "Description:".PHP_EOL;
	echo "  This program uploads a directory recursively into a folder of SeedDMS.".PHP_EOL;
	echo PHP_EOL;
	echo "Options:".PHP_EOL;
	echo "  -h, --help: print usage information and exit.".PHP_EOL;
	echo "  -v, --version: print version and exit.".PHP_EOL;
	echo "  --config: set alternative config file.".PHP_EOL;
	echo "  --user: use this user for accessing seeddms.".PHP_EOL;
	echo "  --exclude: exlude files/directories by name (defaults to .svn, .gitignore).".PHP_EOL;
	echo "      This must be just the file or directory without the path.".PHP_EOL;
	echo "  --filemtime: take over modification time from file.".PHP_EOL;
	echo "  --foldermtime: take over modification time from folder.".PHP_EOL;
	echo "  --basefolder: creates the base folder".PHP_EOL;
	echo "  -F <folder id>: id of folder the file is uploaded to".PHP_EOL;
	echo "  -d <dirname>: upload this directory".PHP_EOL;
	echo "  -e <encoding>: encoding used by filesystem (defaults to iso-8859-1)".PHP_EOL;
} /* }}} */

$version = "0.0.1";
$shortoptions = "d:F:e:hv";
$longoptions = array('help', 'version', 'user:', 'basefolder', 'filemtime', 'foldermtime', 'exclude:', 'config:');
if(false === ($options = getopt($shortoptions, $longoptions))) {
	usage();
	exit(0);
}

/* Print help and exit */
if(!$options || isset($options['h']) || isset($options['help'])) {
	usage();
	exit(0);
}

/* Print version and exit */
if(isset($options['v']) || isset($options['verѕion'])) {
	echo $version.PHP_EOL;
	exit(0);
}

/* Set encoding of names in filesystem */
$fsencoding = 'iso-8859-1';
if(isset($options['e'])) {
	$fsencoding = $options['e'];
}

/* Set alternative config file */
if(isset($options['config'])) {
	define('SEEDDMS_CONFIG_FILE', $options['config']);
} elseif(isset($_SERVER['SEEDDMS_CONFIG_FILE'])) {
	define('SEEDDMS_CONFIG_FILE', $_SERVER['SEEDDMS_CONFIG_FILE']);
}

$excludefiles = array('.', '..');
if(isset($options['exclude'])) {
	if(is_array($options['exclude']))
		$excludefiles = array_merge($excludefiles, $options['exclude']);
	else
		$excludefiles[] = $options['exclude'];
} else {
	$excludefiles[] = '.svn';
	$excludefiles[] = '.gitignore';
}

if(isset($options['user'])) {
	$userlogin = $options['user'];
} else {
	echo "Missing user".PHP_EOL;
	usage();
	exit(1);
}

/* check if base folder shall be created */
$createbasefolder = false;
if(isset($options['basefolder'])) {
	$createbasefolder = true;
}

/* check if modification time shall be taken over */
$setfiledate = false;
if(isset($options['filemtime'])) {
	$setfiledate = true;
}
$setfolderdate = false;
if(isset($options['setfolderdate'])) {
	$setfolderdate = true;
}

if(isset($settings->_extraPath))
	ini_set('include_path', $settings->_extraPath. PATH_SEPARATOR .ini_get('include_path'));

if(isset($options['F'])) {
	$folderid = (int) $options['F'];
} else {
	echo "Missing folder ID".PHP_EOL;
	usage();
	exit(1);
}

$dirname = '';
if(isset($options['d'])) {
	$dirname = $options['d'];
} else {
	echo "Missing import directory".PHP_EOL;
	usage();
	exit(1);
}

include($myincpath."/inc/inc.Settings.php");
include($myincpath."/inc/inc.Utils.php");
include($myincpath."/inc/inc.Language.php");
include($myincpath."/inc/inc.Init.php");
include($myincpath."/inc/inc.Extension.php");
include($myincpath."/inc/inc.DBInit.php");

echo $settings->_contentDir.$settings->_contentOffsetDir.PHP_EOL;

function getBaseData($colname, $coldata, $objdata) { /* {{{ */
	$objdata[$colname] = $coldata;
	return $objdata;
} /* }}} */

function getAttributeData($attrdef, $coldata, $objdata) { /* {{{ */
	$objdata['attributes'][$attrdef->getID()] = $coldata;
	return $objdata;
} /* }}} */

function getCategoryData($colname, $coldata, $objdata) { /* {{{ */
	global $catids;
	$kk = explode(',', $coldata);
	$objdata['category'][] = array();
	foreach($kk as $k) {
		if(isset($catids[$k]))
			$objdata['category'][] = $catids[$k];
	}
	return $objdata;
} /* }}} */

function getUserData($colname, $coldata, $objdata) { /* {{{ */
	global $userids;
	if(isset($userids[$coldata]))
		$objdata['owner'] = $userids[$coldata];
	return $objdata;
} /* }}} */

$metadata = array();
if(isset($metadatafile)) {
	$csvdelim = ';';
	$csvencl = '"';
	if($fp = fopen($metadatafile, 'r')) {
		$colmap = array();
		if($header = fgetcsv($fp, 0, $csvdelim, $csvencl)) {
			foreach($header as $i=>$colname) {
				$colname = trim($colname);
				if(in_array($colname, array('category'))) {
					$colmap[$i] = array("getCategoryData", $colname);
				} elseif(in_array($colname, array('owner'))) {
					$colmap[$i] = array("getUserData", $colname);
				} elseif(in_array($colname, array('filename', 'category', 'name', 'comment'))) {
					$colmap[$i] = array("getBaseData", $colname);
				} elseif(substr($colname, 0, 5) == 'attr:') {
					$kk = explode(':', $colname, 2);
					if(($attrdef = $dms->getAttributeDefinitionByName($kk[1])) || ($attrdef = $dms->getAttributeDefinition((int) $kk[1]))) {
						$colmap[$i] = array("getAttributeData", $attrdef);
					}
				}
			}
		}
//		echo "<pre>";print_r($colmap);echo "</pre>";
		if(count($colmap) > 1) {
			$nameprefix = dirname($dirname).'/';
			$allcats = $dms->getDocumentCategories();
			$catids = array();
			foreach($allcats as $cat)
				$catids[$cat->getName()] = $cat;
			$allusers = $dms->getAllUsers();
			$userids = array();
			foreach($allusers as $muser)
				$userids[$muser->getLogin()] = $muser;
			while(!feof($fp)) {
				if($data = fgetcsv($fp, 0, $csvdelim, $csvencl)) {
					$mi = $nameprefix.$data[$colmap['filename']];
//					$metadata[$mi] = array('category'=>array());
					$md = array();
					$md['attributes'] = array();
					foreach($data as $i=>$coldata) {
						if(isset($colmap[$i])) {
							$md = call_user_func($colmap[$i][0], $colmap[$i][1], $coldata, $md);
						}
					}
					if(!empty($md['filename']))
						$metadata[$nameprefix.$md['filename']] = $md;
				}
			}
		}
	}
}

/* Create a global user object */
if(!($user = $dms->getUserByLogin($userlogin))) {
	echo "User with login '".$userlogin."' does not exists.";
	exit;
}

$folder = $dms->getFolder($folderid);
if (!is_object($folder)) {
	echo "Could not find specified folder".PHP_EOL;
	exit(1);
}

if ($folder->getAccessMode($user) < M_READWRITE) {
	echo "Not sufficient access rights".PHP_EOL;
	exit(1);
}

//$dms->setForceLink(true);

function import_folder($dirname, $folder, $setfiledate, $setfolderdate, $metadata) { /* {{{ */
	global $user, $doccount, $foldercount;

	$d = dir($dirname);
	$sequence = 1;
	while(false !== ($entry = $d->read())) {
		$path = $dirname.'/'.$entry;
		if(!in_array($entry, $excludefiles)) {
			if(is_file($path)) {
				$name = utf8_basename($path);
				$filetmp = $path;

				$reviewers = array();
				$approvers = array();
				$version_comment = '';
				$reqversion = 1;
				$expires = false;
				$keywords = '';
				$categories = array();

				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mimetype = finfo_file($finfo, $path);
				$lastDotIndex = strrpos($name, ".");
				if (is_bool($lastDotIndex) && !$lastDotIndex) $filetype = ".";
				else $filetype = substr($name, $lastDotIndex);

				$docname = !empty($metadata[$path]['name']) ? $metadata[$path]['name'] : $name;
				$comment = !empty($metadata[$path]['comment']) ? $metadata[$path]['comment'] : '';
				$owner = !empty($metadata[$path]['owner']) ? $metadata[$path]['owner'] : $user;

				echo $mimetype." - ".$filetype." - ".$path."<br />\n";
				if($res = $folder->addDocument($docname, $comment, $expires, $owner, $keywords,
																		!empty($metadata[$path]['category']) ? $metadata[$path]['category'] : array(), $filetmp, $name,
																		$filetype, $mimetype, $sequence, $reviewers,
																		$approvers, $reqversion, $version_comment,
																	 	!empty($metadata[$path]['attributes']) ? $metadata[$path]['attributes'] : array())) {
					$doccount++;
					if($setfiledate) {
						$newdoc = $res[0];
						$newdoc->setDate(filemtime($path));
						$lc = $newdoc->getLatestContent();
						$lc->setDate(filemtime($path));
					}
				} else {
					echo "Error importing ".$path."<br />";
					echo "<pre>".print_r($res, true)."</pre>";
//					return false;
				}
				set_time_limit(30);
			} elseif(is_dir($path)) {
				$name = utf8_basename($path);
				if($newfolder = $folder->addSubFolder($name, '', $user, $sequence)) {
					$foldercount++;
					if($setfolderdate) {
						$newfolder->setDate(filemtime($path));
					}
					if(!import_folder($path, $newfolder, $setfiledate, $setfolderdate, $metadata))
						return false;
				} else {
//					return false;
				}
			}
			$sequence++;
		}
	}
	return true;
} /* }}} */

if($createbasefolder) {
	if($newfolder = $folder->addSubFolder(basename($dirname), '', $user, 1)) {
		if($setfolderdate) {
			$newfolder->setDate(filemtime($dirname));
		}
		import_folder($dirname, $newfolder, $setfiledate, $setfolderdate, $metadata);
	}
} else {
	import_folder($dirname, $folder, $setfiledate, $setfolderdate, $metadata);
}

