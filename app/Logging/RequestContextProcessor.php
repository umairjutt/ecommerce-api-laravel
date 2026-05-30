<?php

namespace App\Logging;

use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Enriches every JSON log line with the current request id so logs are
 * correlatable across a single trace.
 */
class RequestContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = Context::get('request_id');

        if ($requestId !== null) {
            $record->extra['request_id'] = $requestId;
        }

        return $record;
    }
}
