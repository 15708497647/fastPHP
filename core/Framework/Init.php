<?php
namespace Core\Framework;
session_start();
class Init 
{
    public static $config;
    public static $route;
    private static $instance;
    private function clone() {}

    private function __construct() 
    {
        
    }

    public static function getInstance()
    {
        if(!self::$instance) {
            return new self();
        }
        return self::$instance;
    }

    public function start(array $config, array $route)
    {
        self::$config = $config;
        self::$route = $route;
        self::checkPHPVersion();
        return self::run($_SERVER['REQUEST_URI']);
    }
    

    /**
     * 执行请求方法
     * @param string $request_uri
     */
    private static function run(string $request_uri)
    {
        $uriArr = parse_url($request_uri);
        $request_uri = str_replace(strtolower(config('suffix')), '', $uriArr['path']);
        $route = self::$route;
        $flag = false;
        foreach($route as $k=>$v) {
            if($k == $request_uri) {
                self::parseControllerAndMethod($route[$k]);
                $flag = true;
                break;
            }
        }
        if(!$flag) {
            self::showErrorTpl('未匹配到路由：'.$request_uri);
        }
    }

    /**
     * 解析uri到控制器+方法
     * @param string $uri
     */
    private static function parseControllerAndMethod(string $uri)
    {
        $tmpArr = explode('@',$uri);
        $method = $tmpArr[1];
        $classFile = APP_ROOT.'/app/Controllers'.$tmpArr[0].'.php';
        self::checkFileExist($classFile);
        $pathArr = array_values(array_filter(explode('/',$tmpArr[0])));
        $classNamespace = 'App\\Controllers';
        foreach($pathArr as $v) {
            $classNamespace .= '\\'.$v;
        }
        $controllerInstance = new $classNamespace();
        if(self::checkFuncExist($controllerInstance, $method)) {
            try{
                self::setMyErrorHandler();//注册错误异常
                $context = [
                    'method'  => $method,
                    'server'  => [
                        'http_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOW',
                        'http_host'       => $_SERVER['HTTP_HOST'] ?? 'UNKNOW',
                        'redirect_status' => $_SERVER['REDIRECT_STATUS'] ?? 'UNKNOW',
                        'server_name'     => $_SERVER['SERVER_NAME'] ?? 'UNKNOW',
                        'server_port'     => $_SERVER['SERVER_PORT'] ?? 'UNKNOW',
                        'request_method'  => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOW',
                        'request_uri'     => $_SERVER['REQUEST_URI'] ?? 'UNKNOW',
                        'query_string'    => $_SERVER['QUERY_STRING'] ?? 'UNKNOW',
                    ]
                ];
                $controllerInstance->context = $context;//保存上下文信息到控制器实例
                $controllerInstance->$method();//执行控制器实例方法   
            }catch(\Exception $e) {
                $file = $e->getFile();
                $line = $e->getLine();
                $message = $e->getMessage();
                $msg = '[\'level\'] : error<br />[\'message\'] : '.$message."<br />['file'] : ".$file."<br />['line'] : ".$line.'<br />[\'info\'] : '.getPHPFileLine($file,$line).'<br />[\'trace\'] : '.$e->getTraceAsString();
                \Core\Driver\Log::getInstance()->error($message.' in file '.$file.' on line '.$line."\r\n[trace]\r\n".$e->getTraceAsString());
                self::showErrorTpl($msg);
            }catch(\Error $e) {
                $file = $e->getFile();
                $line = $e->getLine();
                $message = $e->getMessage();
                $msg = '[\'level\'] : error<br />[\'message\'] : '.$message."<br />['file'] : ".$file."<br />['line'] : ".$line.'<br />[\'info\'] : '.getPHPFileLine($file,$line).'<br />[\'trace\'] : '.$e->getTraceAsString();
                \Core\Driver\Log::getInstance()->error($message.' in file '.$file.' on line '.$line."\r\n[trace]\r\n".$e->getTraceAsString());
                self::showErrorTpl($msg);
            }
            
        }else {
            self::showErrorTpl('class file: '.$classFile.' 不存在 function '.$method.'()');
        }
    }

