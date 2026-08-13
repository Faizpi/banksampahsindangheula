<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Citizen\PickupRequestForm;
use App\Livewire\Officer\PickupTask;
use App\Livewire\Treasurer\WithdrawalPayments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class PhotoUploadPickerTest extends TestCase
{
    public function test_photo_picker_uploads_only_the_new_compressed_file(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));

        self::assertStringContainsString('await uploadPhotoPickerFiles(picker, property, compressedFiles, input.multiple);', $script);
        self::assertStringContainsString('wire.$uploadMultiple(property, files, () => resolve(), onError);', $script);
        self::assertStringContainsString('const confirmedFiles = await wire.$call(confirmMethod);', $script);
        self::assertStringContainsString('return window.Livewire.find(componentId);', $script);
        self::assertStringContainsString('wire.$upload(', $script);
        self::assertStringContainsString('wire.$call(removeMethod, index)', $script);
        self::assertStringContainsString('photoTrigger instanceof HTMLLabelElement || photoTrigger instanceof HTMLButtonElement', $script);
        self::assertStringContainsString('Labels activate their associated file input natively.', $script);
        self::assertStringNotContainsString('syncPhotoPickerInput', $script);
        self::assertStringNotContainsString('photoPickerSyncing', $script);
    }

    public function test_each_custom_photo_picker_uses_only_its_direct_livewire_upload_property(): void
    {
        $citizenView = (string) file_get_contents(resource_path('views/livewire/citizen/pickup-request-form.blade.php'));
        $officerView = (string) file_get_contents(resource_path('views/livewire/officer/pickup-task.blade.php'));
        $treasurerView = (string) file_get_contents(resource_path('views/livewire/treasurer/withdrawal-payments.blade.php'));

        self::assertStringContainsString('wire:ignore', $citizenView);
        self::assertStringContainsString('data-photo-picker-property="photos"', $citizenView);
        self::assertStringNotContainsString('wire:model="photos"', $citizenView);
        self::assertStringContainsString('data-photo-picker-remove-method="removePhoto"', $citizenView);
        self::assertStringContainsString('data-photo-picker-confirm-method="confirmPhotoUploads"', $citizenView);
        self::assertStringContainsString('data-photo-picker-action', $citizenView);
        self::assertStringContainsString('<label for="pickup-photos" data-photo-picker-trigger="camera"', $citizenView);
        self::assertStringContainsString('<label for="pickup-photos" data-photo-picker-trigger="gallery"', $citizenView);
        self::assertStringContainsString('Total berat (kg)', $citizenView);
        self::assertStringContainsString('Jumlah wadah (opsional)', $citizenView);
        self::assertStringContainsString('Jumlah wadah opsional, misalnya 2 kantong atau 1 karung, dan bukan pengali berat.', $citizenView);

        self::assertStringContainsString('wire:ignore', $officerView);
        self::assertStringContainsString('data-photo-picker-property="evidence"', $officerView);
        self::assertStringNotContainsString('wire:model="evidence"', $officerView);
        self::assertStringContainsString('data-photo-picker-remove-method="clearEvidence"', $officerView);
        self::assertStringContainsString('data-photo-picker-confirm-method="confirmEvidenceUpload"', $officerView);
        self::assertStringContainsString('<label for="pickup-evidence" data-photo-picker-trigger="camera"', $officerView);
        self::assertStringContainsString('<label for="pickup-evidence" data-photo-picker-trigger="gallery"', $officerView);

        self::assertStringContainsString('wire:ignore', $treasurerView);
        self::assertStringContainsString('data-photo-picker-property="proof"', $treasurerView);
        self::assertStringNotContainsString('wire:model="proof"', $treasurerView);
        self::assertStringContainsString('data-photo-picker-remove-method="clearProof"', $treasurerView);
        self::assertStringContainsString('data-photo-picker-confirm-method="confirmProofUpload"', $treasurerView);
        self::assertStringContainsString('<label for="payment-proof" data-photo-picker-trigger="camera"', $treasurerView);
        self::assertStringContainsString('<label for="payment-proof" data-photo-picker-trigger="gallery"', $treasurerView);
    }

    public function test_no_custom_photo_picker_combines_manual_upload_with_wire_model(): void
    {
        $customInputs = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            $contents = (string) file_get_contents($view->getPathname());
            preg_match_all('/<input\b(?=[^>]*\bdata-photo-picker-input\b)[^>]*>/i', $contents, $matches);

            foreach ($matches[0] as $input) {
                $customInputs[] = ['path' => $view->getRelativePathname(), 'input' => $input];
            }
        }

        self::assertCount(3, $customInputs);
        foreach ($customInputs as $customInput) {
            self::assertStringNotContainsString(
                'wire:model',
                $customInput['input'],
                "Custom photo picker [{$customInput['path']}] must use only the JavaScript uploader.",
            );
        }
    }

    public function test_removing_a_photo_updates_server_state_and_clears_the_stale_limit_error(): void
    {
        $first = UploadedFile::fake()->image('first.jpg');
        $second = UploadedFile::fake()->image('second.jpg');
        $duplicate = UploadedFile::fake()->image('duplicate.jpg');
        $component = new PickupRequestForm;
        $component->photos = [$first, $second, $duplicate];
        $component->addError('photos', 'Foto sampah tidak boleh berisi lebih dari 2 item.');
        $component->addError('photos.2', 'Foto ketiga tidak valid.');

        $component->removePhoto(2);

        self::assertSame([$first, $second], $component->photos);
        self::assertFalse($component->getErrorBag()->has('photos'));
        self::assertFalse($component->getErrorBag()->has('photos.2'));
    }

    public function test_custom_picker_components_can_remove_temporary_uploads_on_the_server(): void
    {
        $citizenComponent = (string) file_get_contents(app_path('Livewire/Citizen/PickupRequestForm.php'));
        $officerComponent = (string) file_get_contents(app_path('Livewire/Officer/PickupTask.php'));
        $treasurerComponent = (string) file_get_contents(app_path('Livewire/Treasurer/WithdrawalPayments.php'));

        self::assertStringContainsString('public function removePhoto(int $index): void', $citizenComponent);
        self::assertStringContainsString('public function updatedPhotos(): void', $citizenComponent);
        self::assertStringContainsString('public function confirmPhotoUploads(): array', $citizenComponent);
        self::assertStringContainsString('public function clearEvidence(): void', $officerComponent);
        self::assertStringContainsString('public function confirmEvidenceUpload(): array', $officerComponent);
        self::assertStringContainsString('public function clearProof(): void', $treasurerComponent);
        self::assertStringContainsString('public function confirmProofUpload(): array', $treasurerComponent);
    }

    public function test_each_picker_confirms_the_authoritative_server_upload_before_showing_success(): void
    {
        $citizen = new PickupRequestForm;
        $citizen->photos = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ];
        $citizen->addError('photos', 'Foto sampah wajib diisi.');

        $citizenFiles = $citizen->confirmPhotoUploads();

        self::assertSame(['first.jpg', 'second.jpg'], array_column($citizenFiles, 'name'));
        self::assertFalse($citizen->getErrorBag()->has('photos'));

        $officer = new PickupTask;
        $officer->evidence = UploadedFile::fake()->image('evidence.jpg');
        $officer->addError('evidence', 'Tambahkan bukti foto.');

        self::assertSame('evidence.jpg', $officer->confirmEvidenceUpload()[0]['name']);
        self::assertFalse($officer->getErrorBag()->has('evidence'));

        $treasurer = new WithdrawalPayments;
        $treasurer->proof = UploadedFile::fake()->image('proof.jpg');
        $treasurer->addError('proof', 'Tambahkan satu foto bukti pembayaran.');

        self::assertSame('proof.jpg', $treasurer->confirmProofUpload()[0]['name']);
        self::assertFalse($treasurer->getErrorBag()->has('proof'));
    }
}
