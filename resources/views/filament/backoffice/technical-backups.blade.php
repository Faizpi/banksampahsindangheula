<x-filament-panels::page>
    @if (session('operations_notice'))<div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800" role="status">{{ session('operations_notice') }}</div>@endif
    @if ($canRunBackup)
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="technical-backup-title">
            <h2 id="technical-backup-title" class="text-xl font-semibold text-gray-950">Catat metadata cadangan</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">Formulir ini mencatat lokasi, checksum, ukuran, status, dan masa simpan. Berkas cadangan dibuat melalui prosedur penerapan.</p>
            <form wire:submit="recordBackupMetadata" class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-gray-800">Lokasi basis data<input wire:model="backupDatabaseAlias" placeholder="Contoh: backup-db-20260811" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Lokasi media<input wire:model="backupMediaAlias" placeholder="Contoh: backup-media-20260811" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Checksum basis data (SHA-256)<input wire:model="backupDatabaseSha256" placeholder="64 karakter heksadesimal" required minlength="64" maxlength="64" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Checksum media (SHA-256)<input wire:model="backupMediaSha256" placeholder="64 karakter heksadesimal" required minlength="64" maxlength="64" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Ukuran basis data (byte)<input wire:model="backupDatabaseSizeBytes" inputmode="numeric" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Ukuran media (byte)<input wire:model="backupMediaSizeBytes" inputmode="numeric" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Pertahankan sampai<input wire:model="backupRetentionUntil" type="datetime-local" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Kunci operasi<input wire:model="backupOperatorKey" placeholder="Kunci unik minimal 16 karakter" required minlength="16" maxlength="191" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <div class="sm:col-span-2"><button type="submit" wire:confirm="Catat metadata cadangan dengan lokasi dan retensi yang terlihat? Berkas cadangan tidak dibuat oleh formulir ini." class="min-h-11 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800">Catat metadata cadangan</button></div>
            </form>
        </section>
    @endif

    @if ($canRestoreBackup)
        <section class="mt-6 rounded-xl border border-info-200 bg-info-50 p-5 shadow-sm" aria-labelledby="technical-restore-title">
            <h2 id="technical-restore-title" class="text-xl font-semibold text-gray-950">Verifikasi pemulihan</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-700">Lakukan pada lingkungan terisolasi. Formulir ini hanya mencatat bukti dan hasil, bukan menjalankan pemulihan.</p>
            <form wire:submit="recordRestoreVerification" class="mt-6 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-gray-800">ID cadangan<input wire:model="restoreBackupId" inputmode="numeric" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Alias lingkungan verifikasi<input wire:model="restoreTargetAlias" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Referensi bukti verifikasi<input wire:model="restoreEvidenceReference" required minlength="43" maxlength="43" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Hasil verifikasi<select wire:model="restoreResult" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"><option value="">Pilih hasil</option><option value="passed">Lulus</option><option value="failed">Gagal</option></select></label>
                <div class="sm:col-span-2"><button type="submit" wire:confirm="Catat hasil verifikasi pemulihan untuk cadangan dan lingkungan yang dipilih?" class="min-h-11 rounded-lg bg-sky-700 px-4 text-sm font-semibold text-white hover:bg-sky-800">Simpan hasil verifikasi</button></div>
            </form>
        </section>
    @endif

    @if ($canViewBackups)
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="backup-history-title">
            <h2 id="backup-history-title" class="text-xl font-semibold text-gray-950">Metadata cadangan terakhir</h2>
            @if ($backups->isEmpty())
                <p class="mt-3 text-sm text-gray-600">Belum ada metadata cadangan yang dapat ditampilkan.</p>
            @else
                <div class="mt-4 space-y-3">@foreach ($backups as $backup)<details class="rounded-lg border border-gray-200 p-4"><summary class="cursor-pointer list-none"><span class="font-medium">Cadangan #{{ $backup->id }}</span><span class="ml-2 text-sm text-gray-600">{{ \App\Support\StatusLabel::for($backup->status) }}</span><span class="mt-1 block text-xs text-gray-500">{{ $backup->created_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') ?? 'Tanggal tidak tersedia' }}</span></summary><dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2"><div><dt class="text-gray-600">Basis data</dt><dd>{{ $backup->database_location_alias }} · {{ $backup->humanSize($backup->database_size_bytes) }}</dd></div><div><dt class="text-gray-600">Media</dt><dd>{{ $backup->media_location_alias }} · {{ $backup->humanSize($backup->media_size_bytes) }}</dd></div><div><dt class="text-gray-600">Pemulihan</dt><dd>{{ $backup->restore_verification_result?->value ? \App\Support\StatusLabel::for($backup->restore_verification_result) : 'Belum diuji' }}</dd></div><div><dt class="text-gray-600">Retensi</dt><dd>{{ $backup->retention_until->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</dd></div></dl></details>@endforeach</div>
            @endif
        </section>
    @endif
</x-filament-panels::page>
