<?php

namespace App\Modules\SK_Officials_Records\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * INSERT-only backfill into the two completed-term archive tables.
 * Never drops, truncates, or deletes existing archive / live rows.
 */
class ArchivedTermRecordsBackfillService
{
    /**
     * @return array{officials_inserted: int, federation_inserted: int}
     */
    public function backfill(?int $tenantId = null): array
    {
        if (! Schema::hasTable('archived_sk_official_records')
            || ! Schema::hasTable('archived_sk_federation_records')
            || ! Schema::hasTable('official_terms')
            || ! Schema::hasTable('official_profiles')
            || ! Schema::hasTable('users')
        ) {
            return ['officials_inserted' => 0, 'federation_inserted' => 0];
        }

        $hasFedBarangay = Schema::hasColumn('archived_sk_federation_records', 'barangay_id');
        $hasFedPositionCol = Schema::hasColumn('archived_sk_federation_records', 'federation_position');

        return [
            'officials_inserted' => $this->insertOfficialsRecords($tenantId),
            'federation_inserted' => $this->insertFederationRecords($tenantId, $hasFedBarangay, $hasFedPositionCol),
        ];
    }

    private function insertOfficialsRecords(?int $tenantId): int
    {
        // Use app timezone "today" — DB CURRENT_DATE can lag (UTC) and miss ended terms.
        $today = now()->toDateString();
        $tenantSql = $tenantId !== null ? 'AND u.tenant_id = '.(int) $tenantId : '';

        $sql = <<<SQL
INSERT INTO archived_sk_official_records (
    user_id, official_profile_id, official_term_id, tenant_id, barangay_id,
    first_name, last_name, middle_name, suffix, sex, date_of_birth, age, contact_number,
    position, municipality, province, region, email,
    term_start, term_end, term_status, archived_at, archived_by, created_at, updated_at
)
SELECT
    u.id,
    p.id,
    t.id,
    u.tenant_id,
    u.barangay_id,
    COALESCE(p.first_name, ''),
    COALESCE(p.last_name, ''),
    p.middle_name,
    p.suffix,
    p.sex,
    p.date_of_birth,
    p.age,
    p.contact_number,
    COALESCE(NULLIF(TRIM(p.position), ''), 'Chairperson'),
    COALESCE(NULLIF(TRIM(p.municipality), ''), 'Santa Cruz'),
    COALESCE(NULLIF(TRIM(p.province), ''), 'Laguna'),
    COALESCE(NULLIF(TRIM(p.region), ''), 'IV-A CALABARZON'),
    u.email,
    t.term_start,
    t.term_end,
    CASE
        WHEN UPPER(TRIM(t.status)) IN ('EXPIRED', 'REPLACED') THEN UPPER(TRIM(t.status))
        ELSE 'EXPIRED'
    END,
    NOW(),
    NULL,
    NOW(),
    NOW()
FROM official_terms t
INNER JOIN official_profiles p ON p.id = t.official_profile_id
INNER JOIN users u ON u.id = p.user_id
WHERE LOWER(TRIM(u.role)) = 'sk_official'
  AND t.term_end < ?::date
  AND t.status IN ('ACTIVE', 'EXPIRED', 'REPLACED', 'INACTIVE')
  AND NOT EXISTS (
      SELECT 1
      FROM archived_sk_official_records a
      WHERE a.official_term_id = t.id
  )
  {$tenantSql}
SQL;

        return (int) DB::affectingStatement($sql, [$today]);
    }

    private function insertFederationRecords(?int $tenantId, bool $hasBarangay, bool $hasFederationPosition): int
    {
        $today = now()->toDateString();
        $tenantSql = $tenantId !== null ? 'AND u.tenant_id = '.(int) $tenantId : '';

        $barangayCol = $hasBarangay ? 'barangay_id,' : '';
        $barangaySelect = $hasBarangay ? 'u.barangay_id,' : '';
        $fedPosCol = $hasFederationPosition ? 'federation_position,' : '';
        $fedPosSelect = $hasFederationPosition
            ? 'NULLIF(TRIM(p.federation_position), \'\'),'
            : '';

        $sql = <<<SQL
INSERT INTO archived_sk_federation_records (
    user_id, official_profile_id, official_term_id, tenant_id, {$barangayCol}
    first_name, last_name, middle_name, suffix, sex, date_of_birth, age, contact_number,
    position, {$fedPosCol} municipality, province, region, email,
    term_start, term_end, term_status, archived_at, archived_by, created_at, updated_at
)
SELECT
    u.id,
    p.id,
    t.id,
    u.tenant_id,
    {$barangaySelect}
    COALESCE(p.first_name, ''),
    COALESCE(p.last_name, ''),
    p.middle_name,
    p.suffix,
    p.sex,
    p.date_of_birth,
    p.age,
    p.contact_number,
    COALESCE(
        NULLIF(TRIM(p.federation_position), ''),
        NULLIF(TRIM(p.position), ''),
        'Chairperson'
    ),
    {$fedPosSelect}
    COALESCE(NULLIF(TRIM(p.municipality), ''), 'Santa Cruz'),
    COALESCE(NULLIF(TRIM(p.province), ''), 'Laguna'),
    COALESCE(NULLIF(TRIM(p.region), ''), 'IV-A CALABARZON'),
    u.email,
    t.term_start,
    t.term_end,
    CASE
        WHEN UPPER(TRIM(t.status)) IN ('EXPIRED', 'REPLACED') THEN UPPER(TRIM(t.status))
        ELSE 'EXPIRED'
    END,
    NOW(),
    NULL,
    NOW(),
    NOW()
FROM official_terms t
INNER JOIN official_profiles p ON p.id = t.official_profile_id
INNER JOIN users u ON u.id = p.user_id
WHERE LOWER(TRIM(u.role)) = 'sk_official'
  AND t.term_end < ?::date
  AND t.status IN ('ACTIVE', 'EXPIRED', 'REPLACED', 'INACTIVE')
  AND (
        LOWER(TRIM(COALESCE(p.federation_position, ''))) IN (
            'president', 'vice president', 'secretary', 'treasurer', 'pio', 'sergeant at arms'
        )
        OR LOWER(TRIM(COALESCE(p.position, ''))) IN (
            'president', 'vice president', 'secretary', 'treasurer', 'pio', 'sergeant at arms'
        )
  )
  AND NOT EXISTS (
      SELECT 1
      FROM archived_sk_federation_records a
      WHERE a.official_term_id = t.id
  )
  {$tenantSql}
SQL;

        return (int) DB::affectingStatement($sql, [$today]);
    }
}
