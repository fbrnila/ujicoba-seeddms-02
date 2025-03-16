<?php
include("../inc/inc.Settings.php");

require_once("Log.php");
require_once("../inc/inc.Language.php");
require_once("../inc/inc.Utils.php");

$logger = getLogger('restapi-', (int) $settings->_logFileRestApiMaxLevel);

require_once("../inc/inc.Init.php");
require_once("../inc/inc.Extension.php");
require_once("../inc/inc.DBInit.php");
require_once("../inc/inc.ClassNotificationService.php");
require_once("../inc/inc.ClassEmailNotify.php");
require_once("../inc/inc.Notification.php");
require_once("../inc/inc.ClassController.php");

require "vendor/autoload.php";

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

final class JsonRenderer { /* {{{ */
    public function json(
        ResponseInterface $response,
        array $data = null
    ): ResponseInterface {
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(
            (string)json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            )
        );
        return $response;
    }
} /* }}} */

final class SeedDMS_RestapiController { /* {{{ */
    protected $container;
    protected $renderer;

    // constructor receives container and renderer instance by DI
    public function __construct(ContainerInterface $container, JsonRenderer $renderer) {
        $this->container = $container;
        $this->renderer = $renderer;
    }

    protected function __getAttributesData($obj) { /* {{{ */
        $attributes = $obj->getAttributes();
        $attrvalues = array();
        if($attributes) {
            foreach($attributes as $attrdefid=>$attribute) {
                $attrdef = $attribute->getAttributeDefinition();
                $attrvalues[] = array(
                    'id'=>(int) $attrdef->getId(),
                    'name'=>$attrdef->getName(),
                    'value'=>$attribute->getValue()
                );
            }
        }
        return $attrvalues;
    } /* }}} */

    protected function __getDocumentData($document) { /* {{{ */
        $cats = $document->getCategories();
        $tmp = [];
        foreach($cats as $cat) {
            $tmp[] = $this->__getCategoryData($cat);
        }
        $data = array(
            'type'=>'document',
            'id'=>(int)$document->getId(),
            'date'=>date('Y-m-d H:i:s', $document->getDate()),
            'name'=>$document->getName(),
            'comment'=>$document->getComment(),
            'keywords'=>$document->getKeywords(),
            'categories'=>$tmp,
            'owner'=>(int)$document->getOwner()->getId()
        );
        return $data;
    } /* }}} */

    protected function __getLatestVersionData($lc) { /* {{{ */
        $document = $lc->getDocument();
        $data = array(
            'type'=>'document',
            'id'=>(int)$document->getId(),
            'date'=>date('Y-m-d H:i:s', $document->getDate()),
            'name'=>$document->getName(),
            'comment'=>$document->getComment(),
            'keywords'=>$document->getKeywords(),
            'ownerid'=>(int) $document->getOwner()->getID(),
            'islocked'=>$document->isLocked(),
            'sequence'=>$document->getSequence(),
            'expires'=>$document->getExpires() ? date('Y-m-d H:i:s', $document->getExpires()) : "",
            'mimetype'=>$lc->getMimeType(),
            'filetype'=>$lc->getFileType(),
            'origfilename'=>$lc->getOriginalFileName(),
            'version'=>$lc->getVersion(),
            'version_comment'=>$lc->getComment(),
            'version_date'=>date('Y-m-d H:i:s', $lc->getDate()),
            'size'=>(int) $lc->getFileSize(),
        );
        $cats = $document->getCategories();
        if($cats) {
            $c = array();
            foreach($cats as $cat) {
                $c[] = array('id'=>(int)$cat->getID(), 'name'=>$cat->getName());
            }
            $data['categories'] = $c;
        }
        $attributes = $this->__getAttributesData($document);
        if($attributes) {
            $data['attributes'] = $attributes;
        }
        $attributes = $this->__getAttributesData($lc);
        if($attributes) {
            $data['version_attributes'] = $attributes;
        }
        return $data;
    } /* }}} */

    protected function __getDocumentVersionData($lc) { /* {{{ */
        $data = array(
            'id'=>(int) $lc->getId(),
            'version'=>$lc->getVersion(),
            'date'=>date('Y-m-d H:i:s', $lc->getDate()),
            'mimetype'=>$lc->getMimeType(),
            'filetype'=>$lc->getFileType(),
            'origfilename'=>$lc->getOriginalFileName(),
            'size'=>(int) $lc->getFileSize(),
            'comment'=>$lc->getComment(),
        );
        return $data;
    } /* }}} */

    protected function __getDocumentFileData($file) { /* {{{ */
        $data = array(
            'id'=>(int)$file->getId(),
            'name'=>$file->getName(),
            'date'=>$file->getDate(),
            'mimetype'=>$file->getMimeType(),
            'comment'=>$file->getComment(),
        );
        return $data;
    } /* }}} */

    protected function __getDocumentLinkData($link) { /* {{{ */
        $data = array(
            'id'=>(int)$link->getId(),
            'target'=>$this->__getDocumentData($link->getTarget()),
            'public'=>(boolean)$link->isPublic(),
        );
        return $data;
    } /* }}} */

    protected function __getFolderData($folder) { /* {{{ */
        $data = array(
            'type'=>'folder',
            'id'=>(int)$folder->getID(),
            'name'=>$folder->getName(),
            'comment'=>$folder->getComment(),
            'date'=>date('Y-m-d H:i:s', $folder->getDate()),
            'owner'=>(int)$folder->getOwner()->getId()
        );
        $attributes = $this->__getAttributesData($folder);
        if($attributes) {
            $data['attributes'] = $attributes;
        }
        return $data;
    } /* }}} */

    protected function __getGroupData($u) { /* {{{ */
        $data = array(
            'type'=>'group',
            'id'=>(int)$u->getID(),
            'name'=>$u->getName(),
            'comment'=>$u->getComment(),
        );
        return $data;
    } /* }}} */

    protected function __getUserData($u) { /* {{{ */
        $data = array(
            'type'=>'user',
            'id'=>(int)$u->getID(),
            'name'=>$u->getFullName(),
            'comment'=>$u->getComment(),
            'login'=>$u->getLogin(),
            'email'=>$u->getEmail(),
            'language' => $u->getLanguage(),
            'quota' => $u->getQuota(),
            'homefolder' => $u->getHomeFolder(),
            'theme' => $u->getTheme(),
            'role' => $this->__getRoleData($u->getRole()), //array('id'=>(int)$u->getRole()->getId(), 'name'=>$u->getRole()->getName()),
            'hidden'=>$u->isHidden() ? true : false,
            'disabled'=>$u->isDisabled() ? true : false,
            'isguest' => $u->isGuest() ? true : false,
            'isadmin' => $u->isAdmin() ? true : false,
        );
        if($u->getHomeFolder())
            $data['homefolder'] = (int)$u->getHomeFolder();

        $groups = $u->getGroups();
        if($groups) {
            $tmp = [];
            foreach($groups as $group)
                $tmp[] = $this->__getGroupData($group);
            $data['groups'] = $tmp;
        }
        return $data;
    } /* }}} */

    protected function __getRoleData($r) { /* {{{ */
        $data = array(
            'type'=>'role',
            'id'=>(int)$r->getID(),
            'name'=>$r->getName(),
            'role'=>$r->getRole()
        );
        return $data;
    } /* }}} */

    protected function __getAttributeDefinitionData($attrdef) { /* {{{ */
        $data = [
            'id' => (int)$attrdef->getId(),
            'name' => $attrdef->getName(),
            'type'=>(int)$attrdef->getType(),
            'objtype'=>(int)$attrdef->getObjType(),
            'min'=>(int)$attrdef->getMinValues(),
            'max'=>(int)$attrdef->getMaxValues(),
            'multiple'=>$attrdef->getMultipleValues()?true:false,
            'valueset'=>$attrdef->getValueSetAsArray(),
            'regex'=>$attrdef->getRegex()
        ];
        return $data;
    } /* }}} */

    protected function __getCategoryData($category) { /* {{{ */
        $data = [
            'id'=>(int)$category->getId(),
            'name'=>$category->getName()
        ];
        return $data;
    } /* }}} */

