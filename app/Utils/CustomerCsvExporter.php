<?php

namespace App\Utils;

use App\Models\Customer;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;

class CustomerCsvExporter
{
    /** @param resource $stream @param LazyCollection<int, Customer> $customers */
    public function write(mixed $stream, LazyCollection $customers): void
    {
        if (! is_resource($stream)) {
            throw new InvalidArgumentException('A writable CSV stream is required.');
        }

        fputcsv($stream, [
            'Customer ID', 'First name', 'Last name', 'WhatsApp phone', 'Email', 'Staff notes',
            'Registered at (UTC)', 'Archived at (UTC)', 'Tags', 'WhatsApp consent', 'Consent recorded at (UTC)', 'Visit count',
        ], ',', '"', '');

        foreach ($customers as $customer) {
            $consent = $customer->consents
                ->where('channel', 'whatsapp')
                ->sortByDesc('id')
                ->first();

            fputcsv($stream, [
                $customer->getKey(),
                $this->spreadsheetSafe((string) $customer->first_name),
                $this->spreadsheetSafe((string) $customer->last_name),
                $this->spreadsheetSafe((string) $customer->phone),
                $this->spreadsheetSafe((string) $customer->email),
                $this->spreadsheetSafe((string) $customer->notes),
                $customer->created_at?->toISOString(),
                $customer->archived_at?->toISOString(),
                $this->spreadsheetSafe($customer->tags->pluck('name')->implode(', ')),
                $consent === null ? 'not recorded' : ($consent->granted ? 'granted' : 'withdrawn'),
                $consent?->recorded_at?->toISOString(),
                $customer->visits_count,
            ], ',', '"', '');
        }
    }

    private function spreadsheetSafe(string $value): string
    {
        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
