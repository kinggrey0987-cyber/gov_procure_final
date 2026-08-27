<?php

namespace App\Console\Commands;

use App\Models\PublicDisclosure;
use App\Models\User;
use App\Services\Executive\PublicDisclosurePackageService;
use Illuminate\Console\Command;
use Throwable;

class RefreshDisclosurePackagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'disclosures:refresh-packages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate and update all existing public disclosure PDF packages with the new Philippine government letterhead and FDP format.';

    /**
     * Execute the console command.
     */
    public function handle(PublicDisclosurePackageService $packageService): int
    {
        $this->info('Starting regeneration of official public disclosure PDF packages...');

        $disclosures = PublicDisclosure::query()->orderBy('disclosure_id')->get();

        if ($disclosures->isEmpty()) {
            $this->warn('No public disclosures found in the database.');
            return Command::SUCCESS;
        }

        $defaultActor = User::query()
            ->whereHas('role', fn ($query) => $query->whereIn('name', ['EXECUTIVE_OFFICER', 'ADMIN', 'SYSTEM_ADMIN']))
            ->first() ?? User::query()->first();

        if (! $defaultActor instanceof User) {
            $this->error('No administrative or executive user found to authenticate generation.');
            return Command::FAILURE;
        }

        $count = 0;
        $failed = 0;

        foreach ($disclosures as $disclosure) {
            try {
                $actor = $disclosure->publisher ?? $defaultActor;
                
                $document = $packageService->ensureOfficialPackageAttachment($disclosure, $actor);
                
                if (empty($disclosure->file_path) || $disclosure->file_path !== $document->file_path) {
                    $disclosure->forceFill([
                        'file_path' => $document->file_path,
                    ])->save();
                }

                $this->line(sprintf(
                    '  <info>[OK]</info> Refreshed Package for Disclosure #%d: "%s" (%s)',
                    (int) $disclosure->disclosure_id,
                    (string) $disclosure->title,
                    (string) $disclosure->status
                ));
                $count++;
            } catch (Throwable $e) {
                $this->error(sprintf(
                    '  [ERROR] Failed to refresh Disclosure #%d: %s',
                    (int) $disclosure->disclosure_id,
                    $e->getMessage()
                ));
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf('Successfully refreshed %d public disclosure package(s). (%d failed)', $count, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
