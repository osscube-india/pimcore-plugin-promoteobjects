<?php

use Pimcore\Model\Object\AbstractObject;
use Pimcore\Model\User;
use Pimcore\Model\Version;
use Pimcore\Model\Asset;
use Pimcore\Tool\Authentication;
use Pimcore\Tool\Admin;
use PromoteObjects\Model\Pimcore;
use PromoteObjects\Model\Pimcore\PromoteLogger;


class PromoteObjects_IndexController extends \Pimcore\Controller\Action\Webservice
{

    private $_curlConfig = "";

    private $_envUrl = "";

    private $_envApiKey = "";

    private $_targetUserDetails = array();

    private $_tableObjectJson = array();

    private $_responseObjectJson = array();

    private $_currentEnvObjIds = array();

    private $_promoteList = "";

    private $_rePromoteFlag = 0;

    /**
     * the webservice
     *
     * @var
     *
     */
    private $service;

    /**
     *
     * @return the $_curlConfig
     */
    public function getCurlConfig()
    {
        return $this->_curlConfig;
    }

    /**
     *
     * @param string $_curlConfig            
     */
    public function setCurlConfig()
    {
        $configFile = PIMCORE_PLUGINS_PATH . "/PromoteObjects/config.ini";
        // read in the configuration file
        $_curlConfig = new Zend_Config_Ini($configFile, 'production');
        
        $this->_curlConfig = $_curlConfig;
    }

    /**
     *
     * @return the $_envUrl
     */
    public function getEnvUrl()
    {
        $string = $this->_envUrl;
        $env = rtrim($string, "/") . "/webservice/rest/";
        return $env;
    }

    /**
     *
     * @param string $_envUrl            
     */
    public function setEnvUrl($_envUrl)
    {
        $this->_envUrl = $_envUrl;
    }

    /**
     *
     * @return the $_envApiKey
     */
    public function getEnvApiKey()
    {
        return $this->_envApiKey;
    }

    /**
     *
     * @param string $_envApiKey            
     */
    public function setEnvApiKey($_envApiKey)
    {
        $this->_envApiKey = $_envApiKey;
    }

    /**
     *
     * @param string $_envApiKey            
     */
    private function setEnvironment($type)
    {
        $keyConfig = Object\KeyValue\KeyConfig::getByName($type);
        $isValidurl = preg_match('%((http(s)?://)|(www\.))([a-z0-9-].?)+(:[0-9]+)?(/.*)?$%i', $keyConfig->description);
        if ($isValidurl) {
            $this->setEnvUrl($keyConfig->description);
        }
    }

    /**
     *
     * @param mixed $key            
     * @return multitype:|NULL
     */
    public function getTargetUser($key)
    {
        if (isset($this->_targetUserDetails[$key])) {
            return $this->_targetUserDetails[$key];
        }
        
        return null;
    }

    /**
     *
     * @param mixed $key            
     * @return multitype:|NULL
     */
    public function getUserId()
    {
        $currentUser = \Pimcore\Tool\Admin::getCurrentUser();
        return $currentUser->getId();
    }

    /**
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        
        $this->setCurlConfig();
        $this->service = new Webservice\Service();
        
        $maxExecutionTime = 1800;
        @ini_set("max_execution_time", $maxExecutionTime);
        @set_time_limit($maxExecutionTime);
    }

    private function baseURL()
    {
        $pageURL = 'http';
        if (! empty($_SERVER['HTTPS'])) {
            $pageURL .= "s";
        }
        $pageURL .= "://";
        $pageURL .= $_SERVER["HTTP_HOST"];
        $pageURL = preg_replace('/(?<!:)\//', '/', $pageURL);
        return $pageURL;
    }

    /**
     * get list of promote list objects
     */
    public function getPromoteListsAction()
    {
        $db = Pimcore_Resource_Mysql::get();
        $class = Object_Class::getByName("PromoteList");
        $table_id = $class->getId();
        $promoteListSql = "SELECT o_key,o_id FROM `object_{$table_id}`";
        $promoteListSql .= " WHERE promoted IS NULL OR promoted = 0";
        $promoteListResults = $db->fetchAll($promoteListSql);
        $this->_helper->json(array(
            "data" => $promoteListResults
        ));
    }

    /**
     * get list of environments exist for promotion
     */
    public function getenvironmentAction()
    {
        $utility = \PromoteObjects\Model\Utility::getResources();
        $promoteKeyGroup = $utility->getGroupId();
       
    
        $keyConfigList = new Object\KeyValue\KeyConfig\Listing();
        $keyConfigList->load();
        $keyConfigItems = $keyConfigList->getList();
        $pl_owner = 0;
        $reOpenList = 'N';
        $currentUsr = \Pimcore\Tool\Admin::getCurrentUser();
        if ($currentUsr->admin == 1) {
            $pl_owner = 1;
        } else {
            foreach ($currentUsr->roles as $roleId) {
                $rolename = User_UserRole::getById($roleId)->name;
                if (strstr($rolename, 'PromoteList')) {
                    $pl_owner = 1;
                }
            }
        }
        
        $promoteKeyFilter = $utility->getKeyFilter();
        
        foreach ($keyConfigItems as $value) {
            
            if ($value->getGroup() == $promoteKeyGroup && strtolower($value->name) == 'reopenpromotelist') {
                $reOpenList = $value->description;
            }elseif (in_array($value->name, $promoteKeyFilter)) {
                continue;
            }elseif ($value->getGroup() == $promoteKeyGroup) {
                $environmentVars[] = $value->name;
            }else {
                $currentEnvironment = $value->description;
            }
        }
        $this->_helper->json(array(
            "data" => array(
                'envVars' => $environmentVars,
                'envCurrent' => $currentEnvironment,
                'plOwner' => $pl_owner,
                'reOpenList' => $reOpenList
            )
        ));
    }

