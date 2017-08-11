<?php
namespace attr;
use Exception;
/**
 * 日志类
 * 需要手动创建日志目录,默认日志文件名是application.log。
 *
 * 设置日志目录 Log::getInstance()->setLogPath(__DIR__.'/logs');
 *
 * @author    wxxiong@gmail.com
 * @version   v1.2
 */
class Log
{

    /**
     *  代表发生了最严重的错误，会导致整个服务停止（或者需要整个服务停止）。
     *  简单地说就是服务死掉了。
     * @var string
     */
    const LEVEL_FATAL = 'fatal';

    /**
     *  代表发生了必须马上处理的错误。此类错误出现以后可以允许程序继续运行，
     *  但必须马上修正，如果不修正，就会导致不能完成相应的业务。
     * @var string
     */
    const LEVEL_ERROR   = 'error';

    /**
     * 发生这个级别问题时，处理过程可以继续，但必须对这个问题给予额外关注。
     * @var string
     */
    const LEVEL_WARN = 'warn';

    /**
     *  此输出级别常用语业务事件信息。例如某项业务处理完毕，
     *  或者业务处理过程中的一些信息。
     * @var string
     */
    const LEVEL_INFO    = 'info';

    /**
     * 此输出级别用于开发阶段的调试，可以是某几个逻辑关键点的变量值的输出，
     * 或者是函数返回值的验证等等。业务相关的请用info
     * @var string
     */
    const LEVEL_DEBUG   = 'debug';


    /**
     * @var integer how many messages should be logged before they are flushed to destinations.
     * Defaults to 10,000, meaning for every 10,000 messages
     */
    public $autoFlush = 10000;

    /**
     * @var array log messages
     */
    private $_logs = array();

    /**
     * @var integer number of log messages
     */
    private $_logCount = 0;

    /**
     * @var array log levels for filtering (used when filtering)
     */
    private $_levels;

    /**
     * @var array log categories for filtering (used when filtering)
     */
    private $_categories;

    /**
     * @var integer maximum log file size
     */
    private $_maxFileSize = 1024; // in KB

    /**
     * @var integer number of log files used for rotation
     */
    private $_maxLogFiles = 5;

    /**
     * @var string directory storing log files
     */
    private $_logPath;

    /**
     * @var string log file name
     */
    private $_logFile='application.log';

    /**
     * @var object
     */
    private static $_instance;

    public function __construct(){}

