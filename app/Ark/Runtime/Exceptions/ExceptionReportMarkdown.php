<?php

namespace App\Ark\Runtime\Exceptions;

final class ExceptionReportMarkdown
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function format(array $context): string
    {
        $lines = [
            '# ARK Exception Report',
            '',
            '## Report ID',
            '',
            '`'.($context['report_id'] ?? 'unknown').'`',
            '',
            'Use on the VPS:',
            '',
            '```bash',
            (string) ($context['report_vps_command'] ?? 'php artisan errors:recent --id='.($context['report_id'] ?? '')),
            '```',
            '',
            '## Summary',
            '',
            '- **Environment:** '.strtoupper((string) ($context['environment'] ?? config('app.env'))),
            '- **Exception:** `'.($context['exception_class'] ?? 'Unknown').'`',
            '- **Message:** '.($context['exception_message'] ?: 'No message provided.'),
        ];

        if (filled($context['status_code'] ?? null)) {
            $lines[] = '- **Status:** '.$context['status_code'];
        }

        if (filled($context['report_filename'] ?? null)) {
            $lines[] = '- **Archive file:** `'.$context['report_filename'].'`';
        }

        $lines[] = '';
        $lines[] = '## Request';

        if (filled($context['url'] ?? null)) {
            $lines[] = '- **URL:** `'.($context['method'] ?? 'GET').' '.$context['url'].'`';
        }

        if (filled($context['route'] ?? null)) {
            $lines[] = '- **Route:** `'.$context['route'].'`';
        }

        if (filled($context['user_email'] ?? null)) {
            $lines[] = '- **Staff:** '.$context['user_email'].' (#'.($context['user_id'] ?? '?').')';
        } elseif (filled($context['user_id'] ?? null)) {
            $lines[] = '- **Staff user id:** '.$context['user_id'];
        } else {
            $lines[] = '- **Staff:** guest';
        }

        if (filled($context['ip'] ?? null)) {
            $lines[] = '- **IP:** '.$context['ip'];
        }

        if (filled($context['referer'] ?? null)) {
            $lines[] = '- **Referer:** '.$context['referer'];
        }

        if (filled($context['user_agent'] ?? null)) {
            $lines[] = '- **User agent:** '.$context['user_agent'];
        }

        $input = $context['input'] ?? [];

        if (is_array($input) && $input !== []) {
            $lines[] = '';
            $lines[] = '## Input';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $lines[] = '```';
        }

        $trace = $context['trace'] ?? [];

        if (is_array($trace) && $trace !== []) {
            $lines[] = '';
            $lines[] = '## Trace (first frames)';
            $lines[] = '';
            $lines[] = '```text';

            foreach ($trace as $frame) {
                if (! is_array($frame)) {
                    continue;
                }

                $location = ($frame['file'] ?? 'unknown').':'.($frame['line'] ?? '?');
                $call = ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? '').'()';
                $lines[] = trim($location.' '.$call);
            }

            $lines[] = '```';
        }

        return implode("\n", $lines)."\n";
    }
}
