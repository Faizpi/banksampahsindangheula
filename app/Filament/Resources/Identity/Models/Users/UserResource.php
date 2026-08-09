<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Users;

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Actions\ManageRoles;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Filament\Resources\Identity\Models\Users\Pages\ManageUsers as ManageUsersPage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Identitas & Akses';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'user.view');
    }

    public static function canCreate(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'user.create');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun pengguna')->schema([
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                TextInput::make('phone')->label('Nomor telepon')->required()->regex('/^62[0-9]{8,16}$/')->maxLength(20),
                TextInput::make('email')->label('Email')->email()->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('customerProfile.customer_number')->label('Nomor nasabah')->placeholder('Belum terbit')->toggleable(),
                TextColumn::make('roles.name')->label('Role')->badge()->separator(','),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat')->authorize('manageView')->schema([
                    TextInput::make('name')->label('Nama'),
                    TextInput::make('status')->label('Status'),
                    TextInput::make('phone')->label('Nomor telepon')->formatStateUsing(static fn (?string $state): string => $state === null ? 'Tidak tersedia' : Str::mask($state, '*', 4)),
                ]),
                EditAction::make()->label('Ubah')->authorize('manageUpdate')->using(fn (User $record, array $data): User => app(ManageUsers::class)->update(self::actor(), $record, $data)),
                Action::make('assignRoles')
                    ->label('Atur role')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->authorize(fn (User $record): bool => auth()->user() instanceof User && auth()->user()->can('manageUpdate', $record) && app(PermissionChecker::class)->allows(auth()->user(), 'role.manage'))
                    ->schema([
                        Select::make('role_ids')->label('Role')->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())->multiple()->preload()->required(),
                        Textarea::make('reason')->label('Alasan assignment')->required()->minLength(10)->maxLength(1000)->rows(3),
                    ])
                    ->fillForm(fn (User $record): array => ['role_ids' => $record->roles()->pluck('roles.id')->all()])
                    ->requiresConfirmation()
                    ->action(fn (User $record, array $data): User => app(ManageRoles::class)->assignRoles(self::actor(), $record, array_map('intval', $data['role_ids']), (string) $data['reason']))
                    ->successNotificationTitle('Role pengguna diperbarui.'),
                Action::make('activate')->label('Aktifkan')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->visible(fn (User $record): bool => $record->status === UserStatus::Inactive)->requiresConfirmation()->action(fn (User $record): User => app(ManageUsers::class)->activate(self::actor(), $record))->successNotificationTitle('Pengguna diaktifkan.'),
                Action::make('deactivate')->label('Nonaktifkan')->icon(Heroicon::OutlinedNoSymbol)->color('danger')->authorize('deactivate')->visible(fn (User $record): bool => $record->status === UserStatus::Active)->requiresConfirmation()->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)->rows(3)])->action(fn (User $record, array $data): User => app(ManageUsers::class)->deactivate(self::actor(), $record, (string) $data['reason']))->successNotificationTitle('Pengguna dinonaktifkan.'),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageUsersPage::route('/')];
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return User::query()->whereKey([]);
        }

        return app(VisibleUsers::class)->queryFor($actor, ...UserStatus::cases())->with(['customerProfile', 'roles']);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