    /**
     * 获取对象
     * @return object
     */
    public static function getInstance(){
        if (!(self::$_instance instanceof self)){
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * @return string directory storing log files. Defaults to application runtime path.
     */
    public function getLogPath()
    {
        if($this->_logPath ===null)
            $this->setLogPath(__DIR__);
        return $this->_logPath;
    }

    /**
     * @param string $value directory for storing log files.
     * @throws CException if the path is invalid
     */
    public function setLogPath($value)
    {
        $this->_logPath=realpath($value);
        if($this->_logPath===false || !is_dir($this->_logPath) || !is_writable($this->_logPath))
            throw new Exception('logPath'."{$value}".' does not point to a valid directory.
			 Make sure the directory exists and is writable by the Web server process.');
    }

    /**
     * @return string log file name. Defaults to 'application.log'.
     */
    public function getLogFile()
    {
        return $this->_logFile;
    }

    /**
     * @param string $value log file name
     */
    public function setLogFile($value)
    {
        $this->_logFile=$value;
    }

    /**
     * @return integer maximum log file size in kilo-bytes (KB). Defaults to 1024 (1MB).
     */
    public function getMaxFileSize()
    {
        return $this->_maxFileSize;
    }

    /**
     * @param integer $value maximum log file size in kilo-bytes (KB).
     */
    public function setMaxFileSize($value)
    {
        if(($this->_maxFileSize=(int)$value)<1)
            $this->_maxFileSize=1;
    }

    /**
     * @return integer number of files used for rotation. Defaults to 5.
     */
    public function getMaxLogFiles()
    {
        return $this->_maxLogFiles;
    }

    /**
     * @param integer $value number of files used for rotation.
     */
    public function setMaxLogFiles($value)
    {
        if(($this->_maxLogFiles=(int)$value)<1)
            $this->_maxLogFiles=1;
    }


    /**
     * warn
     * @param string $value
     * @param string $category
     */
    public static function warn ($value, $category = '')
    {
        return self::write($value, self::LEVEL_WARN, self:: getLogInfo(1, $category));
    }

    /**
     * info
     * @param string $value
     * @param string $category
     */
    public static function info ($value, $category = '')
    {
        return self::write($value, self::LEVEL_INFO,  $category);
    }

    /**
     * error
     * @param string $value
     * @param string $category
     */
    public static function error($value, $category = '')
    {
        return self::write($value, self::LEVEL_ERROR,  $category);
    }

    /**
     * debug
     * @param string $value
     * @param string $category
     */
    public static function debug($value, $category = '')
    {
        return self::write($value, self::LEVEL_DEBUG,  $category);
    }

    /**
     * Logs a message.
     * @param string $message message to be logged
     * @param string $level level of the message (e.g. 'Trace', 'Warning', 'Error').
     * @param string $category category of the message .
     * @see getLogs
     */
    public static function write($message,  $level='info', $category)
    {
        $logInfo = self::getLogInfo(1, $category);
        $obj = self::getInstance();
        $obj->_logs[]=array($message,$level,microtime(true), $logInfo);
        $obj->_logCount++;
        if($obj->autoFlush>0 && $obj->_logCount>=$obj->autoFlush){  //日志行数
            $obj->flush();
        } elseif(intval(memory_get_usage()/1024) >= $obj->_maxFileSize){  //日志内存数
            $obj->flush();
        }
    }

    /**
     * Removes all recorded messages from the memory.
     */
    public function flush()
    {
        $this->onFlush();
        $this->_logs=array();
        $this->_logCount=0;
    }
    /**
     * Raises an <code>onFlush</code> event.
     * @param CEvent $event the event parameter
     * @since 1.1.0
     */
    public function onFlush()
    {
        $this->processLogs($this->_logs);
    }

    /**
     * Formats a log message given different fields.
     * @param string $message message content
     * @param integer $level message level
     * @param string $category message category
     * @param integer $time timestamp
     * @return string formatted message
     */
    protected function formatLogMessage($message, $level, $time, $logInfoArr)
    {
        //获取IP
        $ipstr = '0.0.0.0';
        if (isset($_SERVER["SERVER_ADDR"])){
            $ipstr = $_SERVER["SERVER_ADDR"];
        }
        return $this->udate('y-m-d H:i:s.u', $time)." <".$level.">: [".$logInfoArr['category']."] [".getmypid()."] [".$ipstr."] ".
            $logInfoArr['file']." line (".$logInfoArr['line']."):". $message ." \n";
    }

    /**
     * Saves log messages in files.
     * @param array $logs list of log messages
     * @param unknown $logs
     * @throws Exception
     */
    protected function processLogs($logs)
    {
        $logFile=$this->getLogPath().DIRECTORY_SEPARATOR.$this->getLogFile();
        try {
            if(!is_file($logFile))
            {
                touch($logFile);
            }
            if(filesize($logFile)>$this->getMaxFileSize()*1024)
                $this->rotateFiles();

                $fp=fopen($logFile,'a');
                flock($fp,LOCK_EX);
                foreach($logs as $log)
                    fwrite($fp,$this->formatLogMessage($log[0],$log[1],$log[2],$log[3]));

                    flock($fp,LOCK_UN);
                    fclose($fp);
                } catch (Exception $e) {
                    throw new Exception('log error:'.$e->getMessage());
                }
    }

    /**
     * Rotates log files.
     */
    protected function rotateFiles()
    {
        $file=$this->getLogPath().DIRECTORY_SEPARATOR.$this->getLogFile();
        $max=$this->getMaxLogFiles();
        for($i=$max;$i>0;--$i)
        {
            $rotateFile=$file.'.'.$i;
            if(is_file($rotateFile))
            {
                // suppress errors because it's possible multiple processes enter into this section
                if($i===$max)
                    unlink($rotateFile);
                else
                  rename($rotateFile,$file.'.'.($i+1));
            }
        }
        if(is_file($file))
            rename($file,$file.'.1');
    }

    /**
     * 返回 文件名、行号和函数名
     * @param number $skipLevel
     * @param string $category
     * @return array
     */
    private static function getLogInfo ($skipLevel = 1, $category = '')
    {
        $trace = debug_backtrace();
        $info = array_pop($trace); 
        if(!empty($category))
            $info['category'] = $category;
        else {
            $info['category'] = $info['class'].$info['type'].$info['function'];
        }
        return  $info;
    }

    /**
     *  毫秒
     * @param string $strFormat
     * @param unknown $uTimeStamp
     * @return string
     */
    private function udate($strFormat = 'u', $uTimeStamp = null)
    {
        // If the time wasn't provided then fill it in
        if (is_null($uTimeStamp))
        {
            $uTimeStamp = microtime(true);
        }
        // Round the time down to the second
        $dtTimeStamp = floor($uTimeStamp);
        // Determine the millisecond value
        $intMilliseconds = round(($uTimeStamp - $dtTimeStamp) * 1000000);
        // Format the milliseconds as a 6 character string
        $strMilliseconds = str_pad($intMilliseconds, 6, '0', STR_PAD_LEFT);
        // Replace the milliseconds in the date format string
        // Then use the date function to process the rest of the string
        return date(preg_replace('`(?<!\\\\)u`', $strMilliseconds, $strFormat), $dtTimeStamp);
    }

    public function __destruct(){
        if($this->_logCount > 0)
            $this->flush();
    }
}