    /**
     * get list of objects which is in multiple promote lists.
     */
    public function getPlObjectAction()
    {
        $oid = $this->getParam("id");
        $db = Pimcore_Resource_Mysql::get();
        $class = Object_Class::getByName("PromoteList");
        $table_id = $class->getId();
        
        $changeListSql = "SELECT o_key,objects FROM `object_{$table_id}`";
        $changeListSql .= " WHERE promoted IS NULL OR promoted = 0";
        $changeListResults = $db->fetchAll($changeListSql);
        
        foreach ($changeListResults as $cItem) {
            if ($cItem['objects'] != ',,') {
                // $string = str_replace(',', "'", $cItem['objects']);
                $cItem['objects'] = trim($cItem['objects'], ',');
                $itemInList = explode(",", $cItem['objects']);
                
                if (in_array($oid, $itemInList)) {
                    $plObjects[] = $cItem['o_key'];
                }
            }
        }
        
        $this->_helper->json(array(
            "data" => $plObjects,
            "success" => true
        ));
    }

    /**
     * get list of objects for Promote List view panel
     */
    public function previewVersionAction()
    {
        $okey = $this->getParam("key");
        $id = $this->getParam("id");
        $object = Object\PromoteList::getById($id);
        $relatedobjects = $object->getObjectjson();
        
        foreach ($relatedobjects as $key => $value) {
            if ($value[1] == $okey) {
                $result['json'] = $value[2];
                $result['status'] = $value[3];
                $result['path'] = $value[1];
            }
        }
        
        $data = json_decode($result['json']);
        if ($object) {
            $this->view->object = $data;
            $this->view->status = $result['status'];
            $this->view->objectFullpath = $result['path'];
        } else {
            throw new \Exception("Object with id [" . $id . "] doesn't exist");
        }
    }

    public function getViewJsonAction()
    {
        $objectid = $this->getParam('id');
        $object = Object\PromoteList::getById($objectid);
        $relatedobjects = $object->getObjectjson();
        foreach ($relatedobjects as $key => $value) {
            $objects[$key]['key'] = $value[1];
            $objects[$key]['status'] = $value[3];
        }
        $this->_helper->json(array(
            "cl_objects" => $objects
        ));
    }

    public function indexAction()
    {
        $returnResponse = array();
        
        try {
            
            $objectid = $this->getParam('objectid');
            $selenvironment = $this->getParam('environment');
            $promoteFlag = false;
            $responseFlag = false;
            
            // Validate target enviornment user details
            $plUser = $this->targetEnvironmentUserDetails($selenvironment)->getTargetUser('name');
            $object = Object\PromoteList::getById($objectid);
            
            $objectIds = '';
            // check if list is already promoted, yes then no need to create static jsons
            if ($object->getPromoted() != true) {
                $db = Pimcore_Resource_Mysql::get();
                $class = Object_Class::getByName("PromoteList"); // company is the name of the custom Class I created
                $table_id = $class->getId();
                $sql = "SELECT objects FROM object_query_{$table_id} WHERE oo_id = {$objectid}";
                $result = $db->fetchRow($sql);
                $result['objects'] = trim($result['objects'], ",");
                $relatedobjects = explode(",", $result['objects']);
                
                foreach ($relatedobjects as $key => $value) {
                    $oid = $value;
                    $vObject = Object::getById($oid);
                    $apiObject = Webservice\Data\Mapper::map($vObject, "\\Pimcore\\Model\\Webservice\\Data\\Object\\Concrete\\Out", "out");
                    $response = Zend_Json::decode(Zend_Json::encode($apiObject, null, array()));
                    $objectPath = $response['path'];
                    $objectPath .= $response['key'];
                    $updateJson = json_encode($response);
                    $Objgroup = $this->getObjectGroup($vObject);
                    
                    $jsonArray[] = array(
                        $Objgroup,
                        $objectPath,
                        $updateJson
                    );
                    if ($objectIds != '') {                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     
                        $objectIds .= ',';
                    }
                    $objectIds .= $oid;
                }
                
                $object->setObjectjson($jsonArray);
                $object->setPromoted(1);
                $object->setPromotedobjects($objectIds);
                $object->setObjects(array());
                $object->setLocked(true);
            } else {
                $object->setRepromote(1);
            }
            Version::disable();
            $object->setEnvironment($selenvironment);
            $object->setpromotionDate(new Zend_Date());
            $object->setPlUser($plUser);
            $object->save();
            
            $zipAsset = $this->createPromoteZip($object);
            PromoteLogger::logMessage(strlen($zipAsset));
            
            if ($zipAsset) {
                
                $response = $this->executeTargetPromoteList($zipAsset);
                PromoteLogger::logMessage(json_decode(json_encode($response)), true);
                
                if (! isset($response['data'])) {
                    throw new \Exception("Unable to upload promote list data on target, Please try again");
                }
                
                $decodeResponse = json_decode($response['data'], true);
                PromoteLogger::logMessage($decodeResponse);
                
                $json = $object->getObjectjson();
                // // if response exist, then upadte promotelist
                if ($response) {
                    foreach ($json as $key => $item) {
                        $item[3] = "error";
                        if ($decodeResponse[$item[1]]) {
                            $item[3] = $decodeResponse[$item[1]];
                        }
                        $json[$key] = $item;
                    }
                }
                
                Version::disable();
                $object->setObjectjson($json);
                $object->save();
                
                $this->_helper->json(array(
                    "success" => true
                ));
            } else {
                if (! empty($result['msg'])) {
                    throw new \Exception($result['msg']);
                } else {
                    throw new \Exception("Unable to upload promote list data on target, Please try again");
                }
            }
        } catch (\Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
            
            $this->_helper->json(array(
                "success" => false,
                "msg" => $e->getMessage()
            ));
        }
    }

    /**
     *
     * @param unknown $assetName            
     * @return Ambigous <mixed, string>
     */
    private function executeTargetPromoteList($assetName)
    {
        $url = "/plugin/PromoteObjects/index/promote-list";
        $response = $this->callRestFulApiPostGzip($assetName, $url);
        
        return $response;
    }

    /**
     * Get object group sequence based on objects class type
     *
     * @param string $className            
     * @return integer $Objgroup
     */
    private function getObjectGroup($object)
    {
		return \PromoteObjects\Model\Utility::getClassesPriority($object);
    }

