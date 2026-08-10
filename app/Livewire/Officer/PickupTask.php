<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.officer')]
final class PickupTask extends Component
{
    public PickupRequest $pickup;

    /** @var list<array{waste_type_id: string, condition_id: string, weight_kg: string}> */
    public array $actualItems = [];

    public string $idempotencyKey = '';

    public string $failureReason = '';

    public bool $failureDialogOpen = false;

    public function mount(PickupRequest $pickup, PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($service->canView($actor, $pickup) && $pickup->assigned_staff_id === $actor->id, 404);
        $this->pickup = $pickup->load(['customer', 'items.wasteType', 'media', 'statusHistory.actor']);
        $this->idempotencyKey = (string) str()->uuid();
        $this->actualItems = [['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => '']];
    }

    public function begin(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->pickup = $service->begin($actor, $this->pickup);
    }

    public function markPickedUp(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->pickup = $service->markPickedUp($actor, $this->pickup);
    }

    public function reportFailure(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->validate(['failureReason' => ['required', 'string', 'min:10', 'max:1000']]);
        $this->pickup = $service->cancel($actor, $this->pickup, $this->failureReason);
        $this->failureDialogOpen = false;
        session()->flash('success', 'Kegagalan tugas tercatat dan penjemputan dihentikan.');
    }

    public function openFailureReport(): void
    {
        $this->failureDialogOpen = true;
        $this->resetErrorBag('failureReason');
    }

    public function closeFailureReport(): void
    {
        $this->failureDialogOpen = false;
    }

    public function complete(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->validate([
            'actualItems' => ['required', 'array', 'min:1'],
            'actualItems.*.waste_type_id' => ['required', 'integer', 'min:1', 'exists:waste_types,id'],
            'actualItems.*.condition_id' => ['required', 'integer', 'min:1', 'exists:waste_conditions,id'],
            'actualItems.*.weight_kg' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
        ]);
        if (! $this->pickup->media()->exists()) {
            $this->addError('evidence', 'Bukti foto penjemputan wajib tersedia sebelum tugas diselesaikan.');

            return;
        }

        $this->pickup = $service->complete($actor, $this->pickup, array_map(static fn (array $item): DepositItemInput => DepositItemInput::fromArray($item), $this->actualItems), $this->idempotencyKey);
        session()->flash('success', 'Penjemputan selesai dan setoran aktual telah dibuat.');
    }

    public function render(): View
    {
        $types = WasteType::query()->where('is_active', true)->orderBy('name')->get();
        $conditions = WasteCondition::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('livewire.officer.pickup-task', [
            'types' => $types,
            'conditions' => $conditions,
            'canComplete' => $this->pickup->status === PickupStatus::PickedUp,
            'pricePreview' => $this->pricePreview($types, $conditions),
        ]);
    }

    /**
     * @param  Collection<int, WasteType>  $types
     * @param  Collection<int, WasteCondition>  $conditions
     * @return array{lines: list<array{name: string, condition: string, weight: string, subtotal: int}>, total: int, complete: bool}
     */
    private function pricePreview(Collection $types, Collection $conditions): array
    {
        $lines = [];
        $total = 0;
        $complete = $this->actualItems !== [];

        foreach ($this->actualItems as $item) {
            $type = $types->firstWhere('id', (int) $item['waste_type_id']);
            $condition = $conditions->firstWhere('id', (int) $item['condition_id']);
            $weight = $item['weight_kg'];
            if ($type === null || $condition === null || $weight === '') {
                $complete = false;

                continue;
            }

            try {
                $price = app(ResolveWastePrice::class)->resolve($type, (int) $condition->id, now('Asia/Jakarta'));
                $snapshot = $price->snapshot()->withWeight($weight);
            } catch (Throwable) {
                $complete = false;

                continue;
            }

            $subtotal = (int) $snapshot->subtotal;
            $total += $subtotal;
            $lines[] = [
                'name' => $type->name,
                'condition' => $condition->name,
                'weight' => (string) $snapshot->weightKg,
                'subtotal' => $subtotal,
            ];
        }

        return ['lines' => $lines, 'total' => $total, 'complete' => $complete && $lines !== []];
    }

    private function assertAssigned(User $actor): void
    {
        abort_unless($this->pickup->assigned_staff_id === $actor->id, 404);
    }
}
