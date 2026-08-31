<?php

namespace App\Services;

use App\Models\Abyip;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AbyipApprovalProvisioningService
{
    /** @var list<string> */
    private const YOUTH_PROGRAM_LETTERS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];

    public function provision(Abyip $document): void
    {
        if ($document->barangay_id === null) {
            return;
        }

        $this->seedProgramDurations($document);
        $this->notifyBarangayOfficials($document);
    }

    private function seedProgramDurations(Abyip $document): void
    {
        if (! Schema::hasTable('abyip_program_durations')) {
            return;
        }

        $barangayId = (int) $document->barangay_id;
        $fiscalYear = (int) ($document->fiscal_year ?? now()->year);
        $defaultStart = sprintf('%d-01-01', $fiscalYear);
        $defaultEnd = sprintf('%d-12-31', $fiscalYear);
        $now = now();

        $programs = Abyip::query()
            ->where('document_id', $document->id)
            ->where(function ($query): void {
                $query->where('row_type', 'youth_program')
                    ->orWhereIn('code', self::YOUTH_PROGRAM_LETTERS);
            })
            ->get(['id', 'implementation_start', 'implementation_end']);

        foreach ($programs as $program) {
            $implStart = $program->implementation_start ?? null;
            $implEnd = $program->implementation_end ?? null;
            $start = $implStart ? (is_string($implStart) ? substr($implStart, 0, 10) : $implStart->format('Y-m-d')) : $defaultStart;
            $end = $implEnd ? (is_string($implEnd) ? substr($implEnd, 0, 10) : $implEnd->format('Y-m-d')) : $defaultEnd;

            if ($end < $start) {
                [$start, $end] = [$defaultStart, $defaultEnd];
            }

            DB::table('abyip_program_durations')->updateOrInsert(
                [
                    'barangay_id' => $barangayId,
                    'abyip_program_id' => $program->id,
                ],
                [
                    'start_date' => $start,
                    'end_date' => $end,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    private function notifyBarangayOfficials(Abyip $document): void
    {
        if (! Schema::hasTable('sk_officials_notifications')) {
            return;
        }

        $barangayId = (int) $document->barangay_id;
        $fiscalYear = (int) ($document->fiscal_year ?? now()->year);
        $barangayName = $document->barangay?->name ?? 'your barangay';
        $now = now();

        $officials = User::query()
            ->where('barangay_id', $barangayId)
            ->where('role', User::ROLE_SK_OFFICIAL)
            ->where('status', User::STATUS_ACTIVE)
            ->get(['id']);

        foreach ($officials as $official) {
            DB::table('sk_officials_notifications')->insert([
                'user_id' => $official->id,
                'category' => 'program',
                'title' => 'ABYIP Approved',
                'body' => "SK Federation approved your ABYIP for CY {$fiscalYear}. Programs are now available in Programs and Program Management.",
                'action_url' => '/programs',
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
