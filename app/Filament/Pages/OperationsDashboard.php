<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditRetentionService;
use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\BackupArtifact;
use App\Domain\Operations\Services\BackupArtifactPair;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Domain\Operations\Services\BackupRequest;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Domain\Operations\Services\OperationalSettingsService;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class OperationsDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Kontrol teknis';

    protected static ?string $title = 'Kontrol teknis';

    protected string $view = 'filament.backoffice.operations-dashboard';

    /** @var array<string, int> */
    public array $settings = [];

    public bool $maintenanceEnabled = false;

    public string $maintenanceReason = '';

    public string $retentionBefore = '';

    public string $retentionResult = '';

    public string $retentionPreviewBefore = '';

    public string $retentionPreviewedAt = '';

    public string $backupDatabaseAlias = '';

    public string $backupDatabaseSha256 = '';

    public string $backupDatabaseSizeBytes = '';

    public string $backupMediaAlias = '';

    public string $backupMediaSha256 = '';

    public string $backupMediaSizeBytes = '';

    public string $backupRetentionUntil = '';

    public string $backupOperatorKey = '';

    public string $restoreBackupId = '';

    public string $restoreTargetAlias = '';

    public string $restoreEvidenceReference = '';

    public string $restoreResult = '';

    public static function canAccess(): bool
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return false;
        }

        foreach (['system.settings.manage', 'system.maintenance', 'backup.view', 'backup.run', 'backup.restore', 'audit.retention.execute'] as $permission) {
            if (app(PermissionChecker::class)->allows($actor, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function mount(): void
    {
        $actor = $this->actor();
        $permissions = app(PermissionChecker::class);
        $this->settings = $permissions->allows($actor, 'system.settings.manage')
            ? app(OperationalSettingsService::class)->values()
            : [];
        $this->maintenanceEnabled = $permissions->allows($actor, 'system.maintenance')
            && app()->maintenanceMode()->active();
    }

    public function saveSettings(): void
    {
        app(OperationalSettingsService::class)->update($this->actor(), [
            'queue_backlog_threshold' => $this->settings['queue_backlog_threshold'] ?? 0,
            'backup_max_age_hours' => $this->settings['backup_max_age_hours'] ?? 0,
        ]);
        $this->settings = app(OperationalSettingsService::class)->values();
    }

    public function toggleMaintenance(): void
    {
        if (trim($this->maintenanceReason) === '') {
            throw ValidationException::withMessages(['maintenanceReason' => 'Alasan perubahan wajib diisi.']);
        }
        app(OperationalSettingsService::class)->setMaintenance($this->actor(), ! $this->maintenanceEnabled, $this->maintenanceReason);
        $this->maintenanceEnabled = app()->maintenanceMode()->active();
        $this->maintenanceReason = '';
    }

    public function previewRetention(): void
    {
        $preview = app(AuditRetentionService::class)->preview($this->actor(), $this->retentionBefore);
        $this->retentionPreviewBefore = $this->retentionBefore;
        $this->retentionPreviewedAt = now()->toIso8601String();
        $this->retentionResult = sprintf('Akan dihapus: %d; dilindungi: %d.', $preview->deletableCount, $preview->protectedCount);
    }

    public function executeRetention(): void
    {
        if ($this->retentionPreviewBefore !== $this->retentionBefore || $this->retentionPreviewedAt === '') {
            throw ValidationException::withMessages(['retentionBefore' => 'Jalankan preview dengan batas tanggal yang sama sebelum retensi.']);
        }

        try {
            $previewedAt = CarbonImmutable::parse($this->retentionPreviewedAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['retentionBefore' => 'Preview retensi tidak valid. Jalankan preview kembali.']);
        }

        if ($previewedAt->addMinutes(10)->isPast()) {
            throw ValidationException::withMessages(['retentionBefore' => 'Preview retensi sudah kedaluwarsa. Jalankan preview kembali.']);
        }

        $count = app(AuditRetentionService::class)->execute($this->actor(), $this->retentionBefore);
        $this->retentionResult = sprintf('Retensi selesai. Baris audit dihapus: %d.', $count);
        $this->retentionPreviewBefore = '';
        $this->retentionPreviewedAt = '';
    }

    public function recordBackupMetadata(): void
    {
        $backup = app(BackupLifecycleService::class)->request(new BackupRequest(
            actor: $this->actor(),
            artifacts: new BackupArtifactPair(
                database: new BackupArtifact($this->backupDatabaseAlias, $this->backupDatabaseSha256, $this->positiveInteger($this->backupDatabaseSizeBytes, 'Ukuran database')),
                media: new BackupArtifact($this->backupMediaAlias, $this->backupMediaSha256, $this->positiveInteger($this->backupMediaSizeBytes, 'Ukuran media')),
            ),
            retentionUntil: $this->parseRetentionUntil(),
            operatorKey: $this->backupOperatorKey,
            correlationId: $this->correlationId(),
        ));

        session()->flash('operations_notice', 'Metadata cadangan tercatat. Penyalinan database dan media tetap dijalankan melalui prosedur penerapan. ID: '.$backup->getKey());
        $this->resetBackupFields();
    }

    public function recordRestoreVerification(): void
    {
        if (! in_array($this->restoreResult, ['passed', 'failed'], true)) {
            throw ValidationException::withMessages(['restoreResult' => 'Pilih hasil verifikasi: lulus atau gagal.']);
        }

        if (! ctype_digit($this->restoreBackupId) || (int) $this->restoreBackupId < 1) {
            throw ValidationException::withMessages(['restoreBackupId' => 'ID backup tidak valid.']);
        }

        $backup = BackupLog::query()->findOrFail((int) $this->restoreBackupId);
        app(BackupLifecycleService::class)->recordRestoreVerification(
            actor: $this->actor(),
            backup: $backup,
            verificationTargetAlias: $this->restoreTargetAlias,
            evidenceReference: $this->restoreEvidenceReference,
            passed: $this->restoreResult === 'passed',
            correlationId: $this->correlationId(),
        );

        session()->flash('operations_notice', 'Hasil verifikasi pemulihan tercatat. Aplikasi tidak menjalankan pemulihan atau menyentuh berkas cadangan.');
        $this->restoreBackupId = '';
        $this->restoreTargetAlias = '';
        $this->restoreEvidenceReference = '';
        $this->restoreResult = '';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $actor = $this->actor();
        $permissions = app(PermissionChecker::class);
        $canInspectHealth = $permissions->allows($actor, 'system.maintenance');
        $backups = $permissions->allows($actor, 'backup.view')
            ? BackupLog::query()->select([
                'id', 'backup_pair_uuid', 'status', 'database_location_alias', 'media_location_alias',
                'database_sha256', 'media_sha256', 'database_size_bytes', 'media_size_bytes',
                'retention_until', 'started_at', 'finished_at', 'restore_tested_at', 'restore_verification_result', 'created_at',
            ])->latest('id')->limit(20)->get()
            : collect();

        return [
            'health' => $canInspectHealth ? app(OperationalHealthService::class)->check()->toArray() : [],
            'backups' => $backups,
            'canManageSettings' => $permissions->allows($actor, 'system.settings.manage'),
            'canManageMaintenance' => $permissions->allows($actor, 'system.maintenance'),
            'canRunBackup' => $permissions->allows($actor, 'backup.run'),
            'canRestoreBackup' => $permissions->allows($actor, 'backup.restore'),
            'canExecuteRetention' => $permissions->allows($actor, 'audit.retention.execute'),
            'canViewBackups' => $permissions->allows($actor, 'backup.view'),
        ];
    }

    private function parseRetentionUntil(): CarbonImmutable
    {
        try {
            $value = CarbonImmutable::parse($this->backupRetentionUntil);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['backupRetentionUntil' => 'Retensi backup tidak valid.']);
        }

        if ($value->isPast()) {
            throw ValidationException::withMessages(['backupRetentionUntil' => 'Retensi backup harus berada di masa depan.']);
        }

        return $value;
    }

    private function positiveInteger(string $value, string $label): int
    {
        if (! ctype_digit($value) || (int) $value < 1) {
            throw ValidationException::withMessages(['backup' => $label.' harus bilangan bulat positif.']);
        }

        return (int) $value;
    }

    private function resetBackupFields(): void
    {
        $this->backupDatabaseAlias = '';
        $this->backupDatabaseSha256 = '';
        $this->backupDatabaseSizeBytes = '';
        $this->backupMediaAlias = '';
        $this->backupMediaSha256 = '';
        $this->backupMediaSizeBytes = '';
        $this->backupRetentionUntil = '';
        $this->backupOperatorKey = '';
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
