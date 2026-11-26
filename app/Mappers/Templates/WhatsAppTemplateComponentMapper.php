<?php

namespace App\Mappers\Templates;

use Illuminate\Validation\ValidationException;

class WhatsAppTemplateComponentMapper
{
    /** @return list<string> */
    public function variablesIn(string $body): array
    {
        preg_match_all('/{{\s*([a-z_]+)\s*}}/', $body, $matches);
        $variables = $matches[1];
        $allowed = config('automation.allowed_template_variables', []);

        foreach ($variables as $variable) {
            if (! in_array($variable, $allowed, true)) {
                throw ValidationException::withMessages(['body' => "The template variable {{ {$variable} }} is not supported."]);
            }
        }

        return $variables;
    }

    /** @param array<string, string> $examples @return list<array<string, mixed>> */
    public function toMetaComponents(string $body, array $examples): array
    {
        $variables = $this->variablesIn($body);
        $counter = 0;
        $metaBody = preg_replace_callback('/{{\s*([a-z_]+)\s*}}/', static function (array $matches) use (&$counter): string {
            $counter++;

            return '{{'.$counter.'}}';
        }, $body);

        if ($variables === []) {
            return [['type' => 'BODY', 'text' => $body]];
        }

        $exampleValues = [];
        foreach ($variables as $variable) {
            $example = trim($examples[$variable] ?? '');
            if ($example === '') {
                throw ValidationException::withMessages(['examples.'.$variable => 'Add an example value for each template variable before submitting to Meta.']);
            }
            $exampleValues[] = $example;
        }

        return [[
            'type' => 'BODY',
            'text' => (string) $metaBody,
            'example' => ['body_text' => [$exampleValues]],
        ]];
    }

    /** @param list<string> $variables @param array<string, scalar|null> $values @return list<array<string, mixed>> */
    public function toSendComponents(string $body, array $variables, array $values): array
    {
        preg_match_all('/{{\s*(\d+)\s*}}/', $body, $matches);
        $indices = array_map(static fn (string $index): int => (int) $index, $matches[1]);
        if ($indices === []) {
            return [];
        }

        $uniqueIndices = array_values(array_unique($indices));
        sort($uniqueIndices);
        if ($uniqueIndices !== range(1, count($uniqueIndices)) || count($variables) !== count($uniqueIndices)) {
            throw ValidationException::withMessages(['template' => 'The approved template placeholders do not match the configured variable mapping.']);
        }

        $parameters = [];
        foreach ($variables as $variable) {
            $value = $values[$variable] ?? null;
            if (! is_scalar($value) || trim((string) $value) === '') {
                throw ValidationException::withMessages(['template' => "A value for the {$variable} template variable is unavailable."]);
            }
            $parameters[] = ['type' => 'text', 'text' => (string) $value];
        }

        return [['type' => 'body', 'parameters' => $parameters]];
    }
}