    function doLogin($request, $response) { /* {{{ */
//        global $session;

        $dms = $this->container->get('dms');
        $settings = $this->container->get('config');
        $logger = $this->container->get('logger');
        $authenticator = $this->container->get('authenticator');

        $params = $request->getParsedBody();
        if(empty($params['user']) || empty($params['pass'])) {
            $logger->log("Login without username or password failed", PEAR_LOG_INFO);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No user or password given', 'data'=>''))->withStatus(400);
        }
        $username = $params['user'];
        $password = $params['pass'];
        $userobj = $authenticator->authenticate($username, $password);

        if(!$userobj) {
            setcookie("mydms_session", '', time()-3600, $settings->_httpRoot);
            $logger->log("Login with user name '".$username."' failed", PEAR_LOG_ERR);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Login failed', 'data'=>''))->withStatus(403);
        } else {
            require_once("../inc/inc.ClassSession.php");
            $session = new SeedDMS_Session($dms->getDb());
            if(!$id = $session->create(array('userid'=>$userobj->getId(), 'theme'=>$userobj->getTheme(), 'lang'=>$userobj->getLanguage()))) {
              return $this->renderer->json($response, array('success'=>false, 'message'=>'Creating session failed', 'data'=>''))->withStatus(500);
            }

            // Set the session cookie.
            if($settings->_cookieLifetime)
                $lifetime = time() + intval($settings->_cookieLifetime);
            else
                $lifetime = 0;
            setcookie("mydms_session", $id, $lifetime, $settings->_httpRoot);
            $dms->setUser($userobj);

            $logger->log("Login with user name '".$username."' successful", PEAR_LOG_INFO);
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getUserData($userobj)))->withStatus(200);
        }
    } /* }}} */

    function doLogout($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $settings = $this->container->get('config');

        if(isset($_COOKIE['mydms_session'])) {
            $dms_session = $_COOKIE["mydms_session"];
            $db = $dms->getDb();

            $session = new SeedDMS_Session($db);
            $session->load($dms_session);

            // If setting the user id to 0 worked, it would be a way to logout a
            // user. It doesn't work because of a foreign constraint in the database
            // won't allow it. So we keep on deleting the session and the cookie on
            // logout
            // $session->setUser(0); does not work because of foreign user constraint

            if(!$session->delete($dms_session)) {
                UI::exitError(getMLText("logout"),$db->getErrorMsg());
            }
            setcookie("mydms_session", '', time()-3600, $settings->_httpRoot);
        }
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
    } /* }}} */

    function setFullName($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
            return;
        }

        $params = $request->getParsedBody();
        $userobj->setFullName($params['fullname']);
        $data = $this->__getUserData($userobj);
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function setEmail($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
            return;
        }

        $params = $request->getParsedBody();
        $userobj->setEmail($params['email']);
        $data = $this->__getUserData($userobj);
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function getLockedDocuments($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(false !== ($documents = $dms->getDocumentsLockedByUser($userobj))) {
            $documents = SeedDMS_Core_DMS::filterAccess($documents, $userobj, M_READ);
            $recs = array();
            foreach($documents as $document) {
                $lc = $document->getLatestContent();
                if($lc) {
                    $recs[] = $this->__getLatestVersionData($lc);
                }
            }
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function getFolder($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $settings = $this->container->get('config');

        $params = $request->getQueryParams();
        $forcebyname = isset($params['forcebyname']) ? $params['forcebyname'] : 0;
        $parent = isset($params['parentid']) ? $dms->getFolder($params['parentid']) : null;

        if (!isset($args['id']) || !$args['id'])
            $folder = $dms->getFolder($settings->_rootFolderID);
        elseif(ctype_digit($args['id']) && empty($forcebyname))
            $folder = $dms->getFolder($args['id']);
        else {
            $folder = $dms->getFolderByName($args['id'], $parent);
        }
        if($folder) {
            if($folder->getAccessMode($userobj) >= M_READ) {
                $data = $this->__getFolderData($folder);
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getFolderParent($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $id = $args['id'];
        if($id == 0) {
            return $this->renderer->json($response, array('success'=>true, 'message'=>'Id is 0', 'data'=>''))->withStatus(200);
        }
        $root = $dms->getRootFolder();
        if($root->getId() == $id) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Id is root folder', 'data'=>''))->withStatus(200);
        }
        $folder = $dms->getFolder($id);
        if($folder) {
            $parent = $folder->getParent();
            if($parent) {
                if($parent->getAccessMode($userobj) >= M_READ) {
                    $rec = $this->__getFolderData($parent);
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$rec))->withStatus(200);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>''))->withStatus(500);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getFolderPath($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(empty($args['id'])) {
            return $this->renderer->json($response, array('success'=>true, 'message'=>'id is 0', 'data'=>''))->withStatus(200);
        }
        $folder = $dms->getFolder($args['id']);
        if($folder) {
            if($folder->getAccessMode($userobj) >= M_READ) {
                $path = $folder->getPath();
                $data = array();
                foreach($path as $element) {
                    $data[] = array('id'=>$element->getId(), 'name'=>$element->getName());
                }
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getFolderAttributes($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $folder = $dms->getFolder($args['id']);
        if($folder) {
            if ($folder->getAccessMode($userobj) >= M_READ) {
                $attributes = $this->__getAttributesData($folder);
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$attributes))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getFolderChildren($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(empty($args['id'])) {
            $folder = $dms->getRootFolder();
            $recs = array($this->$this->__getFolderData($folder));
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs))->withStatus(200);
        } else {
            $folder = $dms->getFolder($args['id']);
            if($folder) {
                if($folder->getAccessMode($userobj) >= M_READ) {
                    $recs = array();
                    $subfolders = $folder->getSubFolders();
                    $subfolders = SeedDMS_Core_DMS::filterAccess($subfolders, $userobj, M_READ);
                    foreach($subfolders as $subfolder) {
                        $recs[] = $this->__getFolderData($subfolder);
                    }
                    $documents = $folder->getDocuments();
                    $documents = SeedDMS_Core_DMS::filterAccess($documents, $userobj, M_READ);
                    foreach($documents as $document) {
                        $lc = $document->getLatestContent();
                        if($lc) {
                            $recs[] = $this->__getLatestVersionData($lc);
                        }
                    }
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs))->withStatus(200);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
            }
        }
    } /* }}} */

    function createFolder($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $settings = $this->container->get('config');
        $logger = $this->container->get('logger');
        $fulltextservice = $this->container->get('fulltextservice');
        $notifier = $this->container->get('notifier');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No parent folder given', 'data'=>''))->withStatus(400);
            return;
        }
        $parent = $dms->getFolder($args['id']);
        if($parent) {
            if($parent->getAccessMode($userobj, 'addFolder') >= M_READWRITE) {
                $params = $request->getParsedBody();
                if(!empty($params['name'])) {
                    $comment = isset($params['comment']) ? $params['comment'] : '';
                    if(isset($params['sequence'])) {
                        $sequence = str_replace(',', '.', $params["sequence"]);
                        if (!is_numeric($sequence))
                            return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText("invalid_sequence"), 'data'=>''))->withStatus(400);
                    } else {
                        $dd = $parent->getSubFolders('s');
                        if(count($dd) > 1)
                            $sequence = $dd[count($dd)-1]->getSequence() + 1;
                        else
                            $sequence = 1.0;
                    }
                    $newattrs = array();
                    if(!empty($params['attributes'])) {
                        foreach($params['attributes'] as $attrname=>$attrvalue) {
                            if((is_int($attrname) || ctype_digit($attrname)) && ((int) $attrname) > 0)
                                $attrdef = $dms->getAttributeDefinition((int) $attrname);
                            else
                                $attrdef = $dms->getAttributeDefinitionByName($attrname);
                            if($attrdef) {
                                $newattrs[$attrdef->getID()] = $attrvalue;
                            }
                        }
                    }
                    /* Check if name already exists in the folder */
                    if(!$settings->_enableDuplicateSubFolderNames) {
                        if($parent->hasSubFolderByName($params['name'])) {
                            return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText("subfolder_duplicate_name"), 'data'=>''))->withStatus(409);
                        }
                    }

                    $controller = Controller::factory('AddSubFolder');
                    $controller->setParam('dms', $dms);
                    $controller->setParam('user', $userobj);
                    $controller->setParam('fulltextservice', $fulltextservice);
                    $controller->setParam('folder', $parent);
                    $controller->setParam('name', $params['name']);
                    $controller->setParam('comment', $comment);
                    $controller->setParam('sequence', $sequence);
                    $controller->setParam('attributes', $newattrs);
                    $controller->setParam('notificationgroups', []);
                    $controller->setParam('notificationusers', []);
                    if($folder = $controller()) {
    $rec = $this->__getFolderData($folder);
                        $logger->log("Creating folder '".$folder->getName()."' (".$folder->getId().") successful", PEAR_LOG_INFO);
                        if($notifier) {
                            $notifier->sendNewFolderMail($folder, $userobj);
                        }
                        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$rec))->withStatus(201);
                    } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not create folder', 'data'=>''))->withStatus(500);
                    }
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Missing folder name', 'data'=>''))->withStatus(400);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on destination folder', 'data'=>''))->withStatus(403);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find parent folder', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function moveFolder($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No source folder given', 'data'=>''))->withStatus(400);
        }

        if(!ctype_digit($args['folderid']) || $args['folderid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No destination folder given', 'data'=>''))->withStatus(400);
        }

        $mfolder = $dms->getFolder($args['id']);
        if($mfolder) {
            if ($mfolder->getAccessMode($userobj, 'moveFolder') >= M_READ) {
                if($folder = $dms->getFolder($args['folderid'])) {
                    if($folder->getAccessMode($userobj, 'moveFolder') >= M_READWRITE) {
                        if($mfolder->setParent($folder)) {
                            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
                        } else {
                            return $this->renderer->json($response, array('success'=>false, 'message'=>'Error moving folder', 'data'=>''))->withStatus(500);
                        }
                    } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on destination folder', 'data'=>''))->withStatus(403);
                    }
                } else {
                    if($folder === null)
                        $status = 404;
                    else
                        $status = 500;
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No destination folder', 'data'=>''))->withStatus($status);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($mfolder === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No folder', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function deleteFolder($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'id is 0', 'data'=>''))->withStatus(400);
        }
        $mfolder = $dms->getFolder($args['id']);
        if($mfolder) {
            if ($mfolder->getAccessMode($userobj, 'removeFolder') >= M_READWRITE) {
                if($mfolder->remove()) {
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Error deleting folder', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($mfolder === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No folder', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function uploadDocument($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $settings = $this->container->get('config');
        $notifier = $this->container->get('notifier');
        $fulltextservice = $this->container->get('fulltextservice');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No parent folder id given', 'data'=>''))->withStatus(400);
        }

        if($settings->_quota > 0) {
            $remain = checkQuota($userobj);
            if ($remain < 0) {
    return $this->renderer->json($response, array('success'=>false, 'message'=>'Quota exceeded', 'data'=>''))->withStatus(400);
            }
        }

        $mfolder = $dms->getFolder($args['id']);
        if($mfolder) {
            $uploadedFiles = $request->getUploadedFiles();
            if ($mfolder->getAccessMode($userobj, 'addDocument') >= M_READWRITE) {
                $params = $request->getParsedBody();
                $docname = isset($params['name']) ? $params['name'] : '';
                $keywords = isset($params['keywords']) ? $params['keywords'] : '';
                $comment = isset($params['comment']) ? $params['comment'] : '';
                if(isset($params['sequence'])) {
                    $sequence = str_replace(',', '.', $params["sequence"]);
                    if (!is_numeric($sequence))
                        return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText("invalid_sequence"), 'data'=>''))->withStatus(400);
                } else {
                    $dd = $mfolder->getDocuments('s');
                    if(count($dd) > 1)
                        $sequence = $dd[count($dd)-1]->getSequence() + 1;
                    else
                        $sequence = 1.0;
                }
                if(isset($params['expdate'])) {
                    $tmp = explode('-', $params["expdate"]);
                    if(count($tmp) != 3)
                        return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText('malformed_expiration_date'), 'data'=>''))->withStatus(400);
                    $expires = mktime(0,0,0, $tmp[1], $tmp[2], $tmp[0]);
                } else
                    $expires = 0;
                $version_comment = isset($params['version_comment']) ? $params['version_comment'] : '';
                $reqversion = (isset($params['reqversion']) && (int) $params['reqversion'] > 1) ? (int) $params['reqversion'] : 1;
                $origfilename = isset($params['origfilename']) ? $params['origfilename'] : null;
                $categories = isset($params["categories"]) ? $params["categories"] : array();
                $cats = array();
                foreach($categories as $catid) {
                    if($cat = $dms->getDocumentCategory($catid))
                        $cats[] = $cat;
                }
                $owner = null;
                if($userobj->isAdmin() && isset($params["owner"]) && ctype_digit($params['owner'])) {
                    $owner = $dms->getUser($params["owner"]);
                }
                $attributes = isset($params["attributes"]) ? $params["attributes"] : array();
                foreach($attributes as $attrdefid=>$attribute) {
                    if((is_int($attrdefid) || ctype_digit($attrdefid)) && ((int) $attrdefid) > 0)
                        $attrdef = $dms->getAttributeDefinition((int) $attrdefid);
                    else
                        $attrdef = $dms->getAttributeDefinitionByName($attrdefid);
                    if($attrdef) {
                        if($attribute) {
                            if(!$attrdef->validate($attribute)) {
                                return $this->renderer->json($response, array('success'=>false, 'message'=>getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute), 'data'=>''))->withStatus(400);
                            }
                        } elseif($attrdef->getMinValues() > 0) {
                            return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText("attr_min_values", array("attrname"=>$attrdef->getName())), 'data'=>''))->withStatus(400);
                        }
                    }
                }
                if (count($uploadedFiles) == 0) {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No file detected', 'data'=>''))->withStatus(400);
                }
                $file_info = array_pop($uploadedFiles);
                if ($origfilename == null)
                    $origfilename = $file_info->getClientFilename();
                if (trim($docname) == '')
                    $docname = $origfilename;
                /* Check if name already exists in the folder */
                if(!$settings->_enableDuplicateDocNames) {
                    if($mfolder->hasDocumentByName($docname)) {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText("document_duplicate_name"), 'data'=>''))->withStatus(409);
                    }
                }
                // Get the list of reviewers and approvers for this document.
                $reviewers = array();
                $approvers = array();
                $reviewers["i"] = array();
                $reviewers["g"] = array();
                $approvers["i"] = array();
                $approvers["g"] = array();
                $workflow = null;
                if($settings->_workflowMode == 'traditional' || $settings->_workflowMode == 'traditional_only_approval') {
                    // add mandatory reviewers/approvers
                    if($settings->_workflowMode == 'traditional') {
                        $mreviewers = getMandatoryReviewers($mfolder, null, $userobj);
                        if($mreviewers['i'])
                            $reviewers['i'] = array_merge($reviewers['i'], $mreviewers['i']);
                        if($mreviewers['g'])
                            $reviewers['g'] = array_merge($reviewers['g'], $mreviewers['g']);
                    }
                    $mapprovers = getMandatoryApprovers($mfolder, null, $userobj);
                    if($mapprovers['i'])
                        $approvers['i'] = array_merge($approvers['i'], $mapprovers['i']);
                    if($mapprovers['g'])
                        $approvers['g'] = array_merge($approvers['g'], $mapprovers['g']);
                } elseif($settings->_workflowMode == 'advanced') {
                    if($workflows = $userobj->getMandatoryWorkflows()) {
                        $workflow = array_shift($workflows);
                    }
                }
                $temp = tempnam(sys_get_temp_dir(), 'FOO');
                file_put_contents($temp, (string) $file_info->getStream());

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $userfiletype = finfo_file($finfo, $temp);
                $fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
                finfo_close($finfo);
                $attributes_version = [];
                $notusers = [];
                $notgroups = [];
                $controller = Controller::factory('AddDocument');
                $controller->setParam('documentsource', 'restapi');
                $controller->setParam('documentsourcedetails', null);
                $controller->setParam('dms', $dms);
                $controller->setParam('user', $userobj);
                $controller->setParam('folder', $mfolder);
                $controller->setParam('fulltextservice', $fulltextservice);
                $controller->setParam('name', $docname);
                $controller->setParam('comment', $comment);
                $controller->setParam('expires', $expires);
                $controller->setParam('keywords', $keywords);
                $controller->setParam('categories', $cats);
                $controller->setParam('owner', $owner ? $owner : $userobj);
                $controller->setParam('userfiletmp', $temp);
                $controller->setParam('userfilename', $origfilename ? $origfilename : basename($temp));
                $controller->setParam('filetype', $fileType);
                $controller->setParam('userfiletype', $userfiletype);
                $controller->setParam('sequence', $sequence);
                $controller->setParam('reviewers', $reviewers);
                $controller->setParam('approvers', $approvers);
                $controller->setParam('reqversion', $reqversion);
                $controller->setParam('versioncomment', $version_comment);
                $controller->setParam('attributes', $attributes);
                $controller->setParam('attributesversion', $attributes_version);
                $controller->setParam('workflow', $workflow);
                $controller->setParam('notificationgroups', $notgroups);
                $controller->setParam('notificationusers', $notusers);
                $controller->setParam('maxsizeforfulltext', $settings->_maxSizeForFullText);
                $controller->setParam('defaultaccessdocs', $settings->_defaultAccessDocs);

                if(!($document = $controller())) {
                    $err = $controller->getErrorMsg();
                    if(is_string($err))
                        $errmsg = getMLText($err);
                    elseif(is_array($err)) {
                        $errmsg = getMLText($err[0], $err[1]);
                    } else {
                        $errmsg = $err;
                    }
                    unlink($temp);
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Upload failed', 'data'=>''))->withStatus(500);
                } else {
                    if($controller->hasHook('cleanUpDocument')) {
                        $controller->callHook('cleanUpDocument', $document, ['ѕource'=>'restapi', 'type'=>$userfiletype, 'name'=>$origfilename]);
                    }
                    // Send notification to subscribers of folder.
                    if($notifier) {
                        $notifier->sendNewDocumentMail($document, $userobj);
                    }
                    unlink($temp);
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'Upload succeded', 'data'=>$this->__getLatestVersionData($document->getLatestContent())))->withStatus(201);
                }
    /*
                $res = $mfolder->addDocument($docname, $comment, $expires, $owner ? $owner : $userobj, $keywords, $cats, $temp, $origfilename ? $origfilename : basename($temp), $fileType, $userfiletype, $sequence, array(), array(), $reqversion, $version_comment, $attributes);
                unlink($temp);
                if($res) {
                    $doc = $res[0];
                    if($notifier) {
                        $notifier->sendNewDocumentMail($doc, $userobj);
                    }
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'Upload succeded', 'data'=>$this->__getLatestVersionData($doc->getLatestContent())))->withStatus(201);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Upload failed', 'data'=>''))->withStatus(500);
                }
     */
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($mfolder === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No folder', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function updateDocument($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $settings = $this->container->get('config');
        $notifier = $this->container->get('notifier');
        $fulltextservice = $this->container->get('fulltextservice');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document id given', 'data'=>''))->withStatus(400);
        }

        if($settings->_quota > 0) {
            $remain = checkQuota($userobj);
            if ($remain < 0) {
    return $this->renderer->json($response, array('success'=>false, 'message'=>'Quota exceeded', 'data'=>''))->withStatus(400);
            }
        }

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj, 'updateDocument') < M_READWRITE) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }

            $params = $request->getParsedBody();
            $origfilename = isset($params['origfilename']) ? $params['origfilename'] : null;
            $comment = isset($params['comment']) ? $params['comment'] : null;
            $attributes = isset($params["attributes"]) ? $params["attributes"] : array();
            foreach($attributes as $attrdefid=>$attribute) {
                if((is_int($attrdefid) || ctype_digit($attrdefid)) && ((int) $attrdefid) > 0)
                    $attrdef = $dms->getAttributeDefinition((int) $attrdefid);
                else
                    $attrdef = $dms->getAttributeDefinitionByName($attrdefid);
                if($attrdef) {
                    if($attribute) {
                        if(!$attrdef->validate($attribute)) {
                            return $this->renderer->json($response, array('success'=>false, 'message'=>getAttributeValidationText($attrdef->getValidationError(), $attrdef->getName(), $attribute), 'data'=>''))->withStatus(400);
                        }
                    } elseif($attrdef->getMinValues() > 0) {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText("attr_min_values", array("attrname"=>$attrdef->getName())), 'data'=>''))->withStatus(400);
                    }
                }
            }
            $uploadedFiles = $request->getUploadedFiles();
            if (count($uploadedFiles) == 0) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No file detected', 'data'=>''))->withStatus(400);
            }
            $file_info = array_pop($uploadedFiles);
            if ($origfilename == null)
                $origfilename = $file_info->getClientFilename();
            $temp = tempnam(sys_get_temp_dir(), 'FOO');
            file_put_contents($temp, (string) $file_info->getStream());

            /* Check if the uploaded file is identical to last version */
            $lc = $document->getLatestContent();
            if($lc->getChecksum() == SeedDMS_Core_File::checksum($temp)) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Uploaded file identical to last version', 'data'=>''))->withStatus(400);
            }

            if($document->isLocked()) {
                $lockingUser = $document->getLockingUser();
                if(($lockingUser->getID() != $userobj->getID()) && ($document->getAccessMode($userobj) != M_ALL)) {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Document is locked', 'data'=>''))->withStatus(400);
                }
                else $document->setLocked(false);
            }

            $folder = $document->getFolder();

            // Get the list of reviewers and approvers for this document.
            $reviewers = array();
            $approvers = array();
            $reviewers["i"] = array();
            $reviewers["g"] = array();
            $approvers["i"] = array();
            $approvers["g"] = array();
            $workflow = null;
            if($settings->_workflowMode == 'traditional' || $settings->_workflowMode == 'traditional_only_approval') {
                // add mandatory reviewers/approvers
                if($settings->_workflowMode == 'traditional') {
                    $mreviewers = getMandatoryReviewers($folder, null, $userobj);
                    if($mreviewers['i'])
                        $reviewers['i'] = array_merge($reviewers['i'], $mreviewers['i']);
                    if($mreviewers['g'])
                        $reviewers['g'] = array_merge($reviewers['g'], $mreviewers['g']);
                }
                $mapprovers = getMandatoryApprovers($folder, null, $userobj);
                if($mapprovers['i'])
                    $approvers['i'] = array_merge($approvers['i'], $mapprovers['i']);
                if($mapprovers['g'])
                    $approvers['g'] = array_merge($approvers['g'], $mapprovers['g']);
            } elseif($settings->_workflowMode == 'advanced') {
                if($workflows = $userobj->getMandatoryWorkflows()) {
                    $workflow = array_shift($workflows);
                }
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $userfiletype = finfo_file($finfo, $temp);
            $fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
            finfo_close($finfo);

            $controller = Controller::factory('UpdateDocument');
            $controller->setParam('documentsource', 'restapi');
            $controller->setParam('documentsourcedetails', null);
            $controller->setParam('dms', $dms);
            $controller->setParam('user', $userobj);
            $controller->setParam('folder', $folder);
            $controller->setParam('document', $document);
            $controller->setParam('fulltextservice', $fulltextservice);
            $controller->setParam('comment', $comment);
            $controller->setParam('userfiletmp', $temp);
            $controller->setParam('userfilename', $origfilename);
            $controller->setParam('filetype', $fileType);
            $controller->setParam('userfiletype', $userfiletype);
            $controller->setParam('reviewers', $reviewers);
            $controller->setParam('approvers', $approvers);
            $controller->setParam('attributes', $attributes);
            $controller->setParam('workflow', $workflow);
            $controller->setParam('maxsizeforfulltext', $settings->_maxSizeForFullText);

            if(!$content = $controller()) {
                unlink($temp);
                $err = $controller->getErrorMsg();
                if(is_string($err))
                    $errmsg = getMLText($err);
                elseif(is_array($err)) {
                    $errmsg = getMLText($err[0], $err[1]);
                } else {
                    $errmsg = $err;
                }
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Upload failed: '.$errmsg, 'data'=>''))->withStatus(500);
            } else {
                unlink($temp);
                if($controller->hasHook('cleanUpDocument')) {
                    $controller->callHook('cleanUpDocument', $document, ['ѕource'=>'restapi', 'type'=>$userfiletype, 'name'=>$origfilename]);
                }
                // Send notification to subscribers.
                if($notifier) {
                    $notifier->sendNewDocumentVersionMail($document, $userobj);

                    //$notifier->sendChangedExpiryMail($document, $user, $oldexpires);
                }

                $rec = array('id'=>(int)$document->getId(), 'name'=>$document->getName(), 'version'=>$document->getLatestContent()->getVersion());
                return $this->renderer->json($response, array('success'=>true, 'message'=>'Upload succeded', 'data'=>$rec))->withStatus(200);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    /**
     * Old upload method which uses put instead of post
     */
    function uploadDocumentPut($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $settings = $this->container->get('config');
        $notifier = $this->container->get('notifier');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document id given', 'data'=>''))->withStatus(400);
        }

        if($settings->_quota > 0) {
            $remain = checkQuota($userobj);
            if ($remain < 0) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Quota exceeded', 'data'=>''))->withStatus(400);
            }
        }

        $mfolder = $dms->getFolder($args['id']);
        if($mfolder) {
            if ($mfolder->getAccessMode($userobj, 'addDocument') >= M_READWRITE) {
                $params = $request->getQueryParams();
                $docname = isset($params['name']) ? $params['name'] : '';
                $comment = isset($params['comment']) ? $params['comment'] : '';
                $keywords = isset($params['keywords']) ? $params['keywords'] : '';
                $origfilename = isset($params['origfilename']) ? $params['origfilename'] : null;
                $content = $request->getBody();
                $temp = tempnam(sys_get_temp_dir(), 'lajflk');
                $handle = fopen($temp, "w");
                fwrite($handle, $content);
                fclose($handle);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $userfiletype = finfo_file($finfo, $temp);
                $fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
                finfo_close($finfo);
                /* Check if name already exists in the folder */
                if(!$settings->_enableDuplicateDocNames) {
                    if($mfolder->hasDocumentByName($docname)) {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>getMLText("document_duplicate_name"), 'data'=>''))->withStatus(409);
                    }
                }
                $res = $mfolder->addDocument($docname, $comment, 0, $userobj, '', array(), $temp, $origfilename ? $origfilename : basename($temp), $fileType, $userfiletype, 0);
                unlink($temp);
                if($res) {
                    $doc = $res[0];
                    if($notifier) {
                        $notifier->sendNewDocumentMail($doc, $userobj);
                    }
                    $rec = array('id'=>(int)$doc->getId(), 'name'=>$doc->getName());
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'Upload succeded', 'data'=>$rec))->withStatus(200);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Upload failed', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($mfolder === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No folder', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function uploadDocumentFile($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document id given', 'data'=>''))->withStatus(400);
        }
        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj, 'addDocumentFile') >= M_READWRITE) {
                $uploadedFiles = $request->getUploadedFiles();
                $params = $request->getParsedBody();
                $docname = $params['name'];
                $keywords = isset($params['keywords']) ? $params['keywords'] : '';
                $origfilename = $params['origfilename'];
                $comment = isset($params['comment']) ? $params['comment'] : '';
                $version = empty($params['version']) ? 0 : $params['version'];
                $public = empty($params['public']) ? 'false' : $params['public'];
                if (count($uploadedFiles) == 0) {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No file detected', 'data'=>''))->withStatus(400);
                }
                $file_info = array_pop($uploadedFiles);
                if ($origfilename == null)
                    $origfilename = $file_info->getClientFilename();
                if (trim($docname) == '')
                    $docname = $origfilename;
                $temp = tempnam(sys_get_temp_dir(), 'FOO');
                file_put_contents($temp, (string) $file_info->getStream());
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $userfiletype = finfo_file($finfo, $temp);
                $fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
                finfo_close($finfo);
                $res = $document->addDocumentFile($docname, $comment, $userobj, $temp,
                            $origfilename ? $origfilename : utf8_basename($temp),
                            $fileType, $userfiletype, $version, $public);
                unlink($temp);
                if($res) {
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'Upload succeded', 'data'=>$res))->withStatus(201);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Upload failed', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function addDocumentLink($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No source document given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['documentid']) || $args['documentid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No target document given', 'data'=>''))->withStatus(400);
            return;
        }
        $sourcedoc = $dms->getDocument($args['id']);
        $targetdoc = $dms->getDocument($args['documentid']);
        if($sourcedoc && $targetdoc) {
            if($sourcedoc->getAccessMode($userobj, 'addDocumentLink') >= M_READ) {
                $params = $request->getParsedBody();
                $public = !isset($params['public']) ? true : false;
                if ($sourcedoc->addDocumentLink($targetdoc->getId(), $userobj->getID(), $public)){
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(201);
                } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not create document link', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on source document', 'data'=>''))->withStatus(403);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find source or target document', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function getDocument($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $lc = $document->getLatestContent();
                if($lc) {
                    $data = $this->__getLatestVersionData($lc);
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function deleteDocument($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj, 'deleteDocument') >= M_READWRITE) {
                if($document->remove()) {
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Error removing document', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function moveDocument($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj, 'moveDocument') >= M_READ) {
                if($folder = $dms->getFolder($args['folderid'])) {
                    if($folder->getAccessMode($userobj, 'moveDocument') >= M_READWRITE) {
                        if($document->setFolder($folder)) {
                            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
                        } else {
                            return $this->renderer->json($response, array('success'=>false, 'message'=>'Error moving document', 'data'=>''))->withStatus(500);
                        }
                    } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on destination folder', 'data'=>''))->withStatus(403);
                    }
                } else {
                  if($folder === null)
                      $status=404;
                  else
                      $status=500;
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No destination folder', 'data'=>''))->withStatus($status);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentContent($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $lc = $document->getLatestContent();
                if($lc) {
                    if (pathinfo($document->getName(), PATHINFO_EXTENSION) == $lc->getFileType())
                        $filename = $document->getName();
                    else
                        $filename = $document->getName().$lc->getFileType();

                    $file = $dms->contentDir . $lc->getPath();
                    if(!($fh = @fopen($file, 'rb'))) {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>''))->withStatus(500);
                    }
                    $stream = new \Slim\Psr7\Stream($fh); // create a stream instance for the response body

                    return $response->withHeader('Content-Type', $lc->getMimeType())
                        ->withHeader('Content-Description', 'File Transfer')
                        ->withHeader('Content-Transfer-Encoding', 'binary')
                        ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                        ->withAddedHeader('Content-Length', filesize($dms->contentDir . $lc->getPath()))
                        ->withHeader('Expires', '0')
                        ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                        ->withHeader('Pragma', 'no-cache')
                        ->withBody($stream);

                  sendFile($dms->contentDir . $lc->getPath());
                } else {
                  return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }

    } /* }}} */

    function getDocumentVersions($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $recs = array();
                $lcs = $document->getContent();
                foreach($lcs as $lc) {
                    $recs[] = $this->__getDocumentVersionData($lc);
                }
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentVersion($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!ctype_digit($args['id']) || !ctype_digit($args['version'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $lc = $document->getContentByVersion($args['version']);
                if($lc) {
                    if (pathinfo($document->getName(), PATHINFO_EXTENSION) == $lc->getFileType())
                        $filename = $document->getName();
                    else
                        $filename = $document->getName().$lc->getFileType();

                    $file = $dms->contentDir . $lc->getPath();
                    if(!($fh = @fopen($file, 'rb'))) {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>''))->withStatus(500);
                    }
                    $stream = new \Slim\Psr7\Stream($fh); // create a stream instance for the response body

                    return $response->withHeader('Content-Type', $lc->getMimeType())
                        ->withHeader('Content-Description', 'File Transfer')
                        ->withHeader('Content-Transfer-Encoding', 'binary')
                        ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                        ->withHeader('Content-Length', filesize($dms->contentDir . $lc->getPath()))
                        ->withHeader('Expires', '0')
                        ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                        ->withHeader('Pragma', 'no-cache')
                        ->withBody($stream);

                    sendFile($dms->contentDir . $lc->getPath());
                } else {
                  return $this->renderer->json($response, array('success'=>false, 'message'=>'No such version', 'data'=>''))->withStatus(404);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function updateDocumentVersion($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $lc = $document->getContentByVersion($args['version']);
                if($lc) {
                  $params = $request->getParsedBody();
                  if (isset($params['comment'])) {
                    $lc->setComment($params['comment']);
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
                  }
                } else {
                  return $this->renderer->json($response, array('success'=>false, 'message'=>'No such version', 'data'=>''))->withStatus(404);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentFiles($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);

        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $recs = array();
                $files = $document->getDocumentFiles();
                foreach($files as $file) {
                    $recs[] = $this->__getDocumentFileData($file);
                }
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentFile($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!ctype_digit($args['id']) || !ctype_digit($args['fileid'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);

        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $lc = $document->getDocumentFile($args['fileid']);
                if($lc) {
                    $file = $dms->contentDir . $lc->getPath();
                    if(!($fh = @fopen($file, 'rb'))) {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>''))->withStatus(500);
                    }
                    $stream = new \Slim\Psr7\Stream($fh); // create a stream instance for the response body

                    return $response->withHeader('Content-Type', $lc->getMimeType())
                          ->withHeader('Content-Description', 'File Transfer')
                          ->withHeader('Content-Transfer-Encoding', 'binary')
                          ->withHeader('Content-Disposition', 'attachment; filename="' . $document->getName() . $lc->getFileType() . '"')
                          ->withHeader('Content-Length', filesize($dms->contentDir . $lc->getPath()))
                          ->withHeader('Expires', '0')
                          ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                          ->withHeader('Pragma', 'no-cache')
                          ->withBody($stream);

                    sendFile($dms->contentDir . $lc->getPath());
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No document file', 'data'=>''))->withStatus(404);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentLinks($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);

        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $recs = array();
                $links = $document->getDocumentLinks();
                foreach($links as $link) {
                    $recs[] = $this->__getDocumentLinkData($link);
                }
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentAttributes($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                $attributes = $this->__getAttributesData($document);
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$attributes))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentContentAttributes($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $document = $dms->getDocument($args['id']);
        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {

                $version = $document->getContentByVersion($args['version']);
                if($version) {
                    if($version->getAccessMode($userobj) >= M_READ) {
                        $attributes = $this->__getAttributesData($version);
                        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$attributes))->withStatus(200);
                    } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on version', 'data'=>''))->withStatus(403);
                    }
                } else {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'No version', 'data'=>''))->withStatus(404);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function getDocumentPreview($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $settings = $this->container->get('config');
        $conversionmgr = $this->container->get('conversionmgr');

        if(!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);

        if($document) {
            if ($document->getAccessMode($userobj) >= M_READ) {
                if($args['version'])
                    $object = $document->getContentByVersion($args['version']);
                else
                    $object = $document->getLatestContent();
                if(!$object)
                    exit;

                if(!empty($args['width']))
                    $previewer = new SeedDMS_Preview_Previewer($settings->_cacheDir, $args['width']);
                else
                    $previewer = new SeedDMS_Preview_Previewer($settings->_cacheDir);
                if($conversionmgr)
                    $previewer->setConversionMgr($conversionmgr);
                else
                    $previewer->setConverters($settings->_converters['preview']);
                if(!$previewer->hasPreview($object))
                    $previewer->createPreview($object);

                $file = $previewer->getFileName($object, $args['width']).".png";
                if(!($fh = @fopen($file, 'rb'))) {
                  return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>''))->withStatus(500);
                }
                $stream = new \Slim\Psr7\Stream($fh); // create a stream instance for the response body

                return $response->withHeader('Content-Type', 'image/png')
                      ->withHeader('Content-Description', 'File Transfer')
                      ->withHeader('Content-Transfer-Encoding', 'binary')
                      ->withHeader('Content-Disposition', 'attachment; filename="preview-' . $document->getID() . "-" . $object->getVersion() . "-" . $width . ".png" . '"')
                      ->withHeader('Content-Length', $previewer->getFilesize($object))
                      ->withBody($stream);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function addDocumentCategory($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['catid']) || $args['catid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No category given', 'data'=>''))->withStatus(400);
            return;
        }
        $cat = $dms->getDocumentCategory($args['catid']);
        $doc = $dms->getDocument($args['id']);
        if($doc && $cat) {
            if($doc->getAccessMode($userobj, 'addDocumentCategory') >= M_READ) {
                if ($doc->addCategories([$cat])){
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(201);
                } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not add document category', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on document', 'data'=>''))->withStatus(403);
            }
        } else {
            if(!$doc)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such document', 'data'=>''))->withStatus(404);
            if(!$cat)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such category', 'data'=>''))->withStatus(404);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find category or document', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function removeDocumentCategory($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!ctype_digit($args['id']) || !ctype_digit($args['catid'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);
        if(!$document)
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such document', 'data'=>''))->withStatus(404);
        $category = $dms->getDocumentCategory($args['catid']);
        if(!$category)
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such category', 'data'=>''))->withStatus(404);

        if ($document->getAccessMode($userobj, 'removeDocumentCategory') >= M_READWRITE) {
            $ret = $document->removeCategories(array($category));
            if ($ret)
                return $this->renderer->json($response, array('success'=>true, 'message'=>'Deleted category successfully.', 'data'=>''))->withStatus(200);
            else
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
        }
    } /* }}} */

    function removeDocumentCategories($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if(!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $document = $dms->getDocument($args['id']);

        if($document) {
            if ($document->getAccessMode($userobj, 'removeDocumentCategory') >= M_READWRITE) {
                if($document->setCategories(array()))
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'Deleted categories successfully.', 'data'=>''))->withStatus(200);
                else
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>''))->withStatus(500);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access', 'data'=>''))->withStatus(403);
            }
        } else {
            if($document === null)
                $status=404;
            else
                $status=500;
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such document', 'data'=>''))->withStatus($status);
        }
    } /* }}} */

    function setDocumentOwner($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['userid']) || $args['userid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No user given', 'data'=>''))->withStatus(400);
            return;
        }
        $owner = $dms->getUser($args['userid']);
        $doc = $dms->getDocument($args['id']);
        if($doc && $owner) {
            if($doc->getAccessMode($userobj, 'setDocumentOwner') > M_READ) {
                if ($doc->setOwner($owner)){
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(201);
                } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not set owner of document', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on document', 'data'=>''))->withStatus(403);
            }
        } else {
            if(!$doc)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such document', 'data'=>''))->withStatus(404);
            if(!$owner)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such user', 'data'=>''))->withStatus(404);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find user or document', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function setDocumentAttribute($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $logger = $this->container->get('logger');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
            return;
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['attrdefid']) || $args['attrdefid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No attribute definition id given', 'data'=>''))->withStatus(400);
            return;
        }
        $attrdef = $dms->getAttributeDefinition($args['attrdefid']);
        $doc = $dms->getDocument($args['id']);
        if($doc && $attrdef) {
            if($attrdef->getObjType() !== SeedDMS_Core_AttributeDefinition::objtype_document) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Attribute definition "'.$attrdef->getName().'" not suitable for documents', 'data'=>''))->withStatus(409);
            }

            $params = $request->getParsedBody();
            if(!isset($params['value'])) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Attribute value not set', 'data'=>''))->withStatus(400);
            }
            $new = $doc->getAttributeValue($attrdef) ? true : false;
            if(!$attrdef->validate($params['value'], $doc, $new)) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Validation of attribute value failed: '.$attrdef->getValidationError(), 'data'=>''))->withStatus(400);
            }
            if($doc->getAccessMode($userobj, 'setDocumentAttribute') > M_READ) {
                if ($doc->setAttributeValue($attrdef, $params['value'])) {
                    $logger->log("Setting attribute '".$attrdef->getName()."' (".$attrdef->getId().") to '".$params['value']."' successful", PEAR_LOG_INFO);
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(201);
                } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not set attribute value of document', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on document', 'data'=>''))->withStatus(403);
            }
        } else {
            if(!$doc)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such document', 'data'=>''))->withStatus(404);
            if(!$attrdef)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such attr definition', 'data'=>''))->withStatus(404);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find user or document', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function setDocumentContentAttribute($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $logger = $this->container->get('logger');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
            return;
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No document given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['version']) || $args['version'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No version number given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['attrdefid']) || $args['attrdefid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No attribute definition id given', 'data'=>''))->withStatus(400);
            return;
        }
        $attrdef = $dms->getAttributeDefinition($args['attrdefid']);
        if($doc = $dms->getDocument($args['id']))
            $version = $doc->getContentByVersion($args['version']);
        if($doc && $attrdef && $version) {
            if($attrdef->getObjType() !== SeedDMS_Core_AttributeDefinition::objtype_documentcontent) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Attribute definition "'.$attrdef->getName().'" not suitable for document versions', 'data'=>''))->withStatus(409);
            }

            $params = $request->getParsedBody();
            if(!isset($params['value'])) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Attribute value not set', 'data'=>''))->withStatus(400);
            }
            $new = $version->getAttributeValue($attrdef) ? true : false;
            if(!$attrdef->validate($params['value'], $version, $new)) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Validation of attribute value failed: '.$attrdef->getValidationError(), 'data'=>''))->withStatus(400);
            }
            if($doc->getAccessMode($userobj, 'setDocumentContentAttribute') > M_READ) {
                if ($version->setAttributeValue($attrdef, $params['value'])) {
                    $logger->log("Setting attribute '".$attrdef->getName()."' (".$attrdef->getId().") to '".$params['value']."' successful", PEAR_LOG_INFO);
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(201);
                } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not set attribute value of document content', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on document', 'data'=>''))->withStatus(403);
            }
        } else {
            if(!$doc)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such document', 'data'=>''))->withStatus(404);
            if(!$version)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such version', 'data'=>''))->withStatus(404);
            if(!$attrdef)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such attr definition', 'data'=>''))->withStatus(404);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find user or document', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function setFolderAttribute($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $logger = $this->container->get('logger');

        if(!$userobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
            return;
        }

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No folder given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['attrdefid']) || $args['attrdefid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No attribute definition id given', 'data'=>''))->withStatus(400);
            return;
        }
        $attrdef = $dms->getAttributeDefinition($args['attrdefid']);
        $obj = $dms->getFolder($args['id']);
        if($obj && $attrdef) {
            if($attrdef->getObjType() !== SeedDMS_Core_AttributeDefinition::objtype_folder) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Attribute definition "'.$attrdef->getName().'" not suitable for folders', 'data'=>''))->withStatus(409);
            }

            $params = $request->getParsedBody();
            if(!isset($params['value'])) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Attribute value not set', 'data'=>''.$request->getHeader('Content-Type')[0]))->withStatus(400);
            }
            if(strlen($params['value'])) {
                $new = $obj->getAttributeValue($attrdef) ? true : false;
                if(!$attrdef->validate($params['value'], $obj, $new)) {
                    return $this->renderer->json($response, array('success'=>false, 'message'=>'Validation of attribute value failed: '.$attrdef->getValidationError(), 'data'=>''))->withStatus(400);
                }
            }
            if($obj->getAccessMode($userobj, 'setFolderAttribute') > M_READ) {
                if ($obj->setAttributeValue($attrdef, $params['value'])) {
                    $logger->log("Setting attribute '".$attrdef->getName()."' (".$attrdef->getId().") to '".$params['value']."' successful", PEAR_LOG_INFO);
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(201);
                } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not set attribute value of folder', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on folder', 'data'=>''))->withStatus(403);
            }
        } else {
            if(!$obj)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
            if(!$attrdef)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such attr definition', 'data'=>''))->withStatus(404);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find user or folder', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function getAccount($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if($userobj) {
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getUserData($userobj)))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Not logged in', 'data'=>''))->withStatus(403);
        }
    } /* }}} */

    /**
     * Search for documents in the database
     *
     * If the request parameter 'mode' is set to 'typeahead', it will
     * return a list of words only.
     */
    function doSearch($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $params = $request->getQueryParams();
        $querystr = $params['query'];
        $mode = isset($params['mode']) ? $params['mode'] : '';
        if(!isset($params['limit']) || !$limit = $params['limit'])
            $limit = 5;
        if(!isset($params['offset']) || !$offset = $params['offset'])
            $offset = 0;
        if(!isset($params['searchin']) || !$searchin = explode(",",$params['searchin']))
            $searchin = array();
        if(!isset($params['objects']) || !$objects = $params['objects'])
            $objects = 0x3;
        $sparams = array(
            'query'=>$querystr,
            'limit'=>$limit,
            'offset'=>$offset,
            'logicalmode'=>'AND',
            'searchin'=>$searchin,
            'mode'=>$objects,
//            'creationstartdate'=>array('hour'=>1, 'minute'=>0, 'second'=>0, 'year'=>date('Y')-1, 'month'=>date('m'), 'day'=>date('d')),
        );
        $resArr = $dms->search($sparams);
        if($resArr === false) {
            return $this->renderer->json($response, array())->withStatus(200);
        }
        $entries = array();
        $count = 0;
        if($resArr['folders']) {
            foreach ($resArr['folders'] as $entry) {
                if ($entry->getAccessMode($userobj) >= M_READ) {
                    $entries[] = $entry;
                    $count++;
                }
                if($count >= $limit)
                    break;
            }
        }
        $count = 0;
        if($resArr['docs']) {
            foreach ($resArr['docs'] as $entry) {
                $lc = $entry->getLatestContent();
                if ($entry->getAccessMode($userobj) >= M_READ && $lc) {
                    $entries[] = $entry;
                    $count++;
                }
                if($count >= $limit)
                    break;
            }
        }

        switch($mode) {
            case 'typeahead';
                $recs = array();
                foreach ($entries as $entry) {
                /* Passing anything back but a string does not work, because
                 * the process function of bootstrap.typeahead needs an array of
                 * strings.
                 *
                 * As a quick solution to distingish folders from documents, the
                 * name will be preceeded by a 'F' or 'D'

                    $tmp = array();
                    if(get_class($entry) == 'SeedDMS_Core_Document') {
                        $tmp['type'] = 'folder';
                    } else {
                        $tmp['type'] = 'document';
                    }
                    $tmp['id'] = $entry->getID();
                    $tmp['name'] = $entry->getName();
                    $tmp['comment'] = $entry->getComment();
                 */
                    if(get_class($entry) == 'SeedDMS_Core_Document') {
                        $recs[] = 'D'.$entry->getName();
                    } else {
                        $recs[] = 'F'.$entry->getName();
                    }
                }
                if($recs)
    //                array_unshift($recs, array('type'=>'', 'id'=>0, 'name'=>$querystr, 'comment'=>''));
                    array_unshift($recs, ' '.$querystr);
                return $this->renderer->json($response, $recs)->withStatus(200);
                break;
            default:
                $recs = array();
                foreach ($entries as $entry) {
                    if(get_class($entry) == 'SeedDMS_Core_Document') {
                        $document = $entry;
                        $lc = $document->getLatestContent();
                        if($lc) {
                            $recs[] = $this->__getLatestVersionData($lc);
                        }
                    } elseif(get_class($entry) == 'SeedDMS_Core_Folder') {
                        $folder = $entry;
                        $recs[] = $this->__getFolderData($folder);
                    }
                }
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs));
                break;
        }
    } /* }}} */

    /**
     * Search for documents/folders with a given attribute=value
     *
     */
    function doSearchByAttr($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $params = $request->getQueryParams();
        $attrname = $params['name'];
        $query = $params['value'];
        if(empty($params['limit']) || !$limit = $params['limit'])
            $limit = 50;
        if(ctype_digit($attrname) && ((int) $attrname) > 0)
            $attrdef = $dms->getAttributeDefinition((int) $attrname);
        else
            $attrdef = $dms->getAttributeDefinitionByName($attrname);
        $entries = array();
        if($attrdef) {
            $resArr = $attrdef->getObjects($query, $limit);
            if($resArr['folders']) {
                foreach ($resArr['folders'] as $entry) {
                    if ($entry->getAccessMode($userobj) >= M_READ) {
                        $entries[] = $entry;
                    }
                }
            }
            if($resArr['docs']) {
                foreach ($resArr['docs'] as $entry) {
                    if ($entry->getAccessMode($userobj) >= M_READ) {
                        $entries[] = $entry;
                    }
                }
            }
        }
        $recs = array();
        foreach ($entries as $entry) {
            if(get_class($entry) == 'SeedDMS_Core_Document') {
                $document = $entry;
                $lc = $document->getLatestContent();
                if($lc) {
                    $recs[] = $this->__getLatestVersionData($lc);
                }
            } elseif(get_class($entry) == 'SeedDMS_Core_Folder') {
                $folder = $entry;
                $recs[] = $this->__getFolderData($folder);
            }
        }
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$recs))->withStatus(200);
    } /* }}} */

    function checkIfAdmin($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
//        if(!$this->container->has('userobj'))
//            echo "no user object";

        if(!$this->container->has('userobj') || !($userobj = $this->container->get('userobj'))) {
            return $this->renderer->json($response, ['success'=>false, 'message'=>'Not logged in', 'data'=>''])->withStatus(403);
        }

        if(!$userobj->isAdmin()) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'You must be logged in with an administrator account to access this resource', 'data'=>''))->withStatus(403);
        }

        return true;
    } /* }}} */

    function getUsers($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        $users = $dms->getAllUsers();
        $data = [];
        foreach($users as $u)
        $data[] = $this->__getUserData($u);

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function createUser($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        $params = $request->getParsedBody();
        if(empty(trim($params['user']))) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Missing user login', 'data'=>''))->withStatus(400);
        }
        $userName = $params['user'];
        $password = isset($params['pass']) ? $params['pass'] : '';
        if(empty(trim($params['name']))) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Missing full user name', 'data'=>''))->withStatus(400);
        }
        $fullname = $params['name'];
        $email = isset($params['email']) ? $params['email'] : '';
        $language = isset($params['language']) ? $params['language'] : null;;
        $theme = isset($params['theme']) ? $params['theme'] : null;
        $comment = isset($params['comment']) ? $params['comment'] : '';
        $role = isset($params['role']) ? $params['role'] : 3;
        $roleobj = $role == 'admin' ? SeedDMS_Core_Role::getInstance(1, $dms) : ($role == 'guest' ? SeedDMS_Core_Role::getInstance(2, $dms) : SeedDMS_Core_Role::getInstance($role, $dms));
        if(!$roleobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Missing role', 'data'=>''), 400);
        }

        $newAccount = $dms->addUser($userName, seed_pass_hash($password), $fullname, $email, $language, $theme, $comment, $roleobj);
        if ($newAccount === false) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Account could not be created, maybe it already exists', 'data'=>''))->withStatus(500);
        }

        $result = $this->__getUserData($newAccount);
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$result))->withStatus(201);
    } /* }}} */

    function deleteUser($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        if($user = $dms->getUser($args['id'])) {
            if($result = $user->remove($userobj, $userobj)) {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'', 'data'=>''))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'Could not delete user', 'data'=>''))->withStatus(500);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such user', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    /**
     * Updates the password of an existing Account, the password
     * will be hashed by this method
     *
     * @param      <type>  $id     The user name or numerical identifier
     */
    function changeUserPassword($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        $params = $request->getParsedBody();
        if ($params['password'] == null) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'You must supply a new password', 'data'=>''))->withStatus(400);
        }

        $newPassword = $params['password'];

        if(ctype_digit($args['id']))
            $account = $dms->getUser($args['id']);
        else {
            $account = $dms->getUserByLogin($args['id']);
        }

        /**
         * User not found
         */
        if (!$account) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'User not found.'))->withStatus(404);
            return;
        }

        $operation = $account->setPwd(seed_pass_hash($newPassword));

        if (!$operation){
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'Could not change password.'))->withStatus(404);
        }

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
    } /* }}} */

    /**
     * Updates the quota of an existing account
     *
     * @param      <type>  $id     The user name or numerical identifier
     */
    function changeUserQuota($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        $params = $request->getParsedBody();
        if ($params['quota'] == null || !ctype_digit($params['quota'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'You must supply a new quota', 'data'=>''))->withStatus(400);
        }

        $newQuota = $params['quota'];

        if(ctype_digit($args['id']))
            $account = $dms->getUser($args['id']);
        else {
            $account = $dms->getUserByLogin($args['id']);
        }

        /**
         * User not found
         */
        if (!$account) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'User not found.'))->withStatus(404);
            return;
        }

        $operation = $account->setQuota($newQuota);

        if (!$operation){
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'Could not change quota.'))->withStatus(404);
        }

        $data = $this->__getUserData($account);
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function changeUserHomefolder($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        if(ctype_digit($args['id']))
            $account = $dms->getUser($args['id']);
        else {
            $account = $dms->getUserByLogin($args['id']);
        }

        /**
         * User not found
         */
        if (!$account) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'User not found.'))->withStatus(404);
            return;
        }

        if(!ctype_digit($args['folderid'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No homefolder given', 'data'=>''))->withStatus(400);
            return;
        }
        if($args['folderid'] == 0) {
            $operation = $account->setHomeFolder(0);
        } else {
            $newHomefolder = $dms->getFolder($args['folderid']);
            if (!$newHomefolder) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'Folder not found.'))->withStatus(404);
                return;
            }

            $operation = $account->setHomeFolder($newHomefolder->getId());
        }

        if (!$operation){
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'Could not change homefolder.'))->withStatus(404);
        }

        $data = $this->__getUserData($account);
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function getUserById($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;
        if(ctype_digit($args['id']))
            $account = $dms->getUser($args['id']);
        else {
            $account = $dms->getUserByLogin($args['id']);
        }
        if($account) {
            $data = $this->__getUserData($account);
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such user', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function setDisabledUser($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;
        $params = $request->getParsedBody();
        if (!isset($params['disable'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'You must supply a disabled state', 'data'=>''))->withStatus(400);
        }

        $isDisabled = false;
        $status = $params['disable'];
        if ($status == 'true' || $status == '1') {
            $isDisabled = true;
        }

        if(ctype_digit($args['id']))
            $account = $dms->getUser($args['id']);
        else {
            $account = $dms->getUserByLogin($args['id']);
        }

        if($account) {
            $account->setDisabled($isDisabled);
            $data = $this->__getUserData($account);
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such user', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getRoles($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        $roles = $dms->getAllRoles();
        $data = [];
        foreach($roles as $r)
            $data[] = $this->__getRoleData($r);

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function createRole($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;
        $params = $request->getParsedBody();
        if (empty($params['name'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Need a role name.', 'data'=>''))->withStatus(400);
        }

        $roleName = $params['name'];
        $roleType = $params['role'];

        $newRole = $dms->addRole($roleName, $roleType);
        if ($newRole === false) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Role could not be created, maybe it already exists', 'data'=>''))->withStatus(500);
        }

    //    $result = array('id'=>(int)$newGroup->getID());
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getRoleData($newRole)))->withStatus(201);
    } /* }}} */

    function deleteRole($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        if($role = $dms->getRole($args['id'])) {
            if($result = $role->remove($userobj)) {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'', 'data'=>''))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'Could not delete role', 'data'=>''))->withStatus(500);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such role', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getRole($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;
        if(ctype_digit($args['id']))
            $role = $dms->getRole($args['id']);
        else {
            $role = $dms->getRoleByName($args['id']);
        }
        if($role) {
            $data = $this->__getRoleData($role);
            $data['users'] = array();
            foreach ($role->getUsers() as $user) {
                $data['users'][] =  array('id' => (int)$user->getID(), 'login' => $user->getLogin());
            }
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such role', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getGroups($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        $groups = $dms->getAllGroups();
        $data = [];
        foreach($groups as $u)
        $data[] = $this->__getGroupData($u);

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function createGroup($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;
        $params = $request->getParsedBody();
        if (empty($params['name'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Need a group name.', 'data'=>''))->withStatus(400);
        }

        $groupName = $params['name'];
        $comment = isset($params['comment']) ? $params['comment'] : '';

        $newGroup = $dms->addGroup($groupName, $comment);
        if ($newGroup === false) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Group could not be created, maybe it already exists', 'data'=>''))->withStatus(500);
        }

    //    $result = array('id'=>(int)$newGroup->getID());
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getGroupData($newGroup)))->withStatus(201);
    } /* }}} */

    function deleteGroup($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        if($group = $dms->getGroup($args['id'])) {
            if($result = $group->remove($userobj)) {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'', 'data'=>''))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'Could not delete group', 'data'=>''))->withStatus(500);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such group', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function getGroup($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        if(ctype_digit($args['id']))
            $group = $dms->getGroup($args['id']);
        else {
            $group = $dms->getGroupByName($args['id']);
        }
        if($group) {
            $data = $this->__getGroupData($group);
            $data['users'] = array();
            foreach ($group->getUsers() as $user) {
                $data['users'][] =  array('id' => (int)$user->getID(), 'login' => $user->getLogin());
            }
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such group', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function changeGroupMembership($request, $response, $args, $operationType) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        if (ctype_digit($args['id']))
            $group = $dms->getGroup($args['id']);
        else {
            $group = $dms->getGroupByName($args['id']);
        }

        $params = $request->getParsedBody();
        if (empty($params['userid'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Missing userid', 'data'=>''))->withStatus(400);
        }
        $userId = $params['userid'];
        if (ctype_digit($userId))
            $user = $dms->getUser($userId);
        else {
            $user = $dms->getUserByLogin($userId);
        }

        if (!($group && $user)) {
            return $response->withStatus(404);
        }

        $operationResult = false;

        if ($operationType == 'add') {
            $operationResult = $group->addUser($user);
        }
        if ($operationType == 'remove') {
            $operationResult = $group->removeUser($user);
        }

        if ($operationResult === false) {
            $message = 'Could not add user to the group.';
            if ($operationType == 'remove') {
                $message = 'Could not remove user from group.';
            }
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Something went wrong. ' . $message, 'data'=>''))->withStatus(500);
        }

        $data = $this->__getGroupData($group);
        $data['users'] = array();
        foreach ($group->getUsers() as $userObj) {
            $data['users'][] =  array('id' => (int)$userObj->getID(), 'login' => $userObj->getLogin());
        }
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function addUserToGroup($request, $response, $args) { /* {{{ */
        return $this->changeGroupMembership($request, $response, $args, 'add');
    } /* }}} */

    function removeUserFromGroup($request, $response, $args) { /* {{{ */
        return $this->changeGroupMembership($request, $response, $args, 'remove');
    } /* }}} */

    function setFolderInheritsAccess($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        $params = $request->getParsedBody();
        if (!isset($params['enable']))
        {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'You must supply an "enable" value', 'data'=>''))->withStatus(400);
        }

        $inherit = false;
        $status = $params['enable'];
        if ($status == 'true' || $status == '1')
        {
            $inherit = true;
        }

        if(ctype_digit($args['id']))
            $folder = $dms->getFolder($args['id']);
        else {
            $folder = $dms->getFolderByName($args['id']);
        }

        if($folder) {
            $folder->setInheritAccess($inherit);
            $folderId = $folder->getId();
            $folder = null;
            // reread from db
            $folder = $dms->getFolder($folderId);
            $success = ($folder->inheritsAccess() == $inherit);
            return $this->renderer->json($response, array('success'=>$success, 'message'=>'', 'data'=>$data))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function setFolderOwner($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        if(!ctype_digit($args['id']) || $args['id'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No folder given', 'data'=>''))->withStatus(400);
            return;
        }
        if(!ctype_digit($args['userid']) || $args['userid'] == 0) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No user given', 'data'=>''))->withStatus(400);
            return;
        }
        $owner = $dms->getUser($args['userid']);
        $folder = $dms->getFolder($args['id']);
        if($folder && $owner) {
            if($folder->getAccessMode($userobj, 'setDocumentOwner') > M_READ) {
                if ($folder->setOwner($owner)){
                    return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(201);
                } else {
                        return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not set owner of folder', 'data'=>''))->withStatus(500);
                }
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No access on folder', 'data'=>''))->withStatus(403);
            }
        } else {
            if(!$doc)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
            if(!$owner)
                return $this->renderer->json($response, array('success'=>false, 'message'=>'No such user', 'data'=>''))->withStatus(404);
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not find user or folder', 'data'=>''))->withStatus(500);
        }
    } /* }}} */

    function addUserAccessToFolder($request, $response, $args) { /* {{{ */
        return $this->changeFolderAccess($request, $response, $args, 'add', 'user');
    } /* }}} */

    function addGroupAccessToFolder($request, $response, $args) { /* {{{ */
        return $this->changeFolderAccess($request, $response, $args, 'add', 'group');
    } /* }}} */

    function removeUserAccessFromFolder($request, $response, $args) { /* {{{ */
        return $this->changeFolderAccess($request, $response, $args, 'remove', 'user');
    } /* }}} */

    function removeGroupAccessFromFolder($request, $response, $args) { /* {{{ */
        return $this->changeFolderAccess($request, $response, $args, 'remove', 'group');
    } /* }}} */

    function changeFolderAccess($request, $response, $args, $operationType, $userOrGroup) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if($check !== true)
            return $check;

        if(ctype_digit($args['id']))
            $folder = $dms->getfolder($args['id']);
        else {
            $folder = $dms->getfolderByName($args['id']);
        }
        if (!$folder) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
        }

        $params = $request->getParsedBody();
        $userOrGroupIdInput = $params['id'];
        if ($operationType == 'add') {
            if ($params['id'] == null) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Please PUT the user or group Id', 'data'=>''))->withStatus(400);
            }

            if ($params['mode'] == null) {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Please PUT the access mode', 'data'=>''))->withStatus(400);
            }

            $modeInput = $params['mode'];

            $mode = M_NONE;
            if ($modeInput == 'read') {
                $mode = M_READ;
            }
            if ($modeInput == 'readwrite') {
                $mode = M_READWRITE;
            }
            if ($modeInput == 'all') {
                $mode = M_ALL;
            }
        }

        $userOrGroupId = $userOrGroupIdInput;
        if (!ctype_digit($userOrGroupIdInput) && $userOrGroup == 'user') {
            $userOrGroupObj = $dms->getUserByLogin($userOrGroupIdInput);
        }
        if (!ctype_digit($userOrGroupIdInput) && $userOrGroup == 'group') {
            $userOrGroupObj = $dms->getGroupByName($userOrGroupIdInput);
        }
        if (ctype_digit($userOrGroupIdInput) && $userOrGroup == 'user') {
            $userOrGroupObj = $dms->getUser($userOrGroupIdInput);
        }
        if (ctype_digit($userOrGroupIdInput) && $userOrGroup == 'group') {
            $userOrGroupObj = $dms->getGroup($userOrGroupIdInput);
        }
        if (!$userOrGroupObj) {
            return $response->withStatus(404);
        }
        $userOrGroupId = $userOrGroupObj->getId();

        $operationResult = false;

        if ($operationType == 'add' && $userOrGroup == 'user') {
            $operationResult = $folder->addAccess($mode, $userOrGroupId, true);
        }
        if ($operationType == 'remove' && $userOrGroup == 'user') {
            $operationResult = $folder->removeAccess($userOrGroupId, true);
        }

        if ($operationType == 'add' && $userOrGroup == 'group') {
            $operationResult = $folder->addAccess($mode, $userOrGroupId, false);
        }
        if ($operationType == 'remove' && $userOrGroup == 'group') {
            $operationResult = $folder->removeAccess($userOrGroupId, false);
        }

        if ($operationResult === false) {
            $message = 'Could not add user/group access to this folder.';
            if ($operationType == 'remove') {
                $message = 'Could not remove user/group access from this folder.';
            }
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Something went wrong. ' . $message, 'data'=>''))->withStatus(500);
        }

        $data = array();
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function getCategories($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if (false === ($categories = $dms->getDocumentCategories())) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not get categories', 'data'=>null))->withStatus(500);
        }

        $data = [];
        foreach ($categories as $category)
            $data[] = $this->__getCategoryData($category);

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function getCategory($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if (!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $category = $dms->getDocumentCategory($args['id']);
        if ($category) {
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getCategoryData($category)))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such category', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    function createCategory($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');
        $logger = $this->container->get('logger');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        $params = $request->getParsedBody();
        if (empty($params['name'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Need a category.', 'data'=>''))->withStatus(400);
        }

        $catobj = $dms->getDocumentCategoryByName($params['name']);
        if ($catobj) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Category already exists', 'data'=>''))->withStatus(409);
        } else {
            if($data = $dms->addDocumentCategory($params['name'])) {
                $logger->log("Creating category '".$data->getName()."' (".$data->getId().") successful", PEAR_LOG_INFO);
                return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getCategoryData($data)))->withStatus(201);
            } else {
                return $this->renderer->json($response, array('success'=>false, 'message'=>'Could not add category', 'data'=>''))->withStatus(500);
            }
        }
    } /* }}} */

    function deleteCategory($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        if ($category = $dms->getDocumentCategory($args['id'])) {
            if ($result = $category->remove()) {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'', 'data'=>''))->withStatus(200);
            } else {
                return $this->renderer->json($response, array('success'=>$result, 'message'=>'Could not delete category', 'data'=>''))->withStatus(500);
            }
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such category', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    /**
     * Updates the name of an existing category
     *
     * @param      <type>  $id     The user name or numerical identifier
     */
    function changeCategoryName($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        if (!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $params = $request->getParsedBody();
        if (empty($params['name'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'You must supply a new name', 'data'=>''))->withStatus(400);
        }

        $newname = $params['name'];

        $category = $dms->getDocumentCategory($args['id']);

        /**
         * Category not found
         */
        if (!$category) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such category', 'data'=>''))->withStatus(404);
        }

        if (!$category->setName($newname)) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'Could not change name.'))->withStatus(400);
        }

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getCategoryData($category)))->withStatus(200);
    } /* }}} */

    function getAttributeDefinitions($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $attrdefs = $dms->getAllAttributeDefinitions();
        $data = [];
        foreach ($attrdefs as $attrdef)
            $data[] = $this->__getAttributeDefinitionData($attrdef);

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

    function getAttributeDefinition($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        if (!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $attrdef = $dms->getAttributeDefinition($args['id']);
        if ($attrdef) {
            return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getAttributeDefinitionData($attrdef)))->withStatus(200);
        } else {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such attributedefinition', 'data'=>''))->withStatus(404);
        }
    } /* }}} */

    /**
     * Updates the name of an existing attribute definition
     *
     * @param      <type>  $id     The user name or numerical identifier
     */
    function changeAttributeDefinitionName($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        if (!ctype_digit($args['id'])) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Invalid parameter', 'data'=>''))->withStatus(400);
        }

        $params = $request->getParsedBody();
        if (!isset($params['name']) || $params['name'] == null) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'You must supply a new name', 'data'=>''))->withStatus(400);
        }

        $newname = $params['name'];

        $attrdef = $dms->getAttributeDefinition($args['id']);

        /**
         * Attribute definition not found
         */
        if (!$attrdef) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such attribute defintion', 'data'=>''))->withStatus(404);
        }

        if (!$attrdef->setName($newname)) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'', 'data'=>'Could not change name.'))->withStatus(400);
            return;
        }

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$this->__getAttributeDefinitionData($attrdef)))->withStatus(200);
    } /* }}} */

    function clearFolderAccessList($request, $response, $args) { /* {{{ */
        $dms = $this->container->get('dms');
        $userobj = $this->container->get('userobj');

        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        if (ctype_digit($args['id'])) {
            $folder = $dms->getFolder($args['id']);
        } else {
            $folder = $dms->getFolderByName($args['id']);
        }
        if (!$folder) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'No such folder', 'data'=>''))->withStatus(404);
        }
        if (!$folder->clearAccessList()) {
            return $this->renderer->json($response, array('success'=>false, 'message'=>'Something went wrong. Could not clear access list for this folder.', 'data'=>''))->withStatus(500);
        }
        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>''))->withStatus(200);
    } /* }}} */

    function getStatsTotal($request, $response) { /* {{{ */
        $dms = $this->container->get('dms');
        $check = $this->checkIfAdmin($request, $response);
        if ($check !== true)
            return $check;

        $data = [];
        foreach (array('docstotal', 'folderstotal', 'userstotal') as $type) {
            $total = $dms->getStatisticalData($type);
            $data[$type] = $total;
        }

        return $this->renderer->json($response, array('success'=>true, 'message'=>'', 'data'=>$data))->withStatus(200);
    } /* }}} */

} /* }}} */

