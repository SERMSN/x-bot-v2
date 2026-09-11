<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">VIN Mini App</div>
                    <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">Подписка и отчеты</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Простая страница-заглушка под Telegram Mini App. Позже сюда подключим оплату.
                    </p>
                </div>

                <div class="rounded-xl bg-primary-50 px-4 py-3 text-sm text-primary-700 dark:bg-primary-950/40 dark:text-primary-300">
                    Доступно отчетов:
                    <div class="mt-1 text-3xl font-bold">{{ $reportsRemaining }}</div>
                </div>
            </div>

            @if (session('vin-mini-app-action'))
                <div class="mt-4 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800 dark:border-primary-900 dark:bg-primary-950/40 dark:text-primary-300">
                    {{ session('vin-mini-app-action') }}
                </div>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($plans as $plan)
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $plan->name }}</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $plan->description ?: 'Пакет отчетов для VIN-бота' }}
                            </p>
                        </div>

                        <div class="rounded-xl bg-gray-100 px-3 py-2 text-right dark:bg-gray-800">
                            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Отчетов</div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $plan->report_count }}</div>
                        </div>
                    </div>

                    <div class="mt-5 flex items-end justify-between gap-4">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Стоимость</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ number_format((float) $plan->price_rub, 0, ',', ' ') }} ₽
                            </div>
                        </div>

                        <x-filament::button
                            color="primary"
                            wire:click="purchasePlan({{ $plan->id }})"
                        >
                            Купить
                        </x-filament::button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
