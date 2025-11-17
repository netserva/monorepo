<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar')
                    ->label('Avatar')
                    ->avatar()
                    ->disk('public')
                    ->directory('avatars')
                    ->visibility('public')
                    ->imageEditor()
                    ->circleCropper()
                    ->maxSize(1024)
                    ->helperText('Upload a profile picture (max 1MB)'),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                Select::make('palette_id')
                    ->label('Color Palette')
                    ->options(\App\Models\Palette::query()->pluck('label', 'id'))
                    ->searchable()
                    ->native(false)
                    ->helperText('Choose your preferred color scheme for the admin panel')
                    ->placeholder('Select a palette'),
            ]);
    }

    /**
     * Override handleRecordUpdate to ensure palette_id is saved.
     */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        \Illuminate\Support\Facades\Log::info('handleRecordUpdate called', [
            'user_id' => $record->id,
            'data' => $data,
            'palette_id_in_data' => $data['palette_id'] ?? 'NOT_SET',
        ]);

        // Ensure palette_id is included in the update
        $record->update($data);

        \Illuminate\Support\Facades\Log::info('After update', [
            'user_id' => $record->id,
            'palette_id' => $record->palette_id,
        ]);

        return $record;
    }

    /**
     * Hook called after saving to force full page reload for palette changes.
     */
    protected function afterSave(): void
    {
        \Illuminate\Support\Facades\Log::info('afterSave called', [
            'user_id' => $this->getUser()->id,
            'palette_id' => $this->getUser()->palette_id,
        ]);

        // Force full page reload with JavaScript (not Livewire navigate)
        // This ensures palette colors are refreshed
        $this->js('window.location.href = "'.\Filament\Facades\Filament::getUrl().'"');
    }
}
