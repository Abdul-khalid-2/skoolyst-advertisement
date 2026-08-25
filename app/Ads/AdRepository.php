<?php

namespace App\Ads;

/**
 * AdRepository
 *
 * Owns all query logic for the `ads` table. AdController calls this
 * instead of running queries directly. Other modules (e.g. Admin)
 * must go through this repository's public methods rather than
 * querying the `ads` table themselves.
 *
 * Stub only — methods added alongside core/Database.php (3.2).
 */
class AdRepository
{
    /**
     * Example method only — demonstrates the pattern controllers use
     * (call a repository method, get data back). Replaced by real
     * query methods once the `ads` migration (5.1.d) lands.
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return ['module' => 'Ads', 'status' => 'ok'];
    }
}
