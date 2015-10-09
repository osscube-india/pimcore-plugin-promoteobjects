<?php
namespace PromoteObjects\Model;

use Pimcore\Model\Object;
use Pimcore\Model\User;
use Pimcore\Model\Object\KeyValue;
use Zend\Db\Sql\Ddl\Column\Integer;

class Utility
{

    private static $_instance;

    private $_className = "PromoteList";

    private $_roleName = "PromoteList Owner";

    private $_keyGroupName = "PromoteEnvironments";

    private $_keyReopen = "ReopenPromoteList";

    private $_keyTargetEnvironment = "TargetEnvironment";

    private $_keyPromoteClassesPriority = "PromoteClassesPriority";

    private $_keyFilter = array(
        "PromoteClassesPriority",
        "ReopenPromoteList"
    );

    private $_userId = 0;

    private $_classesPriority = array();

    /**
     *
     * @return the $_className
     */
    public function getClassName()
    {
        return $this->_className;
    }

    /**
     *
     * @param string $_className            
     */
    private function setClassName($_className)
    {
        $this->_className = $_className;
    }

    /**
     *
     * @return the $_userId
     */
    public function getUserId()
    {
        return $this->_userId;
    }

    /**
     *
     * @param number $_userId            
     */
    private function setUserId($_userId)
    {
        $this->_userId = $_userId;
    }

    /**
     *
     * @return the $_roleName
     */
    public function getRoleName()
    {
        return $this->_roleName;
    }

    /**
     *
     * @param string $_roleName            
     */
    private function setRoleName($_roleName)
    {
        $this->_roleName = $_roleName;
    }

    /**
     *
     * @return the $_keyGroupName
     */
    public function getKeyGroupName()
    {
        return $this->_keyGroupName;
    }

    /**
     *
     * @param string $_keyGroupName            
     */
    private function setKeyGroupName($_keyGroupName)
    {
        $this->_keyGroupName = $_keyGroupName;
    }

    /**
     *
     * @return the $_keyReopen
     */
    public function getKeyReopen()
    {
        return $this->_keyReopen;
    }

    /**
     *
     * @param string $_keyReopen            
     */
    private function setKeyReopen($_keyReopen)
    {
        $this->_keyReopen = $_keyReopen;
    }

    /**
     *
     * @return the $_keyTargetEnvironment
     */
    public function getKeyTargetEnvironment()
    {
        return $this->_keyTargetEnvironment;
    }

    /**
     *
     * @param string $_keyTargetEnvironment            
     */
    private function setKeyTargetEnvironment($_keyTargetEnvironment)
    {
        $this->_keyTargetEnvironment = $_keyTargetEnvironment;
    }

    /**
     *
     * @return the $_keyPromoteClassesPriority
     */
    public function getKeyPromoteClassesPriority()
    {
        return $this->_keyPromoteClassesPriority;
    }

    /**
     *
     * @param string $_keyPromoteClassesPriority            
     */
    private function setKeyPromoteClassesPriority($_keyPromoteClassesPriority)
    {
        $this->_keyPromoteClassesPriority = $_keyPromoteClassesPriority;
    }

    /**
     *
     * @return the $_keyFilter
     */
    public function getKeyFilter()
    {
        return $this->_keyFilter;
    }

    /**
     *
     * @param multitype:string $_keyFilter            
     */
    private function setKeyFilter($_keyFilter)
    {
        $this->_keyFilter = $_keyFilter;
    }

    /**
     * Get current class object
     */
    public static function getResources()
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function correctClassname($name)
    {
        $name = preg_replace('/[^a-zA-Z0-9]+/', '', $name);
        $name = preg_replace("/^[0-9]+/", "", $name);
        return $name;
    }

    /**
     * Add class
     */
    public function addClass()
    {
        $class = Object\ClassDefinition::getByName($this->getClassName());
        
        if (empty($class)) {
            
            $class = Object\ClassDefinition::create(array(
                'name' => $this->correctClassname($this->getClassName()),
                'userOwner' => $this->getUserId()
            ));
            
            $class->save();
        }
    }