    /**
     * Create Json Zip file for Promotion
     *
     * @param object $object            
     * @return
     *
     */
    private function createPromoteZip($object = "")
    {
        // Create Text File with JSON Data
        $objectJson = Webservice\Data\Mapper::map($object, "\\Pimcore\\Model\\Webservice\\Data\\Object\\Concrete\\Out", "out");
        $elementType = array(
            "objectsMetadata",
            "objects",
            "image",
            "objectbricks"
        );
        $elmentId = null;
        $errorMsg = null;
        
        $objectData = json_decode(json_encode($objectJson), true);
        
        PromoteLogger::logMessage($objectData);
        $elementIndex = null;
        
        foreach ($objectData['elements'] as $plElement => $plData) {
            
            if ($plData['name'] == "objectjson") {
                
                $elementIndex = $plElement;
                break;
            }
        }
        
        if ($elementIndex != null) {
            
            foreach ($objectJson->elements[$elementIndex]->value as $key => $elements) {
                
                $data = json_decode($elements[2], true);
                
                foreach ($data['elements'] as $deKey => $element) {
                    
                    if (in_array($element['type'], $elementType)) {
                        
                        if (! empty($element['value'])) {
                            
                            if ($element['type'] === "image") {
                                
                                $elmentPath = $this->getPathfromId($element['value'], $element['type']);
                                $element['value'] = $elmentPath;
                            } else 
                                if ($element['type'] === "objectbricks") {
                                    
                                    foreach ($element['value'] as $feKey => $group) {
                                        
                                        foreach ($group['value'] as $geKey => $item) {
                                            
                                            if (in_array($item['type'], $elementType)) {
                                                
                                                if (! empty($item['value'])) {
                                                    
                                                    if ($item['type'] === "image") {
                                                        $elmentPath = $this->getPathfromId($item['value'], $item['type']);
                                                        $item['value'] = $elmentPath;
                                                    } else {
                                                        
                                                        foreach ($item['value'] as $eKey => $eRow) {
                                                            
                                                            $elmentPath = $this->getPathfromId($eRow['id'], $item['type']);
                                                            $item['value'][$eKey]['id'] = $elmentPath;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            $group['value'][$geKey] = $item;
                                        }
                                        
                                        $element['value'][$feKey] = $group;
                                    }
                                } else {
                                    
                                    foreach ($element['value'] as $eKey => $eRow) {
                                        
                                        $elmentPath = $this->getPathfromId($eRow['id'], $element['type']);
                                        $element['value'][$eKey]['id'] = $elmentPath;
                                    }
                                }
                            
                            $data['elements'][$deKey] = $element;
                        }
                    }
                }
                
                $data['id'] = $this->getPathfromId($data['id'], "objects");
                $data['parentId'] = $this->getPathfromId($data['parentId'], "parent");
                
                // Set back to object as json
                $elements[2] = json_encode($data);
                $objectJson->elements[$elementIndex]->value[$key] = $elements;
            }
            $objectJson->id = $this->getPathfromId($objectJson->id, "objects");
            $objectJson->parentId = $this->getPathfromId($objectJson->parentId, "parent");
            $objectJson = json_encode($objectJson);
            
            $compress = gzcompress($objectJson, 9);
            
            return $compress;
        }
    }

    /**
     * Get target environment user details or validate it.
     *
     * @param string $envType            
     * @param boolean $isValid            
     * @param string $msg            
     * @throws \Exception
     * @return PromoteObjects_IndexController
     */
    private function targetEnvironmentUserDetails($envType = "Test")
    {
        $this->setEnvironment($envType);
        
        if (! empty($this->_envUrl)) {
            
            $sUserObj = Authentication::authenticateSession();
            $sUserId = $sUserObj->getId();
            
            if (! $sUserObj instanceof User) {
                throw new \Exception("Invalid user in current environment");
            }
            
            if (count($this->_targetUserDetails) == 0) {
                
                // Get user details from source enviornment
                $user = User::getById($sUserId);
                
                // Call Target environment user api to validate api key
                $result = $this->callRestFulApi('user', array(), $user->getApiKey());
                
                if (! empty($result['msg'])) {
                    throw new \Exception($result['msg']);
                }
                
                if (! empty($result['success']) && ! empty($result['data'])) {
                    
                    $data = $result['data'];
                    $this->setEnvApiKey($user->getApiKey());
                    $this->_targetUserDetails = array(
                        'id' => $data['id'],
                        'name' => $data['name']
                    );
                } else {
                    throw new \Exception("User's API Key doesn't match on {$envType} environment");
                }
            }
        } else {
            throw new \Exception("Invalid {$envType} environment URL");
        }
        
        return $this;
    }

    /**
     *
     * @param unknown $oPid            
     */
    public function getPromotedObjectsAction()
    {
        $objectid = $this->getParam('oPid');
        $object = Object\PromoteList::getById($objectid);
        $promotedIds = explode(',', $object->getPromotedobjects());
        
        $this->_helper->json(array(
            "promotedobjects" => $promotedIds,
            "success" => true
        ));
    }

    /**
     *
     * @param unknown $oPid            
     */
    public function reOpenPromoteListAction()
    {
        $objectid = $this->getParam('oPid');
        $object = Object\PromoteList::getById($objectid);
        $promotedIds = explode(',', $object->getPromotedobjects());
        foreach ($promotedIds as $value) {
            $PromotedObjects[] = Object_Abstract::getById($value);
        }
        $object->setObjectjson(array());
        $object->setPromoted(0);
        $object->setRepromote(1);
        $object->setpromotionDate(new Zend_Date());
        $object->setObjects($PromotedObjects);
        $object->setLocked(false);
        $object->save();
        
        $this->_helper->json(array(
            "success" => true
        ));
    }

    /**
     * Get object path from object id
     *
     * @param integer $oId            
     * @return string $fullPath
     */
    private function getPathfromId($oId, $oType)
    {
        try {
            
            $fullPath = 0;
            
            if (! empty($oId)) {
                
                if ($oType == "objects" || $oType == "objectsMetadata" || $oType == "parent") {
                    $object = AbstractObject::getById($oId);
                    if (empty($object)) {
                        throw new \Exception();
                    }
                    $key = $object->getKey();
                    if ($oType == "parent")
                        $key .= '||' . $object->o_type;
                } else {
                    $object = Asset::getById($oId);
                    if (empty($object)) {
                        throw new \Exception();
                    }
                    $key = $object->getFilename();
                }
                $path = $object->getPath();
                $fullPath = $path . $key;
            }
        } catch (\Exception $e) {
            $error = "Object [ {$oId} ] doesn't exist, you can't promote to next environment";
            // $error = "This promote list has errors, you can't promote to next environment";
            throw new \Exception($error);
        }
        
        return $fullPath;
    }

    /**
     * Get object id from object path
     *
     * @param string $oPath            
     * @return integer $id;
     */
    private function getIdfromPath($oPath, $oType)
    {
        try {
            $id = 0;
            $eType = array(
                "objects",
                "objectsMetadata"
            );
            if (! in_array($oType, $eType) && $oType == "parent") {
                $Pdetails = explode('||', $oPath);
                if ($Pdetails[1] === "folder") {
                    $object = Object::getByPath($Pdetails[0]);
                    if (! $object) {
                        // call method to create folder
                    }
                } else
                    $object = Object::getByPath($Pdetails[0]);
            } else {
                $object = Asset::getByPath($oPath);
            }
            
            if (empty($object)) {
                throw new \Exception();
            }
            
            if ($object) {
                $id = $object->getId();
            }
        } catch (\Exception $e) {
            $error = "Object [ {$oPath} ] doesn't exist, you can't promote to next environment";
            // $error = "This promote list has errors, you can't promote to next environment";
            throw new \Exception($error);
        }
        return $id;
    }

    /**
     * Promote list on current system by uploaded assets
     *
     * @author deepakgupta
     *        
     */
    public function promoteListAction()
    {
        $result = array();
        
        try {
            $data = file_get_contents("php://input");
            
            // $data = \Zend_Json::decode($data);
            $asset = gzuncompress($data);
            PromoteLogger::logMessage($asset);
            
            $assetResponse = null;
            $objectJson = null;
            if (! empty($asset)) {
                
                $promoteListData = json_decode($asset, true);
                PromoteLogger::logMessage($promoteListData);
                
                $isObjectPromoted = false;
                $this->_promoteList = $promoteListData['key'];
                if (isset($promoteListData['elements'])) {
                    
                    foreach ($promoteListData['elements'] as $element) {
                        if ($element['name'] == 'repromote' && $element['value'] == 1) {
                            $this->_rePromoteFlag = 1;
                            break;
                        }
                    }
                    
                    foreach ($promoteListData['elements'] as $index => $element) {
                        
                        if ($element['name'] == 'objectjson') {
                            
                            $objectsData = $element['value'];
                            
                            unset($promoteListData['elements'][$index]['value']);
                            
                            // Disable Pimcore versioning when promote list is creating.
                            Version::disable();
                            
                            // Add promote list before adding any objects data
                            $this->addUpdatePromoteList($promoteListData);
                            
                            // Enable Pimcore versioning
                            Version::enable();
                            
                            $promoteDate = \Zend_Date::now()->getTimestamp();
                            
                            // Add update object & object's data into promote list
                            $isObjectPromoted = $this->promoteObjects($objectsData, $promoteListData, $index);
                            
                            if ($isObjectPromoted) {
                                
                                // Set object row to promote list object json
                                $promoteListData['elements'][$index]['value'] = $this->_tableObjectJson;
                                
                                // Set current environment object id if re-open list
                                $objectIds = implode(',', $this->_currentEnvObjIds);
                                
                                foreach ($promoteListData['elements'] as $eIndex => $eElement) {
                                    if ($eElement['name'] == 'promotedobjects') {
                                        $promoteListData['elements'][$eIndex]['value'] = $objectIds;
                                    }
                                }
                                
                                // Update promote list data with added objects
                                $response = $this->addUpdateObject($promoteListData);
                                
                                if ($response['success']) {
                                    
                                    $key = $promoteListData['key'];
                                    
                                    $objectJson = json_encode($this->_responseObjectJson);
                                }
                            }
                        }
                    }
                }
                
                if (! empty($objectJson)) {
                    
                    $result = array(
                        'success' => true,
                        'data' => $objectJson
                    );
                }
            }
            
            if (count($result) == 0) {
                $result = array(
                    'success' => false
                );
            }
        } catch (\Exception $e) {
            
            PromoteLogger::logMessage($e->getMessage());
            
            $result = array(
                'success' => false,
                'msg' => $e->getMessage()
            );
        }
        
        // unlink($assetName);
        PromoteLogger::logMessage($result);
        $this->_helper->json($result);
    }

    /**
     *
     * @param unknown $promoteListData            
     */
    private function addUpdatePromoteList(& $promoteListData)
    {
        $msg = "";
        
        $object = new stdClass();
        $id = $this->isObjectExists($promoteListData['id'], $msg, $object);
        $promoteListData['id'] = $id;
        
        $promoteListData['modificationDate'] = time();
        
        if (empty($id)) {
            $promoteListData['userOwner'] = $this->getUserId();
            $promoteListData['userModification'] = $this->getUserId();
        } else {
            $promoteListData['userModification'] = $this->getUserId();
        }
        
        if (strpos($promoteListData['parentId'], "||")) {
            
            $parent = explode('||', $promoteListData['parentId']);
            $path = $parent[0];
            
            if ($parent[1] != "folder") {
                
                $msg = "\"{$path}\" doesn't exist in target environment";
                $parentError = $msg;
            } else {
                
                // Add parent
                $parentId = $this->promoteParent($path);
                $promoteListData['parentId'] = $parentId;
            }
        }
        
        try {
            
            $result = $this->addUpdateObject($promoteListData);
            
            if (isset($result['id'])) {
                $promoteListData['id'] = $result['id'];
            }
        } catch (\Exception $e) {
            
            PromoteLogger::logMessage($e->getMessage());
            
            $result = array(
                'success' => false,
                'msg' => $e->getMessage()
            );
        }
        
        if ($result['success'] === false) {}
    }

    /**
     * Sort objects by master data priority
     *
     * @author deepakgupta
     *        
     */
    private function sortObjects($objects)
    {
        if (is_array($objects)) {
            
            $sortedObjects = array();
            
            foreach ($objects as $rkey => $row) {
                
                $index = $row[0];
                // Reindex Array
                $reindexRow = array_values($row);
                
                $sortedObjects[$index][] = $reindexRow;
            }
            
            ksort($sortedObjects);
        }
        return $sortedObjects;
    }

    /**
     * Get object id from Target Environment by path
     *
     * @param integer $oId            
     * @param string $msg            
     * @return number
     */
    private function isObjectExists($path, & $msg = "", & $object = array(), $type = "object")
    {
        if (strpos($path, "||")) {
            
            $parent = explode('||', $path);
            $path = $parent[0];
        }
        if ($type === "object") {
            $object = AbstractObject::getByPath($path);
        } else {
            $object = Asset::getByPath($path);
        }
        
        $id = 0;
        
        if (! empty($object)) {
            $id = $object->getId();
        } else {
            $msg = "\"{$path}\" doesn't exist in target environment";
        }
        
        return $id;
    }

    /**
     */
    private function addUpdateObject($data)
    {
        try {
            
            $type = $data["type"];
            $id = null;
            
            if ($data["id"]) {
                $obj = Object::getById($data["id"]);
                
                $isUpdate = true;
                if ($type == "folder") {
                    $wsData = self::fillWebserviceData("\\Pimcore\\Model\\Webservice\\Data\\Object\\Folder\\In", $data);
                    $success = $this->service->updateObjectFolder($wsData);
                } else {
                    $wsData = self::fillWebserviceData("\\Pimcore\\Model\\Webservice\\Data\\Object\\Concrete\\In", $data);
                    
                    $success = $this->service->updateObjectConcrete($wsData);
                }
            } else {
                if ($type == "folder") {
                    $class = "\\Pimcore\\Model\\Webservice\\Data\\Object\\Folder\\In";
                    $method = "createObjectFolder";
                } else {
                    $class = "\\Pimcore\\Model\\Webservice\\Data\\Object\\Concrete\\In";
                    $method = "createObjectConcrete";
                }
                $wsData = self::fillWebserviceData($class, $data);
                
                $obj = new Object();
                $obj->setId($wsData->parentId);
                
                $id = $this->service->$method($wsData);
            }
            
            if (! $isUpdate) {
                $success = $id != null;
            }
            
            $result = array(
                "success" => $success
            );
            
            if ($success && ! $isUpdate) {
                $result["id"] = $id;
            }
        } catch (\Exception $e) {
            
            $result = array(
                "success" => false,
                "msg" => (string) $e
            );
        }
        return $result;
    }

    private static function map($wsData, $data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $tmp = array();
                
                foreach ($value as $subkey => $subvalue) {
                    if (is_array($subvalue)) {
                        $object = new stdClass();
                        $object = self::map($object, $subvalue);
                        ;
                        $tmp[$subkey] = $object;
                    } else {
                        $tmp[$subkey] = $subvalue;
                    }
                }
                $value = $tmp;
            }
            $wsData->$key = $value;
        }
        return $wsData;
    }

    public static function fillWebserviceData($class, $data)
    {
        $wsData = new $class();
        return self::map($wsData, $data);
    }

    /**
     * get template for folder creation
     *
     * @return array $template
     */
    private function getFolderTemplate()
    {
        $template = array(
            'path' => '',
            'creationDate' => time(),
            'modificationDate' => time(),
            'userModification' => $this->getUserId(),
            'id' => 0,
            'parentId' => 0,
            'key' => '',
            'published' => null,
            'type' => 'folder',
            'userOwner' => $this->getUserId(),
            'properties' => null
        );
        return $template;
    }

    /**
     * Promote Parent Object
     *
     * @param integer $oPid            
     *
     */
    private function promoteParent($oPid)
    {
        $msg = "";
        
        $folders = explode('/', ltrim($oPid, '/'));
        
        $hierarchy = array();
        
        $parents = array();
        
        foreach ($folders as $index => $folder) {
            
            array_push($hierarchy, $folder);
            
            $path = "/" . implode('/', $hierarchy);
            
            $object = new stdClass();
            $id = $this->isObjectExists($path, $msg, $object);
            
            if ($id > 0) {
                $parents[$index] = $id;
            } else {
                
                $template = $this->getFolderTemplate();
                
                if ($index == 0) {
                    $parents[0] = 1;
                    $template['path'] = "/";
                    $template['parentId'] = 1;
                } else {
                    $template['path'] = rtrim($path, $folder);
                    $template['parentId'] = $parents[$index - 1];
                }
                $template['key'] = $folder;
                
                $response = $this->addUpdateObject($template);
                
                if (! empty($response['id'])) {
                    $parents[$index] = $response['id'];
                }
            }
        }
        
        krsort($parents);
        $parents = array_values($parents);
        
        return $parents[0];
    }

    /**
     * Promote Objects
     *
     * @param string $object            
     * @param string $envType            
     * @return boolean
     */
    private function promoteObjects($objectsData, & $promoteListData, $index)
    {
        $elementType = array(
            "objectsMetadata",
            "objects",
            "image",
            "objectbricks"
        );
        
        $this->_tableObjectJson = array();
        $this->_responseObjectJson = array();
        
        // Sort objects by master data priority
        $sortedObjects = $this->sortObjects($objectsData);
        
        foreach ($sortedObjects as $item) {
            
            foreach ($item as $rkey => $row) {
                $row[3] = null;
                $data = json_decode($row[2], true);
                
                $elmentId = null;
                $errorMsg = null;
                $errorElement = array();
                
                foreach ($data['elements'] as $deKey => $element) {
                    
                    if (in_array($element['type'], $elementType)) {
                        
                        if (! empty($element['value'])) {
                            
                            if ($element['type'] === "image") {
                                
                                $elmentId = $this->getIdfromPath($element['value'], $element['type']);
                                $element['value'] = $elmentId;
                            } else 
                                if ($element['type'] === "objectbricks") {
                                    
                                    foreach ($element['value'] as $feKey => $group) {
                                        
                                        foreach ($group['value'] as $geKey => $item) {
                                            
                                            if (in_array($item['type'], $elementType)) {
                                                
                                                if (! empty($item['value'])) {
                                                    
                                                    if ($item['type'] === "image") {
                                                        $obj = new stdClass();
                                                        $elmentId = $this->isObjectExists($item['value'], $errorMsg, $obj, "asset");
                                                        if ($elmentId === 0 || $errorMsg !== null) {
                                                            $errorElement[] = $errorMsg;
                                                        }
                                                        $item['value'] = $elmentId;
                                                    } else {
                                                        
                                                        foreach ($item['value'] as $eKey => $eRow) {
                                                            $elmentId = $this->isObjectExists($eRow['id'], $errorMsg);
                                                            if ($elmentId === 0 || $errorMsg !== null) {
                                                                $errorElement[] = $errorMsg;
                                                            }
                                                            $item['value'][$eKey]['id'] = $elmentId;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            $group['value'][$geKey] = $item;
                                        }
                                        
                                        $element['value'][$feKey] = $group;
                                    }
                                } else {
                                    
                                    foreach ($element['value'] as $eKey => $eRow) {
                                        
                                        $elmentId = $this->isObjectExists($eRow['id'], $errorMsg);
                                        if ($elmentId === 0 || $errorMsg !== null) {
                                            $errorElement[] = $errorMsg;
                                        }
                                        $element['value'][$eKey]['id'] = $elmentId;
                                    }
                                }
                            
                            $data['elements'][$deKey] = $element;
                        }
                    }
                }
                
                if (count($errorElement) === 0) {
                    
                    $id = $this->isObjectExists($data['id'], $errorMsg);
                    
                    $data['id'] = $id;
                    $data['modificationDate'] = time();
                    
                    if (empty($id)) {
                        
                        $data['userOwner'] = $this->getUserId();
                        $data['userModification'] = $this->getUserId();
                    } else {
                        $data['userModification'] = $this->getUserId();
                    }
                    
                    // validate either parent is exists or not.
                    // if parent is folder and not exists on target, validate recursive parent
                    // if not exists then create folder
                    // if parent is object and doesn't exists on target -> return error
                    $parentId = $this->isObjectExists($data['parentId']);
                    
                    $parentError = "";
                    
                    if ($parentId > 0) {
                        $data['parentId'] = $parentId;
                    } else {
                        
                        if (strpos($data['parentId'], "||")) {
                            
                            $parent = explode('||', $data['parentId']);
                            $path = $parent[0];
                            
                            if ($parent[1] != "folder") {
                                
                                $msg = "\"{$path}\" doesn't exist in target environment";
                                $parentError = $msg;
                            } else {
                                
                                // Add parent
                                $parentId = $this->promoteParent($path);
                                $data['parentId'] = $parentId;
                            }
                        }
                    }
                    
                    if (! empty($parentError)) {
                        
                        $row[3] = json_encode(array(
                            $parentError
                        ));
                    } elseif ($data['parentId'] > 0) {
                        
                        try {
                            // Add update objects on target environment
                            $result = $this->addUpdateObject($data);
                            PromoteLogger::logMessage($result);
                            
                            if (! empty($result['id'])) {
                                array_push($this->_currentEnvObjIds, $result['id']);
                            } else {
                                array_push($this->_currentEnvObjIds, $data['id']);
                            }
                        } catch (\Exception $e) {
                            $result = array(
                                'success' => false,
                                'msg' => $e->getMessage()
                            );
                        }
                        
                        if ($result['success'] === false) {
                            $row[3] = json_encode(array(
                                $result['msg']
                            ));
                        } else {
                            $row[2] = json_encode($data);
                            $row[3] = "success";
                        }
                    } else {
                        $row[3] = json_encode(array(
                            "Required path " . $data['path'] . " couldn't be created "
                        ));
                    }
                } else {
                    
                    $row[3] = json_encode($errorElement);
                }
                
                // Make object success & failed response to set in promote list on current environment
                $this->_tableObjectJson[] = $row;
                
                PromoteLogger::logMessage($row);
                
                // Make response to return to source environment
                $this->_responseObjectJson[$row[1]] = $row[3];
            }
        }
        
        return true;
    }

    /**
     */
    public function getObjectInMultipleListAction()
    {
        $objectIds = json_decode($this->getParam('data'));
        $promoteId = $this->getParam('objid');
        $objectResponse = array();
        foreach ($objectIds as $key => $id) {
            $count = Object\PromoteList::getTotalCount(array(
                'condition' => "objects LIKE '%,$id,%' AND oo_id <> $promoteId"
            ));
            $objectResponse[$id] = $count;
            // $objectResponse[$key][1] = $count;
        }
        
        $this->_helper->json(array(
            "success" => true,
            "objects" => $objectResponse
        ));
    }

    /**
     * Get objects which are changed and not promoted or not in promote list
     *
     * @param
     *            string timezone
     * @param
     *            string tzOffset
     * @param
     *            string start
     * @param
     *            string limit
     * @param
     *            string fromDate
     * @param
     *            string toDate
     * @param
     *            string exportstatus
     * @return array unknown
     *        
     */
    public function getUpdatedObjectsAction()
    {
        $timezone = $this->getParam('timezone');
        $timezoneoffset = abs($this->getParam('tzOffset'));
        $timezone = ($timezone != null ? timezone_name_from_abbr($timezone, $timezoneoffset * 60, 0) : null);
        $offset = $this->getParam("start", 0);
        $limit = $this->getParam("limit", 40);
        $startDate = $this->getParam("fromDate");
        $exportFlag = $this->getParam("exportstatus");
        $endDate = strtotime('+1 day', $this->getParam("toDate"));
        
        $modifiedBy = $this->getParam("modifiedBy");
        $classType = $this->getParam("classType");
        $publishedType = $this->getParam("publishedType");
        
        if ($modifiedBy == "All") {
            $modifiedBy = "";
        }
        if ($classType == "All") {
            $classType = "";
        }
        if ($publishedType == "All") {
            $publishedType = "";
        }
        $data = [];
        if ($startDate != null && $endDate != null) {
            $db = Pimcore_Resource_Mysql::get();
            $class = Object_Class::getByName("PromoteList");
            $table_id = $class->getId();
            $changeListSql = "SELECT o_id, objects, promotedobjects, promoted, promotionDate FROM `object_{$table_id}`";
            $changeListSql .= " UNION ALL ";
            $changeListSql .= " SELECT o_id, objects, promotedobjects, promoted, promotionDate FROM `object_{$table_id}` ";
            $changeListSql .= " WHERE promoted IS NULL OR promoted = 0";
            $changeListResults = $db->fetchAll($changeListSql);
            $sql = "SELECT o_id,o_key,o_className,o_path,o_modificationDate,o_userModification,o_published FROM objects ";
            
            $sql .= " WHERE o_modificationDate >= {$startDate} AND o_modificationDate < {$endDate} AND o_className <> 'PromoteList' ";
            
            if (! empty($classType)) {
                $sql .= " AND o_classId = {$classType} ";
            }
            
            if (! empty($modifiedBy)) {
                $sql .= " AND o_userModification = {$modifiedBy} ";
            }
            
            if (! empty($publishedType)) {
                
                $st = ($publishedType == "Published") ? 1 : 0;
                $sql .= " AND o_published = {$st} ";
            }
            
            $sql .= " ORDER BY o_modificationDate DESC ";
            
            $object = $db->fetchAll($sql);
            
            $inList = array();
            $itemArray = array();
            
            foreach ($object as $key => $oItem) {
                $itemArray[] = $oItem;
                
                foreach ($changeListResults as $cItem) {
                    $itemInList = explode(',', $cItem['objects']);
                    $itemInList2 = explode(',', $cItem['promotedobjects']);
                    
                    if (in_array($oItem['o_id'], $itemInList) || in_array($oItem['o_id'], $itemInList2)) {
                        if ($cItem['promoted'] === null || $cItem['promoted'] == 0) {
                            unset($object[$key]);
                            break;
                        } else {
                            if ($oItem['o_modificationDate'] <= $cItem['promotionDate']) {
                                unset($object[$key]);
                                break;
                            }
                        }
                    }
                }
            }
            $object = array_values($object);
        }
        
        $ExcptiontotalCount = count($object);
        
        if ($ExcptiontotalCount < $limit || $exportFlag) {
            $offset = 0;
            $limit = $ExcptiontotalCount;
        }
        $steps = $limit + $offset;
        for ($i = $offset; $i < $steps; $i ++) {
            if (! isset($object[$i]))
                break;
            $data[$i]['modificationDate'] = $object[$i]['o_modificationDate'];
            $data[$i]['id'] = $object[$i]['o_id'];
            $data[$i]['key'] = $object[$i]['o_key'];
            $data[$i]['type'] = $object[$i]['o_className'];
            $data[$i]['fullpath'] = $object[$i]['o_path'] . $object[$i]['o_key'];
            $data[$i]['published'] = $object[$i]['o_published'];
            $usr = User::getById($object[$i]['o_userModification']);
            $data[$i]['user'] = $usr->name;
            
            if ($exportFlag) {
                date_default_timezone_set($timezone);
                $data[$i]['modificationDate'] = date('Y-m-d H:i:s', $object[$i]['o_modificationDate']);
            }
        }
        $data = array_values($data);
        // if export flag is 1, then export else return data in json format
        if ($exportFlag) {
            // create csv
            if (! empty($data)) {
                $columns = array_keys($data[0]);
                foreach ($columns as $key => $value) {
                    $columns[$key] = '"' . $value . '"';
                }
                
                $csv = implode(";", $columns) . "\r\n";
                foreach ($data as $o) {
                    foreach ($o as $key => $value) {
                        
                        // clean value of evil stuff such as " and linebreaks
                        if (is_string($value)) {
                            $value = strip_tags($value);
                            $value = str_replace('"', '', $value);
                            $value = str_replace("\r", "", $value);
                            $value = str_replace("\n", "", $value);
                            $o[$key] = '"' . $value . '"';
                        }
                    }
                    
                    $csv .= implode(",", $o) . "\r\n";
                }
            }
            
            header("Content-type: text/csv");
            header("Content-Disposition: attachment; filename=\"export.csv\"");
            echo $csv;
            exit();
        }else {
            $this->_helper->json(array(
                "success" => true,
                "updatedobjects" => ($data),
                "total" => $ExcptiontotalCount
            ));
        }
    }

    /**
     * Call Pimcore RestFul API
     * <code>
     * <strong>
     * $restApiName = "object-list";
     * $param = array(
     * "condition" => ' o_path="/routes/" AND o_key="mdpc-kjfk" '
     * );
     * $result = $this->callRestFulApi($restApiName, $param);
     * </strong>
     * </code>
     *
     * @access private
     * @param string $restApiName            
     * @param array $param            
     * @return mixed
     */
    private function callRestFulApi($restApiName = "object/id/1", $param = array(), $userApiKey = "")
    {
        try {
            
            $config = $this->getCurlConfig();
            
            if ($config) {
                
                // setup the curl connection
                $adapter = new Zend_Http_Client_Adapter_Curl();
                $adapter->setConfig($config->curl->options->toArray());
                
                // make url for restFul API Request
                
                $url = $this->getEnvUrl();
                
                if (strpos($restApiName, '/plugin') === 0) {
                    $url = str_replace('/webservice/rest/', '', $url);
                }
                
                $restFulUrl = $url . $restApiName;
                
                // instantiate the http client and add set the adapter
                $client = new Zend_Http_Client($restFulUrl);
                $client->setAdapter($adapter);
                
                if (! empty($userApiKey)) {
                    // API key
                    $apiKey = array(
                        "apikey" => $userApiKey
                    );
                } else {
                    // API key
                    $apiKey = array(
                        "apikey" => $this->getEnvApiKey()
                    );
                }
                
                // add the get parameters
                $client->setParameterGet($apiKey);
                
                if (is_array($param) && count($param) > 0) {
                    // add our get parameters to the request
                    $client->setParameterGet($param);
                }
                // if($restApiName != 'user' && $restApiName != 'asset-list' ){ var_dump($restFulUrl); die; }
                // perform the get, and get the response
                $response = $client->request(Zend_Http_Client::GET);
                
                if ($response->getStatus() == 200) {
                    
                    // decode object data into array
                    $data = json_decode($response->getBody(), true);
                    
                    return $data;
                }
                return $response->getBody();
            }
            
            // check the response for success
        } catch (Zend_Http_Client_Adapter_Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
        } catch (Zend_Http_Client_Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
        }
    }

    /**
     *
     * @param unknown $param            
     * @param string $restApiName            
     * @return mixed|string
     */
    private function callRestFulApiPost($param = array(), $restApiName = "object")
    {
        try {
            
            $config = $this->getCurlConfig();
            
            if ($config) {
                
                $configuration = $config->curl->options->toArray();
                
                // setup the curl connection
                $adapter = new Zend_Http_Client_Adapter_Curl();
                $adapter->setConfig($configuration);
                
                // make url for restFul API Request
                $url = $this->getEnvUrl();
                
                if (strpos($restApiName, '/plugin') === 0) {
                    $url = str_replace('/webservice/rest/', '', $url);
                }
                
                $restFulUrl = $url . $restApiName . '?apikey=' . $this->getEnvApiKey();
                // instantiate the http client and add set the adapter
                $client = new Zend_Http_Client($restFulUrl);
                $client->setAdapter($adapter);
                
                $param = json_encode($param);
                $_headers = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Content-Length: ' . strlen($param)
                );
                
                $client->setHeaders($_headers);
                // add our post parameters to the request
                $client->setRawData($param, Zend_Http_Client::ENC_URLENCODED);
                
                // perform the post, and get the response
                $response = $client->request(Zend_Http_Client::POST);
                $request = $client->getLastRequest();
                
                if ($response->getStatus() == 200) {
                    
                    // decode object data into array
                    $data = json_decode($response->getBody(), true);
                    return $data;
                }
                
                $body = $response->getBody();
                
                $json = json_decode($body, true);
                if (isset($json['success'])) {
                    return $json;
                } else {
                    return $response->getBody();
                }
            }
            
            // check the response for success
        } catch (Zend_Http_Client_Adapter_Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
        } catch (Zend_Http_Client_Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
        }
    }

    /**
     *
     * @param unknown $param            
     * @param string $restApiName            
     * @return mixed|string
     */
    private function callRestFulApiPostGzip($param, $restApiName = "object")
    {
        try {
            
            $config = $this->getCurlConfig();
            
            if ($config) {
                
                $configuration = $config->curl->options->toArray();
                
                // setup the curl connection
                $adapter = new Zend_Http_Client_Adapter_Curl();
                $adapter->setConfig($configuration);
                
                // make url for restFul API Request
                $url = $this->getEnvUrl();
                
                if (strpos($restApiName, '/plugin') === 0) {
                    $url = str_replace('/webservice/rest/', '', $url);
                }
                
                $restFulUrl = $url . $restApiName . '?apikey=' . $this->getEnvApiKey();
                // instantiate the http client and add set the adapter
                $client = new Zend_Http_Client($restFulUrl);
                $client->setAdapter($adapter);
                
                $_headers = array(
                    'Content-Type: text/xml;charset=UTF-8',
                    'Accept-Encoding: gzip',
                    'Content-Encoding: gzip'
                );
                $client->setHeaders($_headers);
                // add our post parameters to the request
                $client->setRawData($param, Zend_Http_Client::ENC_URLENCODED);
                
                // perform the post, and get the response
                $response = $client->request(Zend_Http_Client::POST);
                $request = $client->getLastRequest();
                
                // Log response
                PromoteLogger::logMessage("Pre-Status");
                PromoteLogger::logMessage($response);
                if ($response->getStatus() == 200) {
                    
                    // Log response
                    PromoteLogger::logMessage($response->getBody());
                    // decode object data into array
                    $data = json_decode($response->getBody(), true);
                    // Log response
                    PromoteLogger::logMessage($data);
                    return $data;
                }
                
                $body = $response->getBody();
                // Log response
                PromoteLogger::logMessage("Post-Status");
                PromoteLogger::logMessage($body);
                
                $json = json_decode($body, true);
                if (isset($json['success'])) {
                    return $json;
                } else {
                    return $response->getBody();
                }
            }
            
            // check the response for success
        } catch (Zend_Http_Client_Adapter_Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
        } catch (Zend_Http_Client_Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
        } catch (\Exception $e) {
            PromoteLogger::logMessage($e->getMessage());
        }
    }

    /**
     * Add objects to promote list.
     */
    public function addToPromoteAction()
    {
        $objectIdStr = "";
        $objectIdsArr = json_decode($this->getParam('ids'), true);
        $plId = json_decode($this->getParam('pl'));
        
        if (is_array($objectIdsArr) && count($objectIdsArr) > 0)
            foreach ($objectIdsArr as $key => $id) {
                $newObjectArr[] = Object::getById($id);
            }
        
        $objectPromoteList = Object\PromoteList::getById($plId);
        $oldObjectArr = $objectPromoteList->getObjects();
        
        $resultObjects = array_merge($newObjectArr, $oldObjectArr);
        
        if (is_array($resultObjects) && count($resultObjects) > 0) {
            $objectPromoteList->setObjects($resultObjects);
            $objectPromoteList->save();
        }
        
        $this->_helper->json(array(
            "success" => true,
            "promoteList" => $objectPromoteList->o_key
        ));
    }
}
