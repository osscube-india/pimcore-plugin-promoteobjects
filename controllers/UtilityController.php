<?php
use PromoteObjects\Model\Pimcore;
use Pimcore\Model\Object\AbstractObject;
use Pimcore\Model\Version;
use Pimcore\Tool\Authentication;
use Pimcore\File;
use Pimcore\Tool\Archive;
use Pimcore\Model\Asset;
use PromoteObjects\Model\Pimcore\PromoteLogger;
use Pimcore\Model\User;

class PromoteObjects_UtilityController extends \Pimcore\Controller\Action\Admin
{

    public function getUsersAction()
    {
        $usersData = array(
            array(
                "id" => "All",
                "name" => "All"
            )
        );
        try {
            
            $userList = new User\Listing();
            $userList->setCondition("active = 1");
            $users = $userList->load();
            
            if (! is_array($users) or count($users) !== 1) {
                // throw new \Exception("API key error.");
            }
            
            if (! $users[0]->getApiKey()) {
                // throw new \Exception("Couldn't get API key for user.");
            }
            
            foreach ($users as $user) {
                $usersData[] = array(
                    "id" => $user->getId(),
                    "name" => ucfirst($user->getName())
                );
            }
            
            $this->_helper->json(array(
                "success" => true,
                "users" => $usersData
            ));
        } catch (\Exceptions $e) {
            
            $this->_helper->json(array(
                "success" => false,
                "classType" => $usersData
            ));
        }
    }

    public function getClassesAction()
    {
        $tmpClasses = array(
            array(
                "id" => "All",
                "name" => "All"
            )
        );
        
        try {
            
            $classesList = new Object\ClassDefinition\Listing();
            $classesList->setOrderKey("name");
            $classesList->setOrder("asc");
            $classes = $classesList->load();
            
            $elimiatedClass = array(
                "PromoteList"
            );
            
            foreach ($classes as $class) {
                if (! in_array($class->getName(), $elimiatedClass)) {
                    $tmpClasses[] = array(
                        "id" => $class->getId(),
                        "name" => ucfirst($class->getName())
                    );
                }
            }
            
            $this->_helper->json(array(
                "success" => true,
                "classType" => $tmpClasses,
            ));

        }catch(\Exceptions $e){

            $this->_helper->json(array(
                "success" => false,
                "classType" => $tmpClasses,
            ));
        }
        
    }
  
}