final class SeedDMS_TestController { /* {{{ */
    protected $container;
    protected $renderer;

    // constructor receives container instance
    public function __construct(ContainerInterface $container, JsonRenderer $renderer) {
        $this->container = $container;
        $this->renderer = $renderer;
    }

    public function echoData($request, $response, $args) { /* {{{ */
        return $this->renderer->json($response, ['success'=>true, 'message'=>'This is the result of the echo call.', 'data'=>$args['data']]);
    } /* }}} */

    public function version($request, $response, $args) { /* {{{ */
        $logger = $this->container->get('logger');

        $v = new SeedDMS_Version();
        return $this->renderer->json($response, ['success'=>true, 'message'=>'This is '.$v->banner(), 'data'=>['major'=>$v->majorVersion(), 'minor'=>$v->minorVersion(), 'subminor'=>$v->subminorVersion(), 'version'=>$v->majorVersion().'.'.$v->minorVersion().'.'.$v->subminorVersion()]]);
    } /* }}} */
} /* }}} */

class RestapiCorsMiddleware implements MiddlewareInterface { /* {{{ */

    private $container;

    public function __construct($container) {
        $this->container = $container;
    }

    /**
     * Example middleware invokable class
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $request->getHeader('Origin') ? $request->getHeader('Origin') : '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        return $response;
        }
} /* }}} */

/* Middleware for authentication */
class RestapiAuthMiddleware implements MiddlewareInterface { /* {{{ */

