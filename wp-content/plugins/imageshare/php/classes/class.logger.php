<?php

namespace Imageshare;

class Logger {

    public static function log($log) {
        if (true !== WP_DEBUG) {
            return;
        }

        if (is_array($log) || is_object($log)) {
            error_log(print_r($log, true));
        } else {
            error_log($log);
        }
    }

}

