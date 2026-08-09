<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Actions\AssistedCustomerService as AssistedCustomerServiceAction;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\Consent;
use App\Domain\CustomersRegions\Contracts\CustomerSummary;
use App\Domain\CustomersRegions\Contracts\EvidenceReference;
use App\Domain\CustomersRegions\Queries\SearchCustomers;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.officer')]
final class CustomerIdentification extends Component
{
    use WithFileUploads;

    public string $search = '';

    #[Locked]
    public ?CustomerSummary $candidate = null;

    #[Locked]
    public bool $confirmed = false;

    public bool $scannerOpen = false;

    public bool $assistedConsent = false;

    public ?UploadedFile $assistedEvidence = null;

    public bool $assistedRecorded = false;

    public ?int $assistedServiceId = null;

    public ?int $mobileServiceId = null;

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'customer.view'), 403);
    }

    public function find(SearchCustomers $searchCustomers): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->resetCandidate();
        $this->validate(['search' => ['required', 'string', 'max:120']]);
        $matches = $searchCustomers->search($actor, $this->search, 1);
        $this->candidate = $matches[0] ?? null;
    }

    public function scan(string $rawToken, ManageCustomerIdentity $identity): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->resetCandidate();
        $this->scannerOpen = false;

        try {
            $this->candidate = $identity->scan($actor, $rawToken);
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException) {
                $this->scannerOpen = true;
                $this->addError('token', 'QR tidak ditemukan atau sudah tidak aktif.');

                return;
            }

            throw $exception;
        }
    }

    public function openScanner(): void
    {
        $this->resetErrorBag('token');
        $this->scannerOpen = true;
    }

    public function closeScanner(): void
    {
        $this->scannerOpen = false;
    }

    public function confirm(): void
    {
        if ($this->candidate === null) {
            throw ValidationException::withMessages(['search' => 'Cari nasabah sebelum konfirmasi nama.']);
        }

        $this->confirmed = true;
    }

    public function recordAssistedService(
        AssistedCustomerServiceAction $service,
        StorePrivateMedia $mediaStore,
    ): void {
        if ($this->candidate === null) {
            throw ValidationException::withMessages(['search' => 'Cari nasabah sebelum mencatat layanan berbantuan.']);
        }

        if (! $this->confirmed) {
            throw ValidationException::withMessages(['search' => 'Konfirmasi nama warga sebelum mencatat layanan berbantuan.']);
        }

        $this->validate([
            'assistedConsent' => ['accepted'],
            'assistedEvidence' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'assistedConsent.accepted' => 'Persetujuan warga wajib dicatat.',
            'assistedEvidence.required' => 'Bukti privat wajib diunggah.',
        ]);

        /** @var User $actor */
        $actor = auth()->user();
        /** @var UploadedFile $evidenceFile */
        $evidenceFile = $this->assistedEvidence;
        $media = $mediaStore->handle($evidenceFile, $actor);

        try {
            $record = $service->record(
                $actor,
                User::query()->findOrFail($this->candidate->userId),
                AssistedServiceContract::create(
                    $this->candidate->userId,
                    $actor->id,
                    'layanan_nasabah',
                    Consent::given('assisted-service-v1'),
                    EvidenceReference::privateMedia($media->id),
                ),
            );
            $this->assistedServiceId = $record->id;
        } catch (Throwable $exception) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();

            throw $exception;
        }

        $this->assistedRecorded = true;
        session()->flash('success', 'Layanan berbantuan tercatat dengan persetujuan dan bukti privat.');
    }

    public function handoff(AssistedCustomerServiceAction $service): void
    {
        if ($this->assistedServiceId === null) {
            throw ValidationException::withMessages(['assistedEvidence' => 'Layanan berbantuan belum tercatat.']);
        }

        /** @var User $actor */
        $actor = auth()->user();
        $handoff = $service->handoff($actor, $this->assistedServiceId);
        session()->flash('assisted-handoff', $handoff);
    }

    public function render(PermissionChecker $permissions): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.officer.customer-identification', [
            'canCreateAssisted' => $permissions->allows($actor, 'customer.create-assisted'),
            'mobileServices' => MobileService::query()->whereHas('staff', static fn (Builder $staff): Builder => $staff->whereKey($actor->id))->where('status', MobileServiceStatus::Open)->orderBy('starts_at')->get(),
        ]);
    }

    private function resetCandidate(): void
    {
        $this->reset(['candidate', 'confirmed', 'assistedRecorded', 'assistedServiceId', 'assistedConsent', 'assistedEvidence', 'mobileServiceId']);
        $this->resetErrorBag(['search', 'token', 'assistedConsent', 'assistedEvidence']);
    }
}