    /**
     * Import class definition
     */
    public function importClass()
    {
        // copy promote list icon
        $this->copyIcon();
        
        // Add promote list owner role
       // $this->addRole();
        
        // Import class definition
        $class = Object\ClassDefinition::getByName($this->getClassName());
        
        $json = file_get_contents(__DIR__ . "/../Resources/class_PromoteList_export.json");
        
        $array = \Zend_Json::decode($json);
        
        $array['layoutDefinitions']['childs'][0]['childs'][0]['classes'] = []; //$this->getClasses();
        
        $json = \Zend_Json::encode($array);
        
        $success = Object\ClassDefinition\Service::importClassDefinitionFromJson($class, $json);
        
        // Add target environment setting
        $this->addGroup();
    }

    /**
     * Get all classes to set allowed in objects
     *
     * @return multitype:multitype:NULL
     */
    private function getClasses()
    {
        $classesList = new Object\ClassDefinition\Listing();
        $classesList->setOrderKey("name");
        $classesList->setOrder("asc");
        $classes = $classesList->load();
        
        // filter classes
        $tmpClasses = array();
        $i = 1;
        foreach ($classes as $class) {
            
            $className = $class->getName();
            
            if ($className != $this->getClassName()) {
                
                $tmpClasses[] = array(
                    "classes" => $class->getName()
                );
            }
        }
        return $tmpClasses;
    }

    /**
     * Copy icon
     */
    private function copyIcon()
    {
        $sourePath = __DIR__ . "/../Resources/promote.png";
        $targetPath = PIMCORE_DOCUMENT_ROOT . "/website/var/assets/promote.png";
        
        if (! file_exists($targetPath)) {
            
            @copy($sourePath, $targetPath);
        }
    }

    /**
     * Add promote role to promote list
     */
    private function addRole()
    {
        try {
            
            $roleName = $this->getRoleName();
            $role = User\Role::getByName($roleName);
            
            if (empty($role)) {
                $type = "role";
                $className = User\Service::getClassNameForType($type);
                $user = $className::create(array(
                    "parentId" => 0,
                    "name" => $roleName,
                    "password" => "",
                    "active" => "active"
                ));
            }
        } catch (\Exception $e) {}
    }

    /**
     * Add Key/value group configuration to add target environment
     */
    private function addGroup()
    {
        $name = $this->getKeyGroupName();
        $alreadyExist = false;
        $config = KeyValue\GroupConfig::getByName($name);
        
        if (! $config) {
            
            // Add promote group
            $config = new KeyValue\GroupConfig();
            $config->setName($name);
            $config->save();
        }
        
        $this->addpropertyAction();
    }

    /**
     * Get Promote group id
     */
    public function getGroupId()
    {
        $name = $this->getKeyGroupName();
        $config = KeyValue\GroupConfig::getByName($name);
        
        if (! empty($config)) {
            return $config->getId();
        }
        
        return 0;
    }

    /**
     * Add Key configuration to add target environment
     */
    private function addpropertyAction()
    {
        $alreadyExist = false;
        
        if (! $alreadyExist) {
            
            $name = $this->getKeyTargetEnvironment();
            // Add Target environment key
            $targetConfig = KeyValue\KeyConfig::getByName($name);
            if (! $targetConfig) {
                $targetConfig = new KeyValue\KeyConfig();
                $targetConfig->setName($name);
                $targetConfig->setType("text");
                $targetConfig->save();
            }
            
            $reOpen = $this->getKeyReopen();
            // Add reopen key
            $reOpenConfig = KeyValue\KeyConfig::getByName($reOpen);
            
            if (empty($reOpenConfig)) {
                
                $reOpenConfig = new KeyValue\KeyConfig();
                $reOpenConfig->setName($reOpen);
                $reOpenConfig->setType("text");
                $reOpenConfig->save();
            }
            
            $classPriorityKeyName = $this->getKeyPromoteClassesPriority();
            // Add reopen key
            $ClassPriorityConfig = KeyValue\KeyConfig::getByName($classPriorityKeyName);
            
            if (empty($ClassPriorityConfig)) {
                
                $ClassPriorityConfig = new KeyValue\KeyConfig();
                $ClassPriorityConfig->setName($classPriorityKeyName);
                $ClassPriorityConfig->setType("select");
                $ClassPriorityConfig->save();
            }
        }
        
        $this->setProperties();
    }

