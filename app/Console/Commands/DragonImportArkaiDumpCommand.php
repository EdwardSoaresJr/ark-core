<?php

namespace App\Console\Commands;

use App\Ark\Dragon\Agent\ImportArkaiDragonDumpAction;
use Illuminate\Console\Command;

final class DragonImportArkaiDumpCommand extends Command
{
    protected $signature = 'dragon:import-arkai-dump
        {path=path/to/your-authorized-dump.json : JSON dump from arkai}';

    protected $description = 'Import arkai knowledge documents and durable memories into ARK-hosted Dragon. Does not contact arkai.';

    public function handle(ImportArkaiDragonDumpAction $import): int
    {
        $path = (string) $this->argument('path');
        $resolved = str_starts_with($path, '/') ? $path : base_path($path);
        if (! is_file($resolved)) {
            $this->error("Missing dump [{$resolved}].");

            return self::FAILURE;
        }

        $counts = $import->import($resolved);
        $this->info("Imported sources={$counts['sources']} documents={$counts['documents']} memories={$counts['memories']} superseded={$counts['superseded']}");
        $this->comment('arkai was not contacted. Leave the appliance running until cutover certification.');

        return self::SUCCESS;
    }
}
