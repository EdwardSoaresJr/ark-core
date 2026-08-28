<?php

namespace App\Console\Commands;

use App\Ark\Voice\Lab\VoiceLabRecordScore;
use App\Ark\Voice\Lab\VoiceLabTranscriber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class VoiceLabTranscribeCommand extends Command
{
    protected $signature = 'voice:lab-transcribe
        {path : Path to a 16 kHz mono WAV}
        {--expect= : rr2-lr3 to score the gold rear-pad phrase}';

    protected $description = 'Lab-only: transcribe a handheld WAV. Does not call Dragon or store audio.';

    public function handle(VoiceLabTranscriber $transcriber, VoiceLabRecordScore $score): int
    {
        $path = (string) $this->argument('path');
        if (! File::isReadable($path)) {
            $this->error('Unreadable WAV: '.$path);

            return self::FAILURE;
        }

        try {
            $transcript = $transcriber->transcribeWav((string) File::get($path), basename($path));
        } catch (RuntimeException|Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($transcript);

        if ((string) $this->option('expect') === 'rr2-lr3') {
            $result = $score->score(VoiceLabRecordScore::goldRearPadFacts(), $transcript);
            $this->newLine();
            $this->table(
                ['record_accurate', 'conversational_ok', 'laterality_swap_suspected'],
                [[
                    $result['record_accurate'] ? 'yes' : 'NO',
                    $result['conversational_ok'] ? 'yes' : 'no',
                    $result['laterality_swap_suspected'] ? 'YES' : 'no',
                ]],
            );
            if ($result['laterality_swap_suspected']) {
                $this->warn('Sides/values look swapped. That is a record fail, not 90% accuracy.');
            }
        }

        return self::SUCCESS;
    }
}
