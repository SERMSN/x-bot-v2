<?php

namespace App\Console\Commands;

use App\Models\WeatherBot;
use App\Models\LicensePlateBot;
use Illuminate\Console\Command;

class BotManagerCommand extends Command
{
    protected $signature = 'bot:manage 
                            {action : register|list|webhook|delete} 
                            {type : weather|license} 
                            {token? : Bot token} 
                            {--name= : Bot name}';

    protected $description = 'Manage Telegram bots';

    public function handle()
    {
        $action = $this->argument('action');
        $type = $this->argument('type');

        switch ($action) {
            case 'register':
                $this->registerBot($type);
                break;
            case 'list':
                $this->listBots($type);
                break;
            case 'webhook':
                $this->setupWebhook($type);
                break;
            case 'delete':
                $this->deleteBot($type);
                break;
            default:
                $this->error("Unknown action: $action");
        }

        return Command::SUCCESS;
    }

    protected function registerBot(string $type): void
    {
        $token = $this->argument('token') ?? $this->ask("Enter $type bot token");
        $name = $this->option('name') ?? $this->ask("Bot name (optional)");

        switch ($type) {
            case 'weather':
                $bot = WeatherBot::register($token, $name);
                $webhookRoute = 'webhook.weather';
                break;
            case 'license':
                $bot = LicensePlateBot::register($token, $name);
                $webhookRoute = 'webhook.license_plate';
                break;
            default:
                $this->error("Unknown bot type: $type");
                return;
        }

        try {
            $bot->registerWebhook()->send();
            $this->info("✅ $type bot '{$bot->name}' registered successfully!");
            $this->info("Webhook URL: " . route($webhookRoute, ['token' => $token]));
            $this->info("Token: $token");
        } catch (\Exception $e) {
            $this->error("❌ Bot registered but webhook setup failed: " . $e->getMessage());
        }
    }

    protected function listBots(string $type): void
    {
        switch ($type) {
            case 'weather':
                $bots = WeatherBot::all();
                $title = "Weather Bots";
                break;
            case 'license':
                $bots = LicensePlateBot::all();
                $title = "License Plate Bots";
                break;
            default:
                $this->error("Unknown bot type: $type");
                return;
        }

        if ($bots->isEmpty()) {
            $this->info("No $type bots registered.");
            return;
        }

        $this->info("$title:");
        $this->table(
            ['ID', 'Name', 'Token', 'Webhook URL'],
            $bots->map(function ($bot) use ($type) {
                $webhookRoute = $type === 'weather' ? 'webhook.weather' : 'webhook.license_plate';
                return [
                    $bot->id,
                    $bot->name,
                    substr($bot->token, 0, 15) . '...',
                    route($webhookRoute, ['token' => $bot->token])
                ];
            })
        );
    }

    protected function setupWebhook(string $type): void
    {
        switch ($type) {
            case 'weather':
                $bots = WeatherBot::all();
                break;
            case 'license':
                $bots = LicensePlateBot::all();
                break;
            default:
                $this->error("Unknown bot type: $type");
                return;
        }

        if ($bots->isEmpty()) {
            $this->info("No $type bots registered.");
            return;
        }

        foreach ($bots as $bot) {
            try {
                $bot->registerWebhook()->send();
                $this->info("✅ Webhook for '{$bot->name}' setup successfully");
            } catch (\Exception $e) {
                $this->error("❌ Webhook for '{$bot->name}' failed: " . $e->getMessage());
            }
        }
    }

    protected function deleteBot(string $type): void
    {
        $token = $this->argument('token') ?? $this->ask("Enter $type bot token to delete");

        switch ($type) {
            case 'weather':
                $bot = WeatherBot::where('token', $token)->first();
                break;
            case 'license':
                $bot = LicensePlateBot::where('token', $token)->first();
                break;
            default:
                $this->error("Unknown bot type: $type");
                return;
        }

        if (!$bot) {
            $this->error("$type bot with token not found");
            return;
        }

        if ($this->confirm("Delete $type bot '{$bot->name}'?")) {
            $bot->delete();
            $this->info("✅ Bot '{$bot->name}' deleted");
        }
    }
}