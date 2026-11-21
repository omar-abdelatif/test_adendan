<?php

namespace App\Modules\Members\Console;

use App\Modules\Members\Application\Actions\IssueAppPasswords;
use Illuminate\Console\Command;

class IssueAppPasswordsCommand extends Command
{
    protected $signature = 'users:generate-password';

    protected $description = 'Give a random app password (sent by SMS) to every member who has none';

    public function handle(IssueAppPasswords $passwords): int
    {
        $count = $passwords->forMembersWithoutPassword();
        $this->info("Passwords issued: {$count}");

        return self::SUCCESS;
    }
}
