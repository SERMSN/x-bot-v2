<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>VIN Mini App</title>
        <style>
            :root {
                --bg: #eef4ff;
                --bg-2: #f8fbff;
                --card: rgba(255, 255, 255, 0.82);
                --card-strong: #ffffff;
                --text: #0f172a;
                --muted: #64748b;
                --primary: #5b5ce6;
                --primary-strong: #4338ca;
                --primary-soft: rgba(91, 92, 230, 0.12);
                --success: #10b981;
                --success-soft: rgba(16, 185, 129, 0.12);
                --gold: #f59e0b;
                --gold-soft: rgba(245, 158, 11, 0.12);
                --line: rgba(148, 163, 184, 0.28);
                --shadow: 0 20px 50px rgba(67, 56, 202, 0.12);
                --radius-xl: 30px;
                --radius-lg: 24px;
                --radius-md: 18px;
            }

            * { box-sizing: border-box; }

            html, body {
                margin: 0;
                min-height: 100%;
                font-family: Inter, "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(91, 92, 230, 0.18), transparent 30%),
                    radial-gradient(circle at bottom right, rgba(16, 185, 129, 0.12), transparent 32%),
                    linear-gradient(135deg, var(--bg) 0%, var(--bg-2) 100%);
                color: var(--text);
            }

            body {
                min-height: 100vh;
                display: flex;
                justify-content: center;
                padding: 26px 16px 40px;
            }

            .page {
                width: min(100%, 820px);
            }

            .shell {
                position: relative;
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, 0.42);
                background: rgba(255, 255, 255, 0.44);
                border-radius: 32px;
                backdrop-filter: blur(16px);
                box-shadow: var(--shadow);
                padding: 24px 18px 18px;
            }

            .shell::before {
                content: "";
                position: absolute;
                inset: -30% auto auto -20%;
                width: 180px;
                height: 180px;
                background: rgba(91, 92, 230, 0.08);
                border-radius: 50%;
                filter: blur(18px);
                pointer-events: none;
            }

            .shell::after {
                content: "";
                position: absolute;
                right: -10%;
                bottom: -20%;
                width: 180px;
                height: 180px;
                background: rgba(16, 185, 129, 0.08);
                border-radius: 50%;
                filter: blur(18px);
                pointer-events: none;
            }

            .hero,
            .summary,
            .plans,
            .notice {
                position: relative;
                z-index: 1;
            }

            .hero {
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-bottom: 18px;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                padding: 7px 12px;
                border-radius: 999px;
                background: var(--primary-soft);
                border: 1px solid rgba(91, 92, 230, 0.15);
                color: var(--primary);
                font-size: 0.72rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .title {
                margin: 0;
                font-size: clamp(2rem, 6vw, 3rem);
                line-height: 0.96;
                letter-spacing: -0.06em;
                font-weight: 900;
            }

            .subtitle {
                margin: 0;
                color: var(--muted);
                font-size: 0.96rem;
                line-height: 1.6;
                max-width: 62ch;
            }

            .summary {
                display: grid;
                grid-template-columns: 1.2fr .8fr;
                gap: 14px;
                padding: 18px 18px;
                border-radius: var(--radius-xl);
                background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 50%, #5b5ce6 100%);
                color: white;
                box-shadow: 0 18px 40px rgba(67, 56, 202, 0.35);
                margin-bottom: 18px;
            }

            .summary-left {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .summary-label {
                font-size: 0.72rem;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                opacity: 0.8;
                font-weight: 700;
            }

            .summary-value {
                font-size: clamp(2.2rem, 5vw, 3.4rem);
                line-height: 1;
                font-weight: 900;
                letter-spacing: -0.08em;
            }

            .summary-meta {
                font-size: 0.82rem;
                opacity: 0.9;
                font-weight: 600;
            }

            .insight {
                display: flex;
                flex-direction: column;
                justify-content: center;
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 20px;
                padding: 12px 14px;
            }

            .insight-value {
                font-size: 1.3rem;
                font-weight: 800;
                letter-spacing: -0.04em;
            }

            .insight-text {
                margin-top: 4px;
                font-size: 0.72rem;
                opacity: 0.8;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }

            .plans {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .plan-form { margin: 0; }

            .plan-button {
                position: relative;
                width: 100%;
                display: grid;
                grid-template-columns: 1.5fr auto;
                align-items: center;
                gap: 18px;
                border: 1px solid var(--line);
                background: rgba(255, 255, 255, 0.72);
                color: var(--text);
                border-radius: 26px;
                padding: 18px 18px;
                cursor: pointer;
                text-align: left;
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.04);
                transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
                animation: riseIn 0.45s ease both;
            }

            .plan-button:nth-child(2) { animation-delay: 0.05s; }
            .plan-button:nth-child(3) { animation-delay: 0.1s; }
            .plan-button:nth-child(4) { animation-delay: 0.15s; }

            .plan-button:hover {
                transform: translateY(-2px);
                box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
                border-color: rgba(91, 92, 230, 0.28);
            }

            .plan-button.selected {
                border-color: rgba(91, 92, 230, 0.82);
                background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(238, 242, 255, 0.95));
                box-shadow: 0 18px 32px rgba(91, 92, 230, 0.16);
            }

            .plan-button.selected::before {
                content: "";
                position: absolute;
                inset: 0 auto 0 0;
                width: 6px;
                background: linear-gradient(180deg, var(--primary), #7c3aed);
                border-radius: 26px 0 0 26px;
            }

            .plan-main {
                display: flex;
                flex-direction: column;
                gap: 8px;
                min-width: 0;
            }

            .plan-head {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }

            .plan-name {
                font-size: 1.1rem;
                font-weight: 800;
                letter-spacing: -0.03em;
            }

            .plan-tag,
            .plan-badge {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 5px 8px;
                border-radius: 999px;
                font-size: 0.65rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .plan-tag {
                background: var(--success-soft);
                color: var(--success);
            }

            .plan-badge {
                background: var(--gold-soft);
                color: var(--gold);
            }

            .plan-meta {
                color: var(--muted);
                font-size: 0.86rem;
                font-weight: 700;
            }

            .plan-desc {
                color: var(--muted);
                font-size: 0.75rem;
                line-height: 1.45;
                max-width: 42ch;
            }

            .price-box {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                justify-content: center;
                gap: 8px;
                min-width: 136px;
            }

            .price {
                font-size: clamp(1.5rem, 4vw, 2rem);
                font-weight: 900;
                line-height: 1;
                letter-spacing: -0.06em;
            }

            .cta {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 100px;
                padding: 8px 12px;
                border-radius: 999px;
                font-size: 0.7rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                background: rgba(91, 92, 230, 0.1);
                color: var(--primary);
            }

            .plan-button.selected .cta {
                background: linear-gradient(135deg, var(--primary), var(--primary-strong));
                color: white;
            }

            .notice {
                margin-top: 18px;
                padding: 12px 15px;
                border-radius: 16px;
                background: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.18);
                color: #0f766e;
                font-size: 0.92rem;
                font-weight: 700;
            }

            @keyframes riseIn {
                from {
                    opacity: 0;
                    transform: translateY(12px) scale(0.98);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }

            @media (max-width: 560px) {
                body { padding: 18px 12px 30px; }
                .shell { padding: 18px 14px 14px; }
                .summary { grid-template-columns: 1fr; }
                .plan-button {
                    grid-template-columns: 1fr;
                    gap: 12px;
                }
                .price-box { align-items: flex-start; }
            }
        </style>
    </head>
    <body>
        <main class="page">
            <div class="shell">
                <header class="hero">
                    <span class="badge">VIN Mini App</span>
                    <h1 class="title">Пакеты отчетов</h1>
                    <p class="subtitle">Выберите удобный тариф и откройте полный доступ к подробной информации по VIN. Быстро, безопасно и без лишних действий.</p>
                </header>

                <section class="summary" aria-label="Остаток отчетов">
                    <div class="summary-left">
                        <div class="summary-label">Остаток</div>
                        <div class="summary-value">{{ $reportsRemaining }}</div>
                        <div class="summary-meta">полных VIN-отчетов доступно</div>
                    </div>

                    <div class="insight">
                        <div class="insight-value">+ бонус</div>
                        <div class="insight-text">быстрый доступ</div>
                    </div>
                </section>

                @if (session('vin-mini-app-action'))
                    <div class="notice">{{ session('vin-mini-app-action') }}</div>
                @endif

                <section class="plans" aria-label="Тарифы">
                    @forelse ($plans as $plan)
                        @php
                            $isSelected = $selectedPlanId !== null && (int) $selectedPlanId === (int) $plan->id;
                            $reportLabel = $plan->report_count === 1 ? 'отчет' : 'отчетов';
                            $isPopular = (int) $plan->report_count >= 20;
                        @endphp

                        <form class="plan-form" method="POST" action="{{ route('vin-mini-app.purchase') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <button type="submit" class="plan-button {{ $isSelected ? 'selected' : '' }}">
                                <div class="plan-main">
                                    <div class="plan-head">
                                        <span class="plan-name">{{ $plan->name }}</span>
                                        @if ($isPopular)
                                            <span class="plan-tag">Популярно</span>
                                        @else
                                            <span class="plan-badge">Пакет</span>
                                        @endif
                                    </div>

                                    <div class="plan-meta">{{ $plan->report_count }} {{ $reportLabel }}</div>

                                    @if (!empty($plan->description))
                                        <div class="plan-desc">{{ $plan->description }}</div>
                                    @endif
                                </div>

                                <div class="price-box">
                                    <div class="price">{{ number_format((float) $plan->price_rub, 0, ',', ' ') }} ₽</div>
                                    <div class="cta">{{ $isSelected ? 'Выбрано' : 'Купить' }}</div>
                                </div>
                            </button>
                        </form>
                    @empty
                        <div class="notice">Пока нет доступных тарифов.</div>
                    @endforelse
                </section>
            </div>
        </main>

        <script>
            const telegramApp = window.Telegram && window.Telegram.WebApp;

            if (telegramApp) {
                telegramApp.ready();
                telegramApp.expand();

                const theme = telegramApp.themeParams || {};
                document.documentElement.style.setProperty('--bg', theme.bg_color || '#eef4ff');
                document.documentElement.style.setProperty('--bg-2', theme.secondary_bg_color || '#f8fbff');
                document.documentElement.style.setProperty('--text', theme.text_color || '#0f172a');
                document.documentElement.style.setProperty('--muted', theme.hint_color || '#64748b');
                document.documentElement.style.setProperty('--primary', theme.button_color || '#5b5ce6');
                document.documentElement.style.setProperty('--primary-strong', theme.button_text_color || '#4338ca');
                document.documentElement.style.setProperty('--primary-soft', theme.button_color ? 'rgba(91, 92, 230, 0.12)' : 'rgba(91, 92, 230, 0.12)');
            }
        </script>
    </body>
</html>