    /**
     * 检测文件是否存在
     * @param string $file_path
     * @return mixed bool|include file
     */
    private static function checkFileExist(string $file_path)
    {
        if(file_exists($file_path)) {
            return true;
        }else {
            self::showErrorTpl('File: '.$file_path.'不存在 :(');
        }
    }

    /**
     * 检测控制器方法是否存在
     * @param object $controllerInstance 控制器方法实例
     * @param string $func 方法名
     */
    private static function checkFuncExist($controllerInstance, string $func)
    {
        if(method_exists($controllerInstance, $func)) {
            return true;
        }else {
            return false;
        }
    }

    /**
     * 框架层错误error输出
     * @param string $errorMsg 错误信息
     */
    private static function showErrorTpl(string $errorMsg = '')
    {
        if(config('debug') == true) {
            self::assign('errorMsg', $errorMsg);
            include APP_ROOT.'/core/Tpl/error.html';die;
        }else {
            echo $errorMsg;die;
        }
        
    }

    /**
     * 框架层错误warning输出
     * @param string $warningMsg 警告信息
     */
    private static function showWarningTpl(string $warningMsg = '')
    {
        if(config('debug') == true) {
            self::assign('warningMsg', $warningMsg);
            include APP_ROOT.'/core/Tpl/warning.html';die;
        }else {
            echo $warningMsg;die;
        }
        
    }

    /**
     * 框架层错误notice输出
     * @param string $noticeMsg 提示信息
     */
    private static function showNoticeTpl(string $noticeMsg = '')
    {
        if(config('debug') == true) {
            self::assign('noticeMsg', $noticeMsg);
            include APP_ROOT.'/core/Tpl/notice.html';die;
        }else {
            echo $noticeMsg;die;
        }
        
    }
    
    /**
     * debug页赋值
     * @param string $key
     * @param mixed $val 
     */
    private static function assign(string $key, $val)
    {
        if(array($val)){
            $arr["$key"] = $val;
        }else{
            $arr["$key"] = compact($val);
        }
    }

    public static function setMyErrorHandler()
    {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            $msg = '';
            switch ($errno) {
                case E_NOTICE:
                    $msg = '[\'level\'] : notice<br />[\'message\'] : '.$errstr."<br />['file'] : ".$errfile."<br />['line'] : ".$errline.'<br />[\'info\'] : '.getPHPFileLine($errfile,$errline);
                    \Core\Driver\Log::getInstance()->notice($errstr.' in file '.$errfile.' on line '.$errline);
                    self::showNoticeTpl($msg);
                    break;
                case E_ERROR:
                    $msg = '[\'level\'] : error<br />[\'message\'] : '.$errstr."<br />['file'] : ".$errfile."<br />['line'] : ".$errline.'<br />[\'info\'] : '.getPHPFileLine($errfile,$errline);
                    \Core\Driver\Log::getInstance()->error($errstr.' in file '.$errfile.' on line '.$errline);
                    self::showErrorTpl($msg);
                    break;
                case E_WARNING:
                    $msg = '[\'level\'] : warning<br />[\'message\'] : '.$errstr."<br />['file'] : ".$errfile."<br />['line'] : ".$errline.'<br />[\'info\'] : '.getPHPFileLine($errfile,$errline);
                    \Core\Driver\Log::getInstance()->warning($errstr.' in file '.$errfile.' on line '.$errline);
                    self::showWarningTpl($msg);
                    break;
                default:
                    $msg = '[\'level\'] : warning<br />[\'message\'] : '.$errstr."<br />['file'] : ".$errfile."<br />['line'] : ".$errline.'<br />[\'info\'] : '.getPHPFileLine($errfile,$errline);;
                    self::showErrorTpl($msg);
                    break;
            }
            
        });
    }

    private static function checkPHPVersion()
    {
        $version = substr(phpversion(),0,3);
        if($version < 7.1) {
            self::showErrorTpl('当前PHP版本: '.$version.' ;请使用7.1以及更新的版本');
        }
    }
}