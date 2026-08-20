<?php

namespace App\Logging;

use Monolog\Handler\RotatingFileHandler;

class MonthlyDailyRotatingHandler extends RotatingFileHandler
{
    protected function getTimedFilename(): string {
        $file_info = pathinfo($this->filename);
        $extension = $file_info['extension'] ?? 'log';

        return $file_info['dirname'].'/'.date('Y-m').'/'.$file_info['filename'].'-'.date('Y-m-d').'.'.$extension;
    }

    protected function getGlobPattern(): string {
        $file_info = pathinfo($this->filename);
        $extension = $file_info['extension'] ?? 'log';

        return $file_info['dirname'].'/*/'.$file_info['filename'].'-*.'.$extension;
    }
}
