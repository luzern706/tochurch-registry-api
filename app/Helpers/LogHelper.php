<?php

namespace App\Helpers;

class LogHelper
{
    public static function logWrite($log_data, $log_name = null): int
    {
        // 타임존 설정
        date_default_timezone_set('Asia/Seoul');

        $f_name = $log_name ? $log_name . "_" . date("Ymd") . ".log" : date("Ymd") . ".log";
        $f_path = storage_path("logs/web_log/" . $f_name);

        if (!file_exists(dirname($f_path))) {
            mkdir(dirname($f_path), 0777, true);
        }

        $fp = fopen($f_path, 'a+');

        if ($fp) {
            if (is_array($log_data)) {
                fwrite($fp, print_r($log_data, true) . "\r\n");
            } else {
                $log = date("[Y-m-d H:i:s] ") . trim($log_data);
                fwrite($fp, $log . "\n");
            }

            fclose($fp);
        }

        return 0;
    }
}