    private $container;

    private $responsefactory;

    public function __construct($container, $responsefactory) {
        $this->container = $container;
        $this->responsefactory = $responsefactory;
    }

    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Server\RequestHandlerInterface $handler
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler): ResponseInterface
    {
        // $this->container has the DI
        $dms = $this->container->get('dms');
        $settings = $this->container->get('config');
        $logger = $this->container->get('logger');

        $logger->log("Invoke AuthMiddleware for method ".$request->getMethod()." on '".$request->getUri()->getPath()."'".(isset($environment['HTTP_ORIGIN']) ? " with origin ".$environment['HTTP_ORIGIN'] : ''), PEAR_LOG_INFO);

        $userobj = null;
        /* Do not rely on $userobj being an object. It can be true, if a
         * former authentication middleware has allowed access without
         * authentification as a user. The paperless extension does this,
         * for some endpoints, e.g. to get some general api information.
         */
        if($this->container->has('userobj'))
            $userobj = $this->container->get('userobj');

        if($userobj) {
            $logger->log("Already authenticated. Pass on to next middleware", PEAR_LOG_INFO);
            $response = $handler->handle($request);
            return $response;
        }

        //$environment = $this->container->environment; // Slim 3
        $environment = $request->getServerParams();

        if($settings->_apiOrigin && isset($environment['HTTP_ORIGIN'])) {
            $logger->log("Checking origin", PEAR_LOG_DEBUG);
            $origins = explode(',', $settings->_apiOrigin);
            if(!in_array($environment['HTTP_ORIGIN'], $origins)) {
                $response = $this->responsefactory->createResponse();
                $response = $response->withHeader('Content-Type', 'application/json');
                $response = $response->withStatus(403);
                $response->getBody()->write(
                    (string)json_encode(
                        ['success'=>false, 'message'=>'Invalid origin', 'data'=>''],
                        JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                    )
                );
                return $response;
            }
        }
        /* The preflight options request doesn't have authorization in the header. So
         * don't even try to authorize.
         */
        $path = $environment['PATH_INFO'] ?? '';
        if($request->getMethod() == 'OPTIONS') {
            $logger->log("Received preflight options request", PEAR_LOG_DEBUG);
        } elseif(!in_array($path, array('/login')) && substr($path, 0, 6) != '/echo/' && $path != '/version') {
            $userobj = null;
//            $logger->log(var_export($environment, true), PEAR_LOG_DEBUG);
            if(!empty($environment['HTTP_AUTHORIZATION']) && !empty($settings->_apiKey) && !empty($settings->_apiUserId)) {
                $logger->log("Authorization key: ".$environment['HTTP_AUTHORIZATION'], PEAR_LOG_DEBUG);
                if($settings->_apiKey == $environment['HTTP_AUTHORIZATION']) {
                    if(!($userobj = $dms->getUser($settings->_apiUserId))) {
                        $response = $this->responsefactory->createResponse();
                        $response = $response->withHeader('Content-Type', 'application/json');
                        $response = $response->withStatus(403);
                        $response->getBody()->write(
                            (string)json_encode(
                                ['success'=>false, 'message'=>'Invalid user associated with api key', 'data'=>''],
                                JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                            )
                        );
                        return $response;
                    }
                } else {
                    $response = $this->responsefactory->createResponse();
                    $response = $response->withHeader('Content-Type', 'application/json');
                    $response = $response->withStatus(403);
                    $response->getBody()->write(
                        (string)json_encode(
                            ['success'=>false, 'message'=>'Wrong api key', 'data'=>''],
                            JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                        )
                    );
                    return $response;
                }
                $logger->log("Login with apikey as '".$userobj->getLogin()."' successful", PEAR_LOG_INFO);
            } else {
                $logger->log("Checking for valid session", PEAR_LOG_INFO);
                require_once("../inc/inc.ClassSession.php");
                $session = new SeedDMS_Session($dms->getDb());
                if (isset($_COOKIE["mydms_session"])) {
                    $logger->log("Found cookie for session", PEAR_LOG_INFO);
                    $dms_session = $_COOKIE["mydms_session"];
                    $logger->log("Session key: ".$dms_session, PEAR_LOG_DEBUG);
                    if(!$resArr = $session->load($dms_session)) {
                        /* Delete Cookie */
                        setcookie("mydms_session", $dms_session, time()-3600, $settings->_httpRoot);
                        $logger->log("Session for id '".$dms_session."' has gone", PEAR_LOG_ERR);
                        $response = $this->responsefactory->createResponse();
                        $response = $response->withHeader('Content-Type', 'application/json');
                        $response = $response->withStatus(403);
                        $response->getBody()->write(
                            (string)json_encode(
                                ['success'=>false, 'message'=>'Session has gone', 'data'=>''],
                                JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                            )
                        );
                        return $response;
                    }

                    /* Load user data */
                    $userobj = $dms->getUser($resArr["userID"]);
                    if (!is_object($userobj)) {
                        /* Delete Cookie */
                        setcookie("mydms_session", $dms_session, time()-3600, $settings->_httpRoot);
                        if($settings->_enableGuestLogin) {
                            if(!($userobj = $dms->getUser($settings->_guestID)))
                                $response = $this->responsefactory->createResponse();
                                $response = $response->withHeader('Content-Type', 'application/json');
                                $response = $response->withStatus(403);
                                $response->getBody()->write(
                                    (string)json_encode(
                                        ['success'=>false, 'message'=>'Could not get guest login', 'data'=>''],
                                        JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                                    )
                                );
                                return $response;
                        } else
                            $response = $this->responsefactory->createResponse();
                            $response = $response->withHeader('Content-Type', 'application/json');
                            $response = $response->withStatus(403);
                            $response->getBody()->write(
                                (string)json_encode(
                                    ['success'=>false, 'message'=>'Login as guest disable', 'data'=>''],
                                    JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                                )
                            );
                            return $response;
                    }
                    $logger->log("Authorization as user '".$userobj->getLogin()."'", PEAR_LOG_DEBUG);
                    if($userobj->isAdmin()) {
                        if($resArr["su"]) {
                            if(!($userobj = $dms->getUser($resArr["su"]))) {
                                $response = $this->responsefactory->createResponse();
                                $response = $response->withHeader('Content-Type', 'application/json');
                                $response = $response->withStatus(403);
                                $response->getBody()->write(
                                    (string)json_encode(
                                        ['success'=>false, 'message'=>'Cannot substitute user', 'data'=>''],
                                        JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                                    )
                                );
                                return $response;
                            }
                        }
                    }
                    $logger->log("Login with user name '".$userobj->getLogin()."' successful", PEAR_LOG_INFO);
                    $dms->setUser($userobj);
                } else {
                    $response = $this->responsefactory->createResponse();
                    $response = $response->withHeader('Content-Type', 'application/json');
                    $response = $response->withStatus(403);
                    $response->getBody()->write(
                        (string)json_encode(
                            ['success'=>false, 'message'=>'Missing session cookie', 'data'=>''],
                            JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                        )
                    );
                    return $response;
                }
            }
            $this->container->set('userobj', $userobj);
        }
        $response = $handler->handle($request);
        $logger->log("End AuthMiddleware for method ".$request->getMethod()." on '".$request->getUri()->getPath()."'", PEAR_LOG_INFO);
        return $response;
    }
} /* }}} */

