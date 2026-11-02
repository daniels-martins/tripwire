<?php

namespace App\Services\Providers;

/**
 * Lets a provider say why it could not quote, instead of the caller writing
 * one flat "No quote returned" for every cause. A corridor nobody maps and an
 * upstream that is down are different findings, and the reliability page can
 * only separate them if the reason survives the null.
 */
trait ReportsFailureReason
{
    private ?string $failureReason = null;

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    /** Record the reason and hand the caller the null it expects. */
    protected function fail(string $reason): null
    {
        $this->failureReason = $reason;

        return null;
    }
}
