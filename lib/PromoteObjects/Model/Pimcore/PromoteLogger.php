<?php
namespace PromoteObjects\Model\Pimcore;

use Pimcore\File;

class PromoteLogger
{

    private static $priorities = array(
        \Zend_Log::DEBUG,
        \Zend_Log::INFO,
        \Zend_Log::NOTICE,
        \Zend_Log::WARN,
        \Zend_Log::ERR,
        \Zend_Log::CRIT,
        \Zend_Log::ALERT,
        \Zend_Log::EMERG
    );

    /**
     * Log Debuging
     *
     * @param string $logMessage            
     * @param string $loggerType            
     * @param integer $timeTaken            
     * @return void
     */
    public static function logMessage($logMessage, $loggerType = 'DEBUG', $timeTaken = 0)
    {
        try {
            
            $configuration = Configuration::getConfig();
            
            if (! empty($configuration['logger']['filename'])) {
                $file = $configuration['logger']['filename'];
            } else {
                $file = "promote-objects-logs{-date}.log";
            }
            
            $filename = str_replace("{-date}", '-' . date('Y-m-d'), $file);
            
            $priority = 0;
            
            switch ($loggerType) {
                
                case "CRIT":
                    $priority = \Zend_Log::CRIT;
                    break;
                case "ERROR":
                    $priority = \Zend_Log::ERROR;
                    break;
                case "WARN":
                    $priority = \Zend_Log::WARN;
                    break;
                case "INFO":
                    $priority = \Zend_Log::INFO;
                    break;
                case "ALERT":
                    $priority = \Zend_Log::ALERT;
                    break;
                default:
                    $priority = \Zend_Log::DEBUG;
                    break;
            }
            
            if (! empty($configuration['logger']['type'][$loggerType]) && trim($configuration['logger']['type'][$loggerType]) == "true") {
                
                $logFile = PIMCORE_LOG_DIRECTORY . "/{$filename}";
                
                if (! is_file($logFile)) {
                    if (is_writable(dirname($logFile))) {
                        File::put($logFile, "");
                    }
                }
                
                if (is_writable($logFile)) {
                    
                    // check for big logfile, empty it if it's bigger than about 200M
                    if (filesize($logFile) > 200000000) {
                        rename($logFile, $logFile . "-archive");
                        File::put($logFile, "");
                    }
                    if (in_array($priority, self::$priorities)) {
                        
                        $backtrace = debug_backtrace();
                        
                        if (! isset($backtrace[2])) {
                            $call = array(
                                'class' => '',
                                'type' => '',
                                'function' => ''
                            );
                        } else {
                            $call = $backtrace[2];
                        }
                        
                        $call["line"] = $backtrace[1]["line"];
                        
                        if (is_object($logMessage) || is_array($logMessage)) {
                            // special formatting for exception
                            if ($logMessage instanceof \Exception) {
                                $message = $call["class"] . $call["type"] . $call["function"] . "() [" . $call["line"] . "]: [Exception] with message: " . $logMessage->getMessage() . "\n" . "In file: " . $logMessage->getFile() . " on line " . $logMessage->getLine() . "\n" . $logMessage->getTraceAsString();
                            } else {
                                $message = print_r($logMessage, true);
                            }
                        } else {
                            $message = $call["class"] . $call["type"] . $call["function"] . "() [" . $call["line"] . "]: " . $logMessage;
                        }
                        
                        // add the memory consumption
                        $memory = formatBytes(memory_get_usage(), 0);
                        $memory = str_pad($memory, 6, " ", STR_PAD_LEFT);
                        
                        if ($timeTaken >= 0) {
                            $memory = "{$timeTaken}ms |" . $memory;
                        }
                        
                        $message = $memory . " | " . $message;
                        
                        $writerFile = new \Zend_Log_Writer_Stream($logFile);
                        $loggerFile = new \Zend_Log($writerFile);
                        $loggerFile->log($message, $priority);
                    }
                }
            }
        } catch (\Exception $e) {}
    }
}