$containerBuilder = new ContainerBuilder();
$c = $containerBuilder->build();
AppFactory::setContainer($c);
$app = AppFactory::create();

$container = $app->getContainer();
$container->set('dms', $dms);
$container->set('config', $settings);
$container->set('conversionmgr', $conversionmgr);
$container->set('logger', $logger);
$container->set('fulltextservice', $fulltextservice);
$container->set('notifier', $notifier);
$container->set('authenticator', $authenticator);

$app->setBasePath($settings->_httpRoot."restapi/index.php");

$app->add(new RestapiAuthMiddleware($container, $app->getResponseFactory()));

if(isset($GLOBALS['SEEDDMS_HOOKS']['initRestAPI'])) {
    foreach($GLOBALS['SEEDDMS_HOOKS']['initRestAPI'] as $hookObj) {
        if (method_exists($hookObj, 'addMiddleware')) {
            $hookObj->addMiddleware($app);
        }
    }
}

$app->addErrorMiddleware(true, true, true);

$app->add(new RestapiCorsMiddleware($container));

/* Without the BodyParsingMiddleware the body of PUT Request will
 * not be parsed in Slim4
 */
$app->addBodyParsingMiddleware();

// Make CORS preflighted request possible
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

// use post for create operation
// use get for retrieval operation
// use put for update operation
// use delete for delete operation
$app->post('/login', \SeedDMS_RestapiController::class.':doLogin');
$app->get('/logout', \SeedDMS_RestapiController::class.':doLogout');
$app->get('/account', \SeedDMS_RestapiController::class.':getAccount');
$app->get('/search', \SeedDMS_RestapiController::class.':doSearch');
$app->get('/searchbyattr', \SeedDMS_RestapiController::class.':doSearchByAttr');
$app->get('/folder', \SeedDMS_RestapiController::class.':getFolder');
$app->get('/folder/{id}', \SeedDMS_RestapiController::class.':getFolder');
$app->post('/folder/{id}/move/{folderid}', \SeedDMS_RestapiController::class.':moveFolder');
$app->delete('/folder/{id}', \SeedDMS_RestapiController::class.':deleteFolder');
$app->get('/folder/{id}/children', \SeedDMS_RestapiController::class.':getFolderChildren');
$app->get('/folder/{id}/parent', \SeedDMS_RestapiController::class.':getFolderParent');
$app->get('/folder/{id}/path', \SeedDMS_RestapiController::class.':getFolderPath');
$app->get('/folder/{id}/attributes', \SeedDMS_RestapiController::class.':getFolderAttributes');
$app->put('/folder/{id}/attribute/{attrdefid}', \SeedDMS_RestapiController::class.':setFolderAttribute');
$app->post('/folder/{id}/folder', \SeedDMS_RestapiController::class.':createFolder');
$app->put('/folder/{id}/document', \SeedDMS_RestapiController::class.':uploadDocumentPut');
$app->post('/folder/{id}/document', \SeedDMS_RestapiController::class.':uploadDocument');
$app->get('/document/{id}', \SeedDMS_RestapiController::class.':getDocument');
$app->post('/document/{id}/attachment', \SeedDMS_RestapiController::class.':uploadDocumentFile');
$app->post('/document/{id}/update', \SeedDMS_RestapiController::class.':updateDocument');
$app->delete('/document/{id}', \SeedDMS_RestapiController::class.':deleteDocument');
$app->post('/document/{id}/move/{folderid}', \SeedDMS_RestapiController::class.':moveDocument');
$app->get('/document/{id}/content', \SeedDMS_RestapiController::class.':getDocumentContent');
$app->get('/document/{id}/versions', \SeedDMS_RestapiController::class.':getDocumentVersions');
$app->get('/document/{id}/version/{version}', \SeedDMS_RestapiController::class.':getDocumentVersion');
$app->put('/document/{id}/version/{version}', \SeedDMS_RestapiController::class.':updateDocumentVersion');
$app->get('/document/{id}/version/{version}/attributes', \SeedDMS_RestapiController::class.':getDocumentContentAttributes');
$app->put('/document/{id}/version/{version}/attribute/{attrdefid}', \SeedDMS_RestapiController::class.':setDocumentContentAttribute');
$app->get('/document/{id}/files', \SeedDMS_RestapiController::class.':getDocumentFiles');
$app->get('/document/{id}/file/{fileid}', \SeedDMS_RestapiController::class.':getDocumentFile');
$app->get('/document/{id}/links', \SeedDMS_RestapiController::class.':getDocumentLinks');
$app->post('/document/{id}/link/{documentid}', \SeedDMS_RestapiController::class.':addDocumentLink');
$app->get('/document/{id}/attributes', \SeedDMS_RestapiController::class.':getDocumentAttributes');
$app->put('/document/{id}/attribute/{attrdefid}', \SeedDMS_RestapiController::class.':setDocumentAttribute');
$app->get('/document/{id}/preview/{version}/{width}', \SeedDMS_RestapiController::class.':getDocumentPreview');
$app->delete('/document/{id}/categories', \SeedDMS_RestapiController::class.':removeDocumentCategories');
$app->delete('/document/{id}/category/{catid}', \SeedDMS_RestapiController::class.':removeDocumentCategory');
$app->post('/document/{id}/category/{catid}', \SeedDMS_RestapiController::class.':addDocumentCategory');
$app->put('/document/{id}/owner/{userid}', \SeedDMS_RestapiController::class.':setDocumentOwner');
$app->put('/account/fullname', \SeedDMS_RestapiController::class.':setFullName');
$app->put('/account/email', \SeedDMS_RestapiController::class.':setEmail');
$app->get('/account/documents/locked', \SeedDMS_RestapiController::class.':getLockedDocuments');
$app->get('/users', \SeedDMS_RestapiController::class.':getUsers');
$app->delete('/users/{id}', \SeedDMS_RestapiController::class.':deleteUser');
$app->post('/users', \SeedDMS_RestapiController::class.':createUser');
$app->get('/users/{id}', \SeedDMS_RestapiController::class.':getUserById');
$app->put('/users/{id}/disable', \SeedDMS_RestapiController::class.':setDisabledUser');
$app->put('/users/{id}/password', \SeedDMS_RestapiController::class.':changeUserPassword');
$app->get('/roles', \SeedDMS_RestapiController::class.':getRoles');
$app->post('/roles', \SeedDMS_RestapiController::class.':createRole');
$app->get('/roles/{id}', \SeedDMS_RestapiController::class.':getRole');
$app->delete('/roles/{id}', \SeedDMS_RestapiController::class.':deleteRole');
$app->put('/users/{id}/quota', \SeedDMS_RestapiController::class.':changeUserQuota');
$app->put('/users/{id}/homefolder/{folderid}', \SeedDMS_RestapiController::class.':changeUserHomefolder');
$app->post('/groups', \SeedDMS_RestapiController::class.':createGroup');
$app->get('/groups', \SeedDMS_RestapiController::class.':getGroups');
$app->delete('/groups/{id}', \SeedDMS_RestapiController::class.':deleteGroup');
$app->get('/groups/{id}', \SeedDMS_RestapiController::class.':getGroup');
$app->put('/groups/{id}/addUser', \SeedDMS_RestapiController::class.':addUserToGroup');
$app->put('/groups/{id}/removeUser', \SeedDMS_RestapiController::class.':removeUserFromGroup');
$app->put('/folder/{id}/setInherit', \SeedDMS_RestapiController::class.':setFolderInheritsAccess');
$app->put('/folder/{id}/owner/{userid}', \SeedDMS_RestapiController::class.':setFolderOwner');
$app->put('/folder/{id}/access/group/add', \SeedDMS_RestapiController::class.':addGroupAccessToFolder'); //
$app->put('/folder/{id}/access/user/add', \SeedDMS_RestapiController::class.':addUserAccessToFolder'); //
$app->put('/folder/{id}/access/group/remove', \SeedDMS_RestapiController::class.':removeGroupAccessFromFolder');
$app->put('/folder/{id}/access/user/remove', \SeedDMS_RestapiController::class.':removeUserAccessFromFolder');
$app->put('/folder/{id}/access/clear', \SeedDMS_RestapiController::class.':clearFolderAccessList');
$app->get('/categories', \SeedDMS_RestapiController::class.':getCategories');
$app->get('/categories/{id}', \SeedDMS_RestapiController::class.':getCategory');
$app->delete('/categories/{id}', \SeedDMS_RestapiController::class.':deleteCategory');
$app->post('/categories', \SeedDMS_RestapiController::class.':createCategory');
$app->put('/categories/{id}/name', \SeedDMS_RestapiController::class.':changeCategoryName');
$app->get('/attributedefinitions', \SeedDMS_RestapiController::class.':getAttributeDefinitions');
$app->get('/attributedefinitions/{id}', \SeedDMS_RestapiController::class.':getAttributeDefinition');
$app->put('/attributedefinitions/{id}/name', \SeedDMS_RestapiController::class.':changeAttributeDefinitionName');
$app->get('/echo/{data}', \SeedDMS_TestController::class.':echoData');
$app->get('/version', \SeedDMS_TestController::class.':version');
$app->get('/statstotal', \SeedDMS_RestapiController::class.':getStatsTotal');

if(isset($GLOBALS['SEEDDMS_HOOKS']['initRestAPI'])) {
    foreach($GLOBALS['SEEDDMS_HOOKS']['initRestAPI'] as $hookObj) {
        if (method_exists($hookObj, 'addRoute')) {
            $hookObj->addRoute($app);
        }
    }
}

$app->run();

// vim: ts=4 sw=4 expandtab
