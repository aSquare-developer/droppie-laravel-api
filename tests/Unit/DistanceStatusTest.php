<?php

use App\Enums\DistanceStatus;

it('marks only pending and processing as in progress', function (DistanceStatus $status, bool $inProgress) {
    expect($status->isInProgress())->toBe($inProgress);
})->with([
    [DistanceStatus::Pending, true],
    [DistanceStatus::Processing, true],
    [DistanceStatus::Completed, false],
    [DistanceStatus::Failed, false],
]);

it('exposes labels keyed by stored value', function () {
    expect(DistanceStatus::labels())->toBe([
        'pending' => 'Queued',
        'processing' => 'Calculating',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ]);
});
