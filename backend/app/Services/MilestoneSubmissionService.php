<?php

namespace App\Services;

use App\Models\ContractMilestone;
use App\Models\MilestoneSubmission;
use App\Models\User;
use App\Support\MarketplaceStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MilestoneSubmissionService
{
    public function submit(ContractMilestone $milestone, User $freelancer, ?string $note, array $files = []): MilestoneSubmission
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($milestone, $freelancer, $note, $files, &$storedPaths) {
                $milestone = ContractMilestone::query()->lockForUpdate()->findOrFail($milestone->id);
                $version = ((int) $milestone->submissions()->max('version')) + 1;
                $submission = $milestone->submissions()->create([
                    'submitted_by' => $freelancer->id,
                    'version' => $version,
                    'note' => $note,
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);

                foreach ($files as $file) {
                    /** @var UploadedFile $file */
                    $extension = $file->extension() ?: 'file';
                    $path = "milestone-deliveries/{$milestone->contract_id}/{$milestone->id}/".Str::uuid().".{$extension}";
                    Storage::disk(MarketplaceStorage::privateDisk())->putFileAs(dirname($path), $file, basename($path));
                    $storedPaths[] = $path;
                    $submission->files()->create([
                        'uploaded_by' => $freelancer->id,
                        'original_name' => $file->getClientOriginalName(),
                        'storage_path' => $path,
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $file->getSize(),
                    ]);
                }

                $milestone->update(['status' => 'submitted', 'submitted_at' => now()]);

                return $submission->load(['files', 'submitter']);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk(MarketplaceStorage::privateDisk())->delete($path);
            }

            throw $exception;
        }
    }
}
