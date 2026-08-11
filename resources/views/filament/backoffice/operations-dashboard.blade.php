<x-filament-panels::page>
    @if (session('operations_notice'))
        <div class="mb-6 rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-800" role="status">{{ session('operations_notice') }}</div>
    @endif

    <div class="space-y-6">
        <section class="rounded-xl border border-primary-200 bg-primary-950 p-5 text-white shadow-sm sm:p-6" aria-labelledby="technical-control-title">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-primary-200">Administrasi sistem</p>
                    <h2 id="technical-control-title" class="mt-1 text-2xl font-bold">Kontrol teknis sistem</h2>
                    <p class="mt-2 max-w-2xl text-sm text-primary-100">Kelola pengaturan, pemeliharaan, cadangan, verifikasi pemulihan, dan retensi audit sesuai izin.</p>
                </div>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:text-right">
                    <div><dt class="text-primary-200">Lingkungan</dt><dd class="font-bold">{{ app()->environment() }}</dd></div>
                    <div><dt class="text-primary-200">Sistem</dt><dd class="font-bold">{{ $canManageMaintenance ? ($maintenanceEnabled ? 'Pemeliharaan aktif' : 'Berjalan normal') : 'Terbatas' }}</dd></div>
                </dl>
            </div>
        </section>

        <nav aria-label="Bagian kontrol teknis" class="sticky top-16 z-10 overflow-x-auto rounded-lg border border-gray-200 bg-white p-2 shadow-sm">
            <div class="flex min-w-max gap-2 text-sm font-semibold">
                <a href="#health" class="rounded-md px-3 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Kondisi sistem</a>
                @if ($canManageSettings)<a href="#settings" class="rounded-md px-3 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Pengaturan</a>@endif
                @if ($canManageMaintenance)<a href="#maintenance" class="rounded-md px-3 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Pemeliharaan</a>@endif
                @if ($canRunBackup)<a href="#backup" class="rounded-md px-3 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Cadangan</a>@endif
                @if ($canRestoreBackup)<a href="#restore" class="rounded-md px-3 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Verifikasi pemulihan</a>@endif
                @if ($canExecuteRetention)<a href="#retention" class="rounded-md px-3 py-2 text-gray-700 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Retensi audit</a>@endif
            </div>
        </nav>

        <section id="health" class="scroll-mt-32 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="health-title">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div><h2 id="health-title" class="text-lg font-semibold text-gray-950">Kondisi sistem</h2><p class="text-sm text-gray-600">Ringkasan aman tanpa path, payload, checksum, atau rahasia.</p></div>
                @if ($health !== [])<a href="{{ route('operations.health') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-800 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600">Lihat detail kondisi</a>@endif
            </div>
            @if ($health === [])
                <p class="mt-4 text-sm text-gray-600">Izin pemeriksaan sistem tidak tersedia.</p>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">@foreach ($health as $name => $check)<div class="rounded-lg border border-gray-200 p-3"><div class="text-sm font-semibold capitalize text-gray-900">{{ str_replace('_', ' ', $name) }}</div><div class="mt-1 text-sm font-medium text-gray-700">{{ $check['status'] ?? 'Tidak diketahui' }}</div>@if (isset($check['reason']))<div class="mt-1 text-xs text-gray-500">{{ $check['reason'] }}</div>@endif</div>@endforeach</div>
            @endif
        </section>

        @if ($canManageSettings)
            <section id="settings" class="scroll-mt-32 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="settings-title">
                <h2 id="settings-title" class="text-lg font-semibold text-gray-950">Pengaturan</h2><p class="mt-1 text-sm text-gray-600">Rahasia aplikasi, kata sandi, token, dan kredensial tetap berada di lingkungan penerapan.</p>
                <form wire:submit="saveSettings" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-medium text-gray-800">
                        Ambang antrean pekerjaan
                        <input wire:model="settings.queue_backlog_threshold" type="number" min="1" max="8760" required class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm">
                    </label>
                    <label class="block text-sm font-medium text-gray-800">
                        Usia maksimum cadangan terverifikasi (jam)
                        <input wire:model="settings.backup_max_age_hours" type="number" min="1" max="8760" required class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm">
                    </label>
                    <div class="sm:col-span-2">
                        <button type="submit" class="min-h-11 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2">Simpan pengaturan</button>
                    </div>
                </form>
            </section>
        @endif

        @if ($canManageMaintenance)
            <details id="maintenance" class="scroll-mt-32 rounded-xl border border-warning-200 bg-warning-50 p-5 shadow-sm">
                <summary class="cursor-pointer list-none text-lg font-semibold text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Pemeliharaan <span class="ml-2 text-sm font-normal text-gray-700">Buka untuk mengubah status aplikasi.</span></summary>
                <div class="mt-4">
                    <p class="text-sm text-gray-700">Status saat ini: <strong>{{ $maintenanceEnabled ? 'aktif' : 'nonaktif' }}</strong>. Pengguna dapat kehilangan akses selama pemeliharaan.</p>
                    <form wire:submit="toggleMaintenance" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="block flex-1 text-sm font-medium text-gray-800">
                            Alasan perubahan
                            <textarea wire:model="maintenanceReason" required minlength="10" maxlength="1000" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm"></textarea>
                        </label>
                        <button type="submit" wire:confirm="{{ $maintenanceEnabled ? 'Nonaktifkan pemeliharaan untuk seluruh aplikasi?' : 'Aktifkan pemeliharaan untuk seluruh aplikasi?' }} Pekerjaan operasional dapat terganggu selama perubahan ini." class="min-h-11 rounded-lg {{ $maintenanceEnabled ? 'bg-red-700 hover:bg-red-800' : 'bg-emerald-700 hover:bg-emerald-800' }} px-4 text-sm font-semibold text-white">{{ $maintenanceEnabled ? 'Nonaktifkan pemeliharaan' : 'Aktifkan pemeliharaan' }}</button>
                    </form>
                </div>
            </details>
        @endif

        @if ($canRunBackup)
            <section id="backup" class="scroll-mt-32 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="backup-title"><h2 id="backup-title" class="text-lg font-semibold text-gray-950">Cadangan</h2><p class="mt-1 text-sm text-gray-600">Catat lokasi, checksum, ukuran, status, dan masa simpan. Berkas cadangan dibuat melalui prosedur penerapan.</p><form wire:submit="recordBackupMetadata" class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-gray-800">Lokasi basis data<input id="backup-database-alias" wire:model="backupDatabaseAlias" placeholder="Contoh: backup-db-20260811" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"><span class="mt-1 block text-xs font-normal text-gray-500">Alias lokasi, bukan alamat rahasia.</span></label>
                <label class="block text-sm font-medium text-gray-800">Lokasi media<input id="backup-media-alias" wire:model="backupMediaAlias" placeholder="Contoh: backup-media-20260811" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"><span class="mt-1 block text-xs font-normal text-gray-500">Alias lokasi, bukan alamat rahasia.</span></label>
                <label class="block text-sm font-medium text-gray-800">Checksum basis data (SHA-256)<input wire:model="backupDatabaseSha256" placeholder="64 karakter heksadesimal" required minlength="64" maxlength="64" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Checksum media (SHA-256)<input wire:model="backupMediaSha256" placeholder="64 karakter heksadesimal" required minlength="64" maxlength="64" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Ukuran basis data (byte)<input wire:model="backupDatabaseSizeBytes" placeholder="Contoh: 12400000" required inputmode="numeric" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Ukuran media (byte)<input wire:model="backupMediaSizeBytes" placeholder="Contoh: 34800000" required inputmode="numeric" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Pertahankan sampai<input wire:model="backupRetentionUntil" type="datetime-local" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Kunci operasi<input wire:model="backupOperatorKey" placeholder="Kunci unik minimal 16 karakter" required minlength="16" maxlength="191" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <div class="sm:col-span-2"><button type="submit" wire:confirm="Catat metadata cadangan dengan lokasi dan retensi yang terlihat? Berkas cadangan tidak dibuat oleh formulir ini." class="min-h-11 rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800">Catat metadata cadangan</button></div>
            </form></section>
        @endif

        @if ($canRestoreBackup)
            <details id="restore" class="scroll-mt-32 rounded-xl border border-info-200 bg-info-50 p-5 shadow-sm">
                <summary class="cursor-pointer list-none text-lg font-semibold text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Verifikasi pemulihan <span class="ml-2 text-sm font-normal text-gray-700">Buka untuk mencatat hasil pemeriksaan.</span></summary>
                <div class="mt-4"><p class="text-sm text-gray-700">Lakukan pada lingkungan terisolasi. Formulir ini hanya mencatat bukti dan hasil, bukan menjalankan pemulihan.</p><form wire:submit="recordRestoreVerification" class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-gray-800">ID cadangan<input wire:model="restoreBackupId" placeholder="Contoh: 123" required inputmode="numeric" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Alias lingkungan verifikasi<input wire:model="restoreTargetAlias" placeholder="Contoh: verify-2026-08-11" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Referensi bukti verifikasi<input wire:model="restoreEvidenceReference" placeholder="43 karakter alfanumerik" required minlength="43" maxlength="43" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm font-medium text-gray-800">Hasil verifikasi<select wire:model="restoreResult" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm"><option value="">Pilih hasil</option><option value="passed">Lulus</option><option value="failed">Gagal</option></select></label>
                <div class="sm:col-span-2"><button type="submit" wire:confirm="Catat hasil verifikasi pemulihan untuk cadangan dan lingkungan yang dipilih?" class="min-h-11 rounded-lg bg-sky-700 px-4 text-sm font-semibold text-white hover:bg-sky-800">Simpan hasil verifikasi</button></div>
            </form></div>
            </details>
        @endif

        @if ($canExecuteRetention)
            <details id="retention" class="scroll-mt-32 rounded-xl border border-danger-200 bg-danger-50 p-5 shadow-sm">
                <summary class="cursor-pointer list-none text-lg font-semibold text-gray-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-2">Retensi audit <span class="ml-2 text-sm font-normal text-gray-700">Buka untuk meninjau dan menghapus catatan lama.</span></summary>
                <div class="mt-4">
                    <p class="text-sm text-gray-700">Jalankan pratinjau terlebih dahulu. Bukti operasional yang dilindungi tidak ikut dihapus.</p>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label class="block flex-1 text-sm font-medium text-gray-800">
                            Hapus sebelum tanggal
                            <input wire:model="retentionBefore" type="date" required class="mt-1 block min-h-11 w-full rounded-lg border-gray-300 text-sm shadow-sm">
                        </label>
                        <button wire:click="previewRetention" type="button" class="min-h-11 rounded-lg border border-gray-300 px-4 text-sm font-semibold text-gray-800 hover:bg-gray-50">Pratinjau</button>
                        <button wire:click="executeRetention" type="button" wire:confirm="Jalankan retensi audit berdasarkan pratinjau terbaru? Data yang memenuhi batas akan dihapus dan tidak dapat dipulihkan dari aplikasi." class="min-h-11 rounded-lg bg-red-700 px-4 text-sm font-semibold text-white hover:bg-red-800">Jalankan retensi</button>
                    </div>
                    @if ($retentionResult !== '')
                        <p class="mt-3 text-sm text-gray-700" role="status">{{ $retentionResult }}</p>
                    @endif
                </div>
            </details>
        @endif

        @if ($canViewBackups)
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="backups-title"><h2 id="backups-title" class="text-lg font-semibold text-gray-950">Metadata cadangan terakhir</h2>@if ($backups->isEmpty())<p class="mt-3 text-sm text-gray-600">Belum ada metadata cadangan yang dapat ditampilkan.</p>@else
                <div class="mt-4 space-y-3 md:hidden">@foreach ($backups as $backup)<details class="rounded-lg border border-gray-200 p-4"><summary class="cursor-pointer list-none"><span class="font-medium">Cadangan #{{ $backup->id }}</span><span class="ml-2 text-sm text-gray-600">{{ \App\Support\StatusLabel::for($backup->status) }}</span><span class="mt-1 block text-xs text-gray-500">{{ $backup->created_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') ?? 'Tanggal tidak tersedia' }}</span></summary><dl class="mt-3 space-y-2 text-sm"><div class="flex justify-between gap-4"><dt class="text-gray-600">Basis data</dt><dd class="text-right">{{ $backup->database_location_alias }}<br>{{ $backup->humanSize($backup->database_size_bytes) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-gray-600">Media</dt><dd class="text-right">{{ $backup->media_location_alias }}<br>{{ $backup->humanSize($backup->media_size_bytes) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-gray-600">Verifikasi</dt><dd>{{ $backup->restore_verification_result?->value ? \App\Support\StatusLabel::for($backup->restore_verification_result) : 'Belum diuji' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-gray-600">Retensi</dt><dd>{{ $backup->retention_until->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</dd></div><div class="border-t border-gray-100 pt-2 text-xs text-gray-500"><dt>Checksum basis data</dt><dd class="break-all">{{ $backup->database_sha256 }}</dd><dt class="mt-1">Checksum media</dt><dd class="break-all">{{ $backup->media_sha256 }}</dd></div></dl></details>@endforeach</div>
                <div class="mt-4 hidden overflow-x-auto md:block"><table class="min-w-full text-left text-sm"><caption class="sr-only">Daftar metadata cadangan terbaru</caption><thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-3 py-2">ID</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Basis data</th><th class="px-3 py-2">Media</th><th class="px-3 py-2">Pemulihan</th><th class="px-3 py-2">Retensi</th></tr></thead><tbody class="divide-y divide-gray-100">@foreach ($backups as $backup)<tr><td class="px-3 py-3 font-medium">{{ $backup->id }}<br><span class="text-xs text-gray-500">{{ $backup->created_at?->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</span></td><td class="px-3 py-3">{{ \App\Support\StatusLabel::for($backup->status) }}</td><td class="px-3 py-3">{{ $backup->database_location_alias }}<br><span class="text-xs text-gray-500">{{ $backup->humanSize($backup->database_size_bytes) }}</span></td><td class="px-3 py-3">{{ $backup->media_location_alias }}<br><span class="text-xs text-gray-500">{{ $backup->humanSize($backup->media_size_bytes) }}</span></td><td class="px-3 py-3">{{ $backup->restore_verification_result?->value ? \App\Support\StatusLabel::for($backup->restore_verification_result) : 'Belum diuji' }}</td><td class="px-3 py-3">{{ $backup->retention_until->setTimezone('Asia/Jakarta')->format('d M Y H:i') }}</td></tr>@endforeach</tbody></table></div>
            @endif</section>
        @endif
    </div>
</x-filament-panels::page>
