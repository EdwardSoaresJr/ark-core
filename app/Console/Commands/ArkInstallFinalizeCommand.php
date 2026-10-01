<?php

namespace App\Console\Commands;

use App\Ark\Install\CompleteInstallationAction;
use App\Ark\Install\PendingInstallPayload;
use Illuminate\Console\Command;

/**
 * Background (or CLI) finalize for first-run install. Reads PendingInstallPayload.
 */
class ArkInstallFinalizeCommand extends Command
{
    protected $signature = 'ark:install-finalize';

    protected $description = 'Complete a pending first-run install from the web wizard payload.';

    public function handle(CompleteInstallationAction $action): int
    {
        $input = PendingInstallPayload::read();
        if ($input === null) {
            $this->error('No pending install payload.');

            return self::FAILURE;
        }

        try {
            $result = $action->execute($input);
            if (! ($result['ok'] ?? false)) {
                $this->error($result['message'] ?? 'Installation failed.');

                return self::FAILURE;
            }

            $this->info($result['message'] ?? 'ARK is ready.');

            return self::SUCCESS;
        } finally {
            PendingInstallPayload::clear();
        }
    }
}
