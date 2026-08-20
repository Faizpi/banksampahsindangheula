<?php

declare(strict_types=1);

namespace Tests\Feature\Wave9;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Models\DepositItem;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

final class ReportDetailedDepositExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_export_includes_an_item_level_row_for_every_waste_item_with_parent_identity_and_totals(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $firstType = WasteType::factory()->create(['name' => 'Plastik Botol Ekspor']);
        $secondType = WasteType::factory()->create(['name' => 'Kertas Koran Ekspor']);
        $condition = WasteCondition::factory()->create(['name' => 'Bersih Ekspor']);
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-DETAIL-EXPORT',
            'customer_id' => $actor->id,
            'staff_id' => $actor->id,
            'method' => 'loket',
            'occurred_at' => '2026-08-01 10:00:00',
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '4.000',
            'total_value' => 42_000,
            'finalized_at' => '2026-08-01 10:00:00',
        ]);
        foreach ([
            [$firstType, '1.250', 12_500],
            [$secondType, '2.750', 29_500],
        ] as [$type, $weight, $subtotal]) {
            DepositItem::query()->create([
                'deposit_id' => $deposit->id,
                'waste_type_id' => $type->id,
                'waste_condition_id' => $condition->id,
                'weight_kg' => $weight,
                'price_per_unit' => 10_000,
                'subtotal' => $subtotal,
            ]);
        }

        $export = app(ReportExportService::class)->export($actor, 'deposits', [
            'start' => '2026-08-01',
            'end' => '2026-08-02',
        ], 'xlsx');

        self::assertSame(ReportExportStatus::Succeeded, $export->status);
        $parts = $this->xlsxParts((string) Storage::disk('media_private')->get((string) $export->path));
        $sheet = $parts['xl/worksheets/sheet1.xml'];
        foreach (['DEP-DETAIL-EXPORT', (string) $actor->id, 'Plastik Botol Ekspor', 'Kertas Koran Ekspor', 'Bersih Ekspor', '1.250', '2.750', '12500', '29500'] as $expectedValue) {
            self::assertStringContainsString($expectedValue, $sheet);
        }
        self::assertSame(3, substr_count($sheet, '<row '));
        self::assertStringContainsString('<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>', $sheet);
        self::assertStringContainsString('<autoFilter ref="A1:K3"/>', $sheet);
        self::assertStringContainsString('<tableParts count="1">', $sheet);
        self::assertStringContainsString('<c r="E2" s="4"><v>4.000</v></c>', $sheet);
        self::assertStringContainsString('<c r="F2" s="5"><v>42000</v></c>', $sheet);
        self::assertStringContainsString('<c r="B2" s="3"><v>', $sheet);
        self::assertStringNotContainsString('<mergeCells', $sheet);
        self::assertStringContainsString('<table xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" id="1" name="LaporanTable" displayName="LaporanTable" ref="A1:K3"', $parts['xl/tables/table1.xml']);
        self::assertStringContainsString('<sheet name="Laporan" sheetId="1" r:id="rId1"/>', $parts['xl/workbook.xml']);
        self::assertSame(1, substr_count($parts['xl/workbook.xml'], '<sheet '));
    }

    public function test_deposit_export_honors_selected_parent_columns_and_keeps_required_item_schema(): void
    {
        Storage::fake('media_private');
        $actor = $this->userWith('report.view', 'report.export');
        $type = WasteType::factory()->create();
        $condition = WasteCondition::factory()->create();
        $deposit = Deposit::query()->create(['deposit_number' => 'DEP-COLUMN-EXPORT', 'customer_id' => $actor->id, 'staff_id' => $actor->id, 'method' => 'loket', 'occurred_at' => '2026-08-01 10:00:00', 'status' => Deposit::STATUS_FINAL, 'total_weight_kg' => '1.000', 'total_value' => 10_000, 'finalized_at' => '2026-08-01 10:00:00']);
        DepositItem::query()->create(['deposit_id' => $deposit->id, 'waste_type_id' => $type->id, 'waste_condition_id' => $condition->id, 'weight_kg' => '1.000', 'price_per_unit' => 10_000, 'subtotal' => 10_000]);

        $export = app(ReportExportService::class)->export($actor, 'deposits', ['start' => '2026-08-01', 'end' => '2026-08-02'], 'xlsx', ['deposit_number', 'total_value']);

        self::assertSame(ReportExportStatus::Succeeded, $export->status);
        $table = $this->xlsxParts((string) Storage::disk('media_private')->get((string) $export->path))['xl/tables/table1.xml'];
        self::assertStringContainsString('name="Nomor Setoran"', $table);
        self::assertStringContainsString('name="Total Nilai (Rp)"', $table);
        self::assertStringContainsString('name="Jenis Sampah"', $table);
        self::assertStringNotContainsString('name="Status Setoran"', $table);
    }

    /** @return array<string, string> */
    private function xlsxParts(string $xlsx): array
    {
        $path = tempnam(sys_get_temp_dir(), 'report-export-');
        self::assertNotFalse($path);
        file_put_contents($path, $xlsx);
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path) === true);
        $parts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            self::assertIsString($name);
            $content = $zip->getFromIndex($index);
            self::assertIsString($content);
            $parts[$name] = $content;
        }
        $zip->close();
        unlink($path);

        return $parts;
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'detail-export-'.uniqid(), 'description' => 'Detailed deposit export test']);
        foreach ($permissions as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
