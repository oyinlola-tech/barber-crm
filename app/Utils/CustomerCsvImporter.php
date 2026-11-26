<?php

namespace App\Utils;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CustomerCsvImporter
{
    private const MAX_ROWS = 500;

    /** @return list<array<string, string|null>> */
    public function parse(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new RuntimeException('The uploaded CSV file could not be read.');
        }

        try {
            $headers = fgetcsv($stream);
            if (! is_array($headers)) {
                throw ValidationException::withMessages(['file' => 'The CSV file is empty.']);
            }

            $headers = array_map(static function (mixed $header): string {
                $value = strtolower(trim((string) $header));

                return ltrim($value, "\xEF\xBB\xBF");
            }, $headers);
            $required = ['first_name', 'phone'];
            $allowed = ['first_name', 'last_name', 'phone', 'email', 'date_of_birth', 'notes'];
            if (array_diff($required, $headers) !== [] || array_diff($headers, $allowed) !== [] || count($headers) !== count(array_unique($headers))) {
                throw ValidationException::withMessages([
                    'file' => 'CSV headers must include first_name and phone. Optional headers: last_name, email, date_of_birth, notes. Do not include duplicate or unknown headers.',
                ]);
            }

            $rows = [];
            while (($values = fgetcsv($stream)) !== false) {
                if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw ValidationException::withMessages(['file' => 'A CSV import can contain no more than 500 customer rows.']);
                }
                if (count($values) !== count($headers)) {
                    throw ValidationException::withMessages(['file' => 'Every CSV row must contain the same number of columns as the header.']);
                }

                $row = [];
                foreach ($headers as $index => $header) {
                    $value = trim((string) $values[$index]);
                    $row[$header] = $value === '' ? null : $value;
                }
                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
