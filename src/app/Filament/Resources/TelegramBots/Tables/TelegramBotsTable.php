<?php

namespace App\Filament\Resources\TelegramBots\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

use App\Models\TelegramBot;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;

class TelegramBotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("id")->label("ID"),
                TextColumn::make("name")
                    ->label("Название")
                    ->searchable()
                    ->sortable()
                    ->description(function ($record) {
                        if (str_contains(strtolower($record->name), "погод")) {
                            return "Погодный бот";
                        } elseif (
                            str_contains(strtolower($record->name), "vin")
                        ) {
                            return "Отчет по VIN";
                        }
                        return "🤖 Общий бот";
                    }),

                TextColumn::make("token")
                    ->label("Токен")
                    ->searchable()
                    //->limit(15)
                    ->tooltip(function ($record) {
                        return "Нажмите, чтобы скопировать";
                    })
                    ->copyable()
                    ->copyMessage("Токен скопирован")
                    ->copyMessageDuration(1500),

                TextColumn::make("chats_count")
                    ->label("Чаты")
                    ->counts("chats")
                    ->sortable(),
                /*
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),*/
            ])
            ->filters([
                //
            ])
            ->actions([
                // Действия над записью (рядом с каждой записью)
            ])
            ->recordActions([
                Action::make("setup_webhook")
                    ->label("Setup webhook")
                    ->icon("heroicon-o-link")
                    ->color("success")
                    ->action(function (TelegramBot $record) {
                        try {
                            $webhookUrl =
                                "https://superbly-tough-cusk.cloudpub.ru/telegram/" .
                                $record->token;
                            $handlerClass = $record->handler_class;
                            //
                            //$webhookUrl = url('/webhook/telegram/' . $record->token);

                            $response = Http::post(
                                "https://api.telegram.org/bot{$record->token}/setWebhook",
                                [
                                    "url" => $webhookUrl,
                                    "handler" => $handlerClass,
                                ],
                            );

                            if ($response->json()["ok"]) {
                                Notification::make()
                                    ->title("Вебхук установлен!")
                                    ->body("URL: {$webhookUrl}")
                                    ->success()
                                    ->send();
                            } else {
                                throw new \Exception(
                                    "Ошибка Telegram API</br></br>" . $response,
                                );
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title("Ошибка!")
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading("Установка вебхука")
                    ->modalDescription(
                        "Вы уверены, что хотите установить вебхук для этого бота?",
                    )
                    ->modalSubmitActionLabel("Установить"),
                ViewAction::make(),
                EditAction::make(),
            ]);
        /* ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);*/
    }
}
