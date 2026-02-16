<?php

namespace App\Filament\Pages\Auth;

use App\Enums\UserRole;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;

class Register extends BaseRegister
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getTelegramIdFormComponent(),
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getTelegramIdFormComponent(): Component
    {
        return TextInput::make('telegram_id')
            ->label('Telegram ID')
            ->numeric()
            ->required()
            ->unique(table: 'users', column: 'telegram_id')
            ->helperText('Your unique Telegram user ID');
    }

    protected function getNameFormComponent(): Component
    {
        return TextInput::make('first_name')
            ->label('Name')
            ->required()
            ->maxLength(255);
    }

    protected function handleRegistration(array $data): Model
    {
        // Set default role to User (will be overridden if telegram_id is in admin list)
        $data['role'] = UserRole::User;

        // Check if telegram_id is in admin list
        $adminIds = config('services.telegram.admin_id');
        if ($adminIds) {
            $adminIdsList = array_map('trim', explode(',', $adminIds));
            if (in_array((string) $data['telegram_id'], $adminIdsList, true)) {
                $data['role'] = UserRole::Admin;
            }
        }

        return parent::handleRegistration($data);
    }
}
