<?php

namespace App\Filament\Resources\AppSettings\Pages;

use App\Filament\Resources\AppSettings\AppSettingResource;
use App\Models\AppSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageAppSettings extends Page
{
    protected static string $resource = AppSettingResource::class;

    protected static ?string $title = "Настройка приложения";

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getFormDefaults());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath("data");
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make("Админ-панель")
                ->schema([
                    ColorPicker::make("admin_panel_primary_color")
                        ->label("Цвет primary")
                        ->required(),
                    Select::make("admin_panel_navigation_layout")
                        ->label("Расположение меню")
                        ->options([
                            AppSetting::NAVIGATION_TOP => "Верхнее меню",
                            AppSetting::NAVIGATION_LEFT => "Левое меню",
                        ])
                        ->required(),
                ])
                ->columns(2),
            Section::make("Основная панель")
                ->schema([
                    ColorPicker::make("main_panel_primary_color")
                        ->label("Цвет primary")
                        ->required(),
                    Select::make("main_panel_navigation_layout")
                        ->label("Расположение меню")
                        ->options([
                            AppSetting::NAVIGATION_TOP => "Верхнее меню",
                            AppSetting::NAVIGATION_LEFT => "Левое меню",
                        ])
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make("form")])
                ->id("app-settings-form")
                ->livewireSubmitHandler("save")
                ->footer([
                    Actions::make([$this->getSaveFormAction()])->key(
                        "form-actions",
                    ),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $this->saveSetting(
            AppSetting::KEY_ADMIN_PANEL_PRIMARY_COLOR,
            "Цвет primary админ-панели",
            (string) ($state["admin_panel_primary_color"] ?? "#3B82F6"),
        );

        $this->saveSetting(
            AppSetting::KEY_ADMIN_PANEL_NAVIGATION_LAYOUT,
            "Расположение меню админ-панели",
            (string) ($state["admin_panel_navigation_layout"] ??
                AppSetting::NAVIGATION_TOP),
        );

        $this->saveSetting(
            AppSetting::KEY_MAIN_PANEL_PRIMARY_COLOR,
            "Цвет primary основной панели",
            (string) ($state["main_panel_primary_color"] ?? "#6366F1"),
        );

        $this->saveSetting(
            AppSetting::KEY_MAIN_PANEL_NAVIGATION_LAYOUT,
            "Расположение меню основной панели",
            (string) ($state["main_panel_navigation_layout"] ??
                AppSetting::NAVIGATION_TOP),
        );

        Notification::make()
            ->title("Настройки приложения сохранены")
            ->success()
            ->send();
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make("save")->label("Сохранить")->submit("save");
    }

    /**
     * @return array<string, string>
     */
    private function getFormDefaults(): array
    {
        return [
            "admin_panel_primary_color" => (string) AppSetting::getValue(
                AppSetting::KEY_ADMIN_PANEL_PRIMARY_COLOR,
                "#3B82F6",
            ),
            "admin_panel_navigation_layout" => (string) AppSetting::getValue(
                AppSetting::KEY_ADMIN_PANEL_NAVIGATION_LAYOUT,
                AppSetting::NAVIGATION_TOP,
            ),
            "main_panel_primary_color" => (string) AppSetting::getValue(
                AppSetting::KEY_MAIN_PANEL_PRIMARY_COLOR,
                "#6366F1",
            ),
            "main_panel_navigation_layout" => (string) AppSetting::getValue(
                AppSetting::KEY_MAIN_PANEL_NAVIGATION_LAYOUT,
                AppSetting::NAVIGATION_TOP,
            ),
        ];
    }

    private function saveSetting(string $key, string $name, string $value): void
    {
        AppSetting::query()->updateOrCreate(
            ["key" => $key],
            [
                "name" => $name,
                "value" => $value,
            ],
        );
    }
}
