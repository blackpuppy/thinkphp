<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2012 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Think;
/**
 * ThinkPHP Portal类
 * @category   Think
 * @package  Think
 * @subpackage  Core
 * @author    liu21st <liu21st@gmail.com>
 */
class Think {

    // 类映射
    private static $_map      = array();

    // 实例化对象
    private static $_instance = array();

    /**
     * 应用程序初始化
     * @access public
     * @return void
     */
    static public function start() {
      // 设定错误和异常处理
      register_shutdown_function(array('Think\Think','fatalError'));
      set_error_handler(array('Think\Think','appError'));
      set_exception_handler(array('Think\Think','appException'));
      // 注册AUTOLOAD方法
      spl_autoload_register(array('Think\Think', 'autoload'));

      // 读取应用模式
      $mode   =   include THINK_PATH.'Conf/Mode/'.APP_MODE.'.php';
      
      // 加载配置文件
      foreach ($mode['config'] as $key=>$file){
          is_numeric($key)?C(include $file):C($key,include $file);
      }

      // 加载核心文件
      foreach ($mode['core'] as $file){
          if(is_file($file)) include $file;
      }

      // 加载别名定义
      foreach($mode['alias'] as $alias){
          self::addMap(is_array($alias)?$alias:include $alias);
      }
            
      // 加载模式系统行为定义
      if(isset($mode['extends'])) {
          C('extends',is_array($mode['extends'])?$mode['extends']:include $mode['extends']);
      }

      // 加载应用行为定义
      if(isset($mode['tags'])) {
          C('tags', is_array($mode['tags'])?$mode['tags']:include $mode['tags']);
      }

      // 加载框架底层语言包
      L(include THINK_PATH.'Lang/'.strtolower(C('DEFAULT_LANG')).'.php');
      
      // 设置系统时区
      date_default_timezone_set(C('DEFAULT_TIMEZONE'));

  	  // 初始化文件存储方式
  	  Storage::connect();

      // 检查项目目录结构 如果不存在则自动创建
      if(!is_dir(RUNTIME_PATH)) {
          // 创建项目目录结构
          require THINK_PATH.'Common/build.php';
      }

      if(APP_DEBUG){
          // 调试模式加载系统默认的配置文件
          C(include THINK_PATH.'Conf/debug.php');
          // 读取调试模式的应用状态
          $status  =  C('APP_STATUS');
          // 加载对应的项目配置文件
          if(is_file(COMMON_PATH.'Conf/'.$status.'.php'))
              // 允许项目增加开发模式配置定义
              C(include COMMON_PATH.'Conf/'.$status.'.php');          
      }

      // 记录加载文件时间
      G('loadTime');
      // 运行应用
      App::run();
    }

    // 注册classmap
    static public function addMap($class, $map=''){
        if(is_array($class)){
            self::$_map = array_merge(self::$_map, $class);
        }else{
            self::$_map[$class] = $map;
        }        
    }

    /**
     * 类库自动加载
     * @param string $class 对象类名
     * @return void
     */
    public static function autoload($class) {
        // 检查是否存在映射
        if(isset(self::$_map[$class])) {
            include self::$_map[$class];
        }else{
          $name     = strstr($class, '\\', true);
          $namespace =    C('AUTOLOAD_NAMESPACE');
          if(isset($namespace[$name])){ // 注册的命名空间
              $path   =   dirname($namespace[$name]) . '/';
          }elseif(is_dir(LIB_PATH.$name)){ // Library目录下面的命名空间自动定位
              $path   =   LIB_PATH;
          }else{ // 模块的命名空间
              $path   =   APP_PATH;
          }
          $filename = $path . str_replace('\\', '/', $class) . EXT;
          if(is_file($filename)) {
              // Win环境下面严格区分大小写
              if (IS_WIN && false === strpos(str_replace('/', '\\', realpath($filename)), $class . EXT)){
                  return ;
              }
              include $filename;
          }
        }
    }

