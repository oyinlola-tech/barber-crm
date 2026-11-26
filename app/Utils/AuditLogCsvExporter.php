<?php

namespace App\Utils;

use Illuminate\Support\LazyCollection;
use InvalidArgumentException;

class AuditLogCsvExporter
{
    /** @param resource $stream @param LazyCollection<int, array{action:string,resource:string,resource_id:string,actor:string,ip_address:string,created_at:string}> $logs */
    public function write(mixed $stream, LazyCollection $logs): void
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('A writable CSV stream is required.');
        }

        fputcsv($stream, ['Action', 'Actor', 'Resource', 'Resource ID', 'IP address', 'Created at (UTC)'], ',', '"', '');
        foreach ($logs as $log) {
            fputcsv($stream, [
                $this->spreadsheetSafe((string) $log['action']),
                $this->spreadsheetSafe((string) $log['actor']),
                $this->spreadsheetSafe((string) $log['resource']),
                $log['resource_id'],
                $this->spreadsheetSafe((string) $log['ip_address']),
                $log['created_at'],
            ], ',', '"', '');
        }
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
