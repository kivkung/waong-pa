<?php

namespace App\Console\Commands;

use App\Models\Waongpa\MeetingRound;
use App\Services\Waongpa\Workflow;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class WaongpaTick extends Command
{
    protected $signature = 'waongpa:tick';

    protected $description = 'Advance Waongpa rounds whose configured phase deadlines have passed';

    public function handle(Workflow $workflow): int
    {
        $blocked = 0;
        foreach (MeetingRound::whereNotNull('active_marker')->pluck('id') as $id) {
            try {
                $workflow->syncDue($id);
            } catch (ValidationException $e) {
                $blocked++;
                $this->warn('Round '.$id.': '.implode(' ', array_merge(...array_values($e->errors()))));
            }
        }
        $this->info('Processed rounds; blocked: '.$blocked);

        return self::SUCCESS;
    }
}
