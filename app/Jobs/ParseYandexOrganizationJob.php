<?php

namespace App\Jobs;

use App\Actions\ParseYandexOrganizationAction;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ParseYandexOrganizationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;
    public int $tries = 1;
    public int $uniqueFor = 1200;

    public function __construct(
        public int $organizationId
    ) {}

    public function uniqueId(): string
    {
        return 'parse-yandex-organization-' . $this->organizationId;
    }

    public function handle(ParseYandexOrganizationAction $action): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if (! $organization) {
            return;
        }

        $organization->update([
            'parse_status' => 'processing',
            'parse_error' => null,
        ]);

        $action->handle($organization->fresh());
    }

    public function failed(Throwable $exception): void
    {
        Organization::query()
            ->whereKey($this->organizationId)
            ->update([
                'parse_status' => 'failed',
                'parse_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
    }
}
