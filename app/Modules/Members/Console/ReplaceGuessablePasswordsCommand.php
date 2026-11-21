<?php

namespace App\Modules\Members\Console;

use App\Modules\Members\Application\Actions\IssueAppPasswords;
use Illuminate\Console\Command;

/**
 * Older passwords were the last 4 digits of the national ID + the last 4 of
 * the mobile number. This finds members still using one and, with --force,
 * gives them a random password by SMS (their app session is ended).
 */
class ReplaceGuessablePasswordsCommand extends Command
{
    protected $signature = 'members:replace-guessable-passwords {--force : Change the passwords and send the SMS (default: only count)}';

    protected $description = 'Find (and with --force replace) member app passwords derived from national ID + mobile';

    public function handle(IssueAppPasswords $passwords): int
    {
        if (! $this->option('force')) {
            $count = $passwords->replaceGuessablePasswords(dryRun: true);
            $this->warn("{$count} members still have a guessable password. Run again with --force to replace them (one SMS each).");

            return self::SUCCESS;
        }

        $count = $passwords->replaceGuessablePasswords();
        $this->info("Replaced {$count} guessable passwords.");

        return self::SUCCESS;
    }
}