    /**
     * 取得对象实例 支持调用类的静态方法
     * @param string $class 对象类名
     * @param string $method 类的静态方法名
     * @return object
     */
    static public function instance($class,$method='') {
        $identify   =   $class.$method;
        if(!isset(self::$_instance[$identify])) {
            if(class_exists($class)){
                $o = new $class();
                if(!empty($method) && method_exists($o,$method))
                    self::$_instance[$identify] = call_user_func_array(array(&$o, $method));
                else
                    self::$_instance[$identify] = $o;
            }
            else
                self::halt(L('_CLASS_NOT_EXIST_').':'.$class);
        }
        return self::$_instance[$identify];
    }

    /**
     * 自定义异常处理
     * @access public
     * @param mixed $e 异常对象
     */
    static public function appException($e) {
        $error = array();
        $error['message']   =   $e->getMessage();
        $trace              =   $e->getTrace();
        if('E'==$trace[0]['function']) {
            $error['file']  =   $trace[0]['file'];
            $error['line']  =   $trace[0]['line'];
        }else{
            $error['file']  =   $e->getFile();
            $error['line']  =   $e->getLine();
        }
        $error['trace']     =   $e->getTraceAsString();
        Log::record($error['message'],Log::ERR);
        // 发送404信息
        header('HTTP/1.1 404 Not Found');
        header('Status:404 Not Found');
        self::halt($error);
    }

    /**
     * 自定义错误处理
     * @access public
     * @param int $errno 错误类型
     * @param string $errstr 错误信息
     * @param string $errfile 错误文件
     * @param int $errline 错误行数
     * @return void
     */
    static public function appError($errno, $errstr, $errfile, $errline) {
      switch ($errno) {
          case E_ERROR:
          case E_PARSE:
          case E_CORE_ERROR:
          case E_COMPILE_ERROR:
          case E_USER_ERROR:
            ob_end_clean();
            // 页面压缩输出支持
            if(C('OUTPUT_ENCODE')){
                $zlib = ini_get('zlib.output_compression');
                if(empty($zlib)) ob_start('ob_gzhandler');
            }
            $errorStr = "$errstr ".$errfile." 第 $errline 行.";
            if(C('LOG_RECORD')) Log::write("[$errno] ".$errorStr,Log::ERR);
            self::halt($errorStr);
            break;
          case E_STRICT:
          case E_USER_WARNING:
          case E_USER_NOTICE:
          default:
            $errorStr = "[$errno] $errstr ".$errfile." 第 $errline 行.";
            trace($errorStr,'','NOTIC');
            break;
      }
    }
    
    // 致命错误捕获
    static public function fatalError() {
        // 保存日志记录
        if(C('LOG_RECORD')) Log::save();
        if ($e = error_get_last()) {
            switch($e['type']){
              case E_ERROR:
              case E_PARSE:
              case E_CORE_ERROR:
              case E_COMPILE_ERROR:
              case E_USER_ERROR:  
                ob_end_clean();
                self::halt($e);
                break;
            }
        }
    }

    /**
     * 错误输出
     * @param mixed $error 错误
     * @return void
     */
    static public function halt($error) {
        $e = array();
        if (APP_DEBUG || IS_CLI) {
            //调试模式下输出错误信息
            if (!is_array($error)) {
                $trace          = debug_backtrace();
                $e['message']   = $error;
                $e['file']      = $trace[0]['file'];
                $e['line']      = $trace[0]['line'];
                ob_start();
                debug_print_backtrace();
                $e['trace']     = ob_get_clean();
            } else {
                $e              = $error;
            }
            if(IS_CLI){
                exit($e['message'].PHP_EOL.'FILE: '.$e['file'].'('.$e['line'].')'.PHP_EOL.$e['trace']);
            }
        } else {
            //否则定向到错误页面
            $error_page         = C('ERROR_PAGE');
            if (!empty($error_page)) {
                redirect($error_page);
            } else {
                if (C('SHOW_ERROR_MSG'))
                    $e['message'] = is_array($error) ? $error['message'] : $error;
                else
                    $e['message'] = C('ERROR_MESSAGE');
            }
        }
        // 包含异常页面模板
        include C('TMPL_EXCEPTION_FILE');
        exit;
    }
}