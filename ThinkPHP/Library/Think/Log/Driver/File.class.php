<?php
// +----------------------------------------------------------------------
// | TOPThink [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011 http://topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace Think\Log\Driver;

class File
{
    protected $config = [
        'log_time_format' => ' c ',
        'log_file_size'   => 2097152,
        'log_path'        => '',
    ];

    // 实例化并传入参数
    public function __construct($config = [])
    {
        $logFileSize   = C('LOG_FILE_SIZE', null, 2097152);
        $defaultConfig = [
            'log_time_format' => ' c ',
            'log_file_size'   => $logFileSize,
            'log_path'        => '',
        ];
        $this->config = array_merge($defaultConfig, $config);
    }

    /**
     * 日志写入接口
     * @access public
     * @param string $log 日志信息
     * @param string $destination  写入目标
     * @return void
     */
    public function write($log, $destination = '')
    {
        $now = date($this->config['log_time_format']);
        if (empty($destination)) {
            $destination = $this->config['log_path'] . date('y_m_d') . '.log';
        }
        // 自动创建日志目录
        $log_dir = dirname($destination);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        //检测日志文件大小，超过配置大小则备份日志文件重新生成
        if (is_file($destination) && floor($this->config['log_file_size']) <= filesize($destination)) {
            rename($destination, dirname($destination) . '/' . time() . '-' . basename($destination));
        }
        error_log(
            "[{$now}] " . getClientIp() .
            ' [' . $_SERVER['REQUEST_METHOD'] . '] ' .
            $_SERVER['REQUEST_URI'] . PHP_EOL .
            "{$log}" . PHP_EOL,
            3,
            $destination
        );
    }
}