    /**
     * Set relation between key & group
     */
    private function setProperties()
    {
        $groupConfig = KeyValue\GroupConfig::getByName($this->getKeyGroupName());
        $groupConfig->getId();
        
        // Set target environment group
        $keyName = $this->getKeyTargetEnvironment();
        $targetConfig = KeyValue\KeyConfig::getByName($keyName);
        
        if (empty($targetConfig)) {
            $targetConfig = new KeyValue\KeyConfig();
        }
        
        $targetConfig->setName($keyName);
        $targetConfig->setGroup($groupConfig->getId());
        $targetConfig->save();
        
        // Set reopen key with 'Y' value and group
        $keyReOpenName = $this->getKeyReopen();
        $reOpenConfig = KeyValue\KeyConfig::getByName($keyReOpenName);
        
        if (empty($reOpenConfig)) {
            $reOpenConfig = new KeyValue\KeyConfig();
        }
        
        $reOpenConfig->setName($keyReOpenName);
        $reOpenConfig->setGroup($groupConfig->getId());
        
        $reOpenConfig->setDescription('Y');
        $reOpenConfig->save();
        
        // Set Class priority with group
        $classPriorityKeyName = $this->getKeyPromoteClassesPriority();
        $classPriorityConfig = KeyValue\KeyConfig::getByName($classPriorityKeyName);
        
        if (empty($classPriorityConfig)) {
            $classPriorityConfig = new KeyValue\KeyConfig();
        }
        $classes = $this->getExistingClasses();
        $classPriorityConfig->setName($classPriorityKeyName);
        $classPriorityConfig->setPossibleValues($classes);
        $classPriorityConfig->setGroup($groupConfig->getId());
        
        $classPriorityConfig->setDescription('Set classes priority to promote objects on target environment.');
        $classPriorityConfig->save();
    }

    /**
     * Get key value configuration for classes priority
     */
    private function getClassesPriorityKeyConfig()
    {
		
        if (empty($this->_classesPriority)) {
            
            $classPriorityKeyName = $this->getKeyPromoteClassesPriority();
            $classPriorityConfig = KeyValue\KeyConfig::getByName($classPriorityKeyName);
            
            if (! empty($classPriorityConfig->possiblevalues)) {
                
                $classPriority = json_decode($classPriorityConfig->possiblevalues, true);
               
                $classesWithPriority = array();
                foreach ($classPriority as $index => $row) {
                    $this->_classesPriority[$row['key']] = $row['value'];
                }
                
                 asort($this->_classesPriority);
            }
        }
      
        return $this->_classesPriority;
    }

    /**
     * Get Class priority
     * 
     * @param Object $object            
     */
    public static function getClassesPriority($object)
    {
	    $classesPriority = self::getResources()->getClassesPriorityKeyConfig();
        
        foreach ($classesPriority as $class => $priority) {
            
            if ($object->o_className == $class) {
                
                if ($object->o_type == "variant") {
					
                    return (string)($priority + .5);
                    break;
                }
                
               return (string)$priority ;
               break;
           }
       }
       return null;
    }

    private function getExistingClasses()
    {        
       
        $db = \Pimcore\Resource\Mysql::get();
        $data = $db->fetchAll('SELECT name FROM classes WHERE name <> ?',$this->getClassName());
        $options = array();
        foreach ($data as $cls){            
            array_push($options, array("key" => $cls['name'], "value" => "")); 
        }
        
        return json_encode($options);
    }
    
}
