<?php

namespace App\Utils;

use Illuminate\Support\LazyCollection;
use InvalidArgumentException;

class ReviewCsvExporter
{
    /** @param resource $stream @param LazyCollection<int, array{id:int,rating:int,body:string,source:string,reviewed_at:string,customer_name:string,customer_phone:string,visit_at:string}> $reviews */
    public function write(mixed $stream, LazyCollection $reviews): void
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('A writable CSV stream is required.');
        }

        fputcsv($stream, ['Review ID', 'Customer', 'WhatsApp phone', 'Rating', 'Feedback', 'Source', 'Reviewed at (UTC)', 'Related visit (UTC)'], ',', '"', '');
        foreach ($reviews as $review) {
            fputcsv($stream, [
                $review['id'],
                $this->spreadsheetSafe((string) $review['customer_name']),
                $this->spreadsheetSafe((string) $review['customer_phone']),
                $review['rating'],
                $this->spreadsheetSafe((string) $review['body']),
                $this->spreadsheetSafe((string) $review['source']),
                $review['reviewed_at'],
                $review['visit_at'],
            ], ',', '"', '');
        }
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
