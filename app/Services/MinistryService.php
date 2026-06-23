<?php

namespace App\Services;

use App\Models\Ministry;

class MinistryService
{
    /**
     * Return the single ministry record with zone and church counts.
     */
    public function getCurrent(): Ministry
    {
        return Ministry::current()->loadCount(['zones', 'churches']);
    }

    /**
     * Update the ministry's profile fields and return the refreshed model.
     * The ministry code is intentionally excluded — it is immutable after creation.
     */
    public function update(array $data): Ministry
    {
        $ministry = Ministry::current();
        $ministry->update($data);
        return $ministry->fresh();
    }
}
