<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PhotoUploadPickerTest extends TestCase
{
    public function test_photo_picker_uploads_only_the_new_compressed_file(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));

        self::assertStringContainsString('await uploadPhotoPickerFile(picker, property, file);', $script);
        self::assertStringContainsString('return window.Livewire.find(componentId);', $script);
        self::assertStringContainsString('wire.$upload(', $script);
        self::assertStringContainsString('wire.$call(removeMethod, index)', $script);
        self::assertStringNotContainsString('syncPhotoPickerInput', $script);
        self::assertStringNotContainsString('photoPickerSyncing', $script);
    }

    public function test_each_custom_photo_picker_uses_its_own_direct_livewire_property(): void
    {
        $citizenView = (string) file_get_contents(resource_path('views/livewire/citizen/pickup-request-form.blade.php'));
        $officerView = (string) file_get_contents(resource_path('views/livewire/officer/pickup-task.blade.php'));
        $treasurerView = (string) file_get_contents(resource_path('views/livewire/treasurer/withdrawal-payments.blade.php'));

        self::assertStringContainsString('wire:ignore', $citizenView);
        self::assertStringContainsString('data-photo-picker-property="photos"', $citizenView);
        self::assertStringContainsString('wire:model="photos"', $citizenView);
        self::assertStringContainsString('data-photo-picker-remove-method="removePhoto"', $citizenView);
        self::assertStringContainsString('Total berat (kg)', $citizenView);
        self::assertStringContainsString('Jumlah wadah (opsional)', $citizenView);
        self::assertStringContainsString('Jumlah wadah opsional, misalnya 2 kantong atau 1 karung, dan bukan pengali berat.', $citizenView);

        self::assertStringContainsString('wire:ignore', $officerView);
        self::assertStringContainsString('data-photo-picker-property="evidence"', $officerView);
        self::assertStringContainsString('wire:model="evidence"', $officerView);
        self::assertStringContainsString('data-photo-picker-remove-method="clearEvidence"', $officerView);

        self::assertStringContainsString('wire:ignore', $treasurerView);
        self::assertStringContainsString('data-photo-picker-property="proof"', $treasurerView);
        self::assertStringContainsString('wire:model="proof"', $treasurerView);
        self::assertStringContainsString('data-photo-picker-remove-method="clearProof"', $treasurerView);
    }

    public function test_custom_picker_components_can_remove_temporary_uploads_on_the_server(): void
    {
        $citizenComponent = (string) file_get_contents(app_path('Livewire/Citizen/PickupRequestForm.php'));
        $officerComponent = (string) file_get_contents(app_path('Livewire/Officer/PickupTask.php'));
        $treasurerComponent = (string) file_get_contents(app_path('Livewire/Treasurer/WithdrawalPayments.php'));

        self::assertStringContainsString('public function removePhoto(int $index): void', $citizenComponent);
        self::assertStringContainsString('public function clearEvidence(): void', $officerComponent);
        self::assertStringContainsString('public function clearProof(): void', $treasurerComponent);
    }
}
