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

            html[data-theme="dark"] {
                --bg: #0f172a;
                --bg-2: #111827;
                --card: rgba(15, 23, 42, 0.86);
                --card-strong: #111827;
                --text: #e5eefb;
                --muted: #a5b4cf;
                --primary: #8b8cff;
                --primary-strong: #6c6ef3;
                --primary-soft: rgba(139, 140, 255, 0.18);
                --success: #34d399;
                --success-soft: rgba(52, 211, 153, 0.14);
                --gold: #fbbf24;
                --gold-soft: rgba(251, 191, 36, 0.16);
                --line: rgba(148, 163, 184, 0.22);
                --shadow: 0 26px 55px rgba(2, 6, 23, 0.52);
                --soft-panel: rgba(17, 24, 39, 0.9);
                --soft-panel-strong: rgba(15, 23, 42, 0.96);
                --button-soft: rgba(139, 140, 255, 0.14);
                --button-text: #f8faff;
                --modal-bg: rgba(15, 23, 42, 0.74);
                --modal-card: rgba(17, 24, 39, 0.94);
                --modal-border: rgba(148, 163, 184, 0.16);
                --success-pill: rgba(52, 211, 153, 0.12);
            }

            :root {
                --soft-panel: rgba(255, 255, 255, 0.76);
                --soft-panel-strong: rgba(255, 255, 255, 0.92);
                --button-soft: rgba(91, 92, 230, 0.1);
                --button-text: #ffffff;
                --modal-bg: rgba(15, 23, 42, 0.22);
                --modal-card: rgba(255, 255, 255, 0.94);
                --modal-border: rgba(148, 163, 184, 0.16);
                --success-pill: rgba(16, 185, 129, 0.12);
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

            html[data-theme="dark"] .shell {
                background: rgba(15, 23, 42, 0.7);
                border-color: rgba(148, 163, 184, 0.2);
            }

            html[data-theme="dark"] .summary {
                box-shadow: 0 18px 40px rgba(91, 92, 230, 0.22);
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
                font-size: clamp(1.4rem, 3vw, 2rem);
                line-height: 1.08;
                letter-spacing: -0.04em;
                font-weight: 800;
                text-align: center;
            }

            .subtitle {
                margin: 0;
                color: var(--muted);
                font-size: 0.96rem;
                line-height: 1.6;
                max-width: 62ch;
            }

            .summary {
                display: flex;
                flex-direction: column;
                gap: 8px;
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

            .plans {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .plan-form { margin: 0; }

            .plan-button {
                position: relative;
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                border: 1px solid var(--line);
                background: var(--soft-panel);
                color: var(--text);
                border-radius: 18px;
                padding: 12px 14px;
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
                background: linear-gradient(135deg, var(--soft-panel-strong), rgba(91, 92, 230, 0.08));
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
                flex: 1;
                min-width: 0;
                justify-content: flex-start;
                align-items: center;
            }

            .plan-head {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
                justify-content: flex-start;
                width: 100%;
            }

            .plan-name {
                font-size: 1.06rem;
                font-weight: 800;
                letter-spacing: -0.03em;
                margin-right: auto;
            }


            .plan-meta {
                color: var(--muted);
                font-size: 0.8rem;
                font-weight: 700;
                line-height: 1.2;
            }

            .plan-desc {
                display: none;
            }

            .price-box {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 10px;
                min-width: 210px;
                flex-shrink: 0;
            }

            .price {
                font-size: clamp(1.1rem, 2.6vw, 1.6rem);
                font-weight: 800;
                line-height: 1;
                letter-spacing: -0.04em;
                margin-left: auto;
            }

            .cta {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 82px;
                padding: 8px 12px;
                border-radius: 999px;
                font-size: 0.64rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                background: var(--button-soft);
                color: var(--primary);
                border: 1px solid rgba(91, 92, 230, 0.12);
            }

            .plan-button.selected .cta {
                background: linear-gradient(135deg, var(--primary), var(--primary-strong));
                color: var(--button-text);
                border-color: transparent;
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

            html[data-theme="dark"] .notice {
                background: rgba(16, 185, 129, 0.12);
                border-color: rgba(52, 211, 153, 0.2);
                color: #b8f5de;
            }

            .loader-overlay {
                position: fixed;
                inset: 0;
                display: none;
                align-items: center;
                justify-content: center;
                background: var(--modal-bg);
                backdrop-filter: blur(9px);
                z-index: 50;
                padding: 20px;
            }

            .loader-overlay.visible {
                display: flex;
            }

            .loader-card {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 12px;
                padding: 18px 20px 16px;
                border-radius: 28px;
                background: var(--modal-card);
                color: var(--text);
                border: none;
                box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
                text-align: center;
                min-width: 248px;
                max-width: 292px;
                animation: modalIn 0.22s ease;
            }

            .loader-note {
                display: none;
                width: 100%;
                margin-top: 6px;
                padding-top: 8px;
                border-top: 1px solid var(--line);
                color: var(--muted);
                font-size: 0.74rem;
                line-height: 1.5;
                font-weight: 700;
            }

            .spinner {
                width: 34px;
                height: 34px;
                border-radius: 50%;
                border: 3px solid rgba(91, 92, 230, 0.16);
                border-top-color: var(--primary);
                box-shadow: 0 0 0 6px rgba(91, 92, 230, 0.05);
                animation: spin 0.9s linear infinite;
            }

            .loader-text {
                margin: 0;
                font-size: 0.95rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                line-height: 1.45;
                color: var(--text);
                opacity: 0.92;
            }

            .successful-badge {
                display: none;
                padding: 8px 12px;
                border-radius: 999px;
                background: var(--success-pill);
                color: var(--success);
                font-size: 0.76rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .success-phase .loader-text {
                font-size: 1.04rem;
                color: var(--success);
                font-weight: 800;
                opacity: 1;
            }

            .success-phase .spinner {
                display: none;
            }

            .success-phase .successful-badge {
                display: inline-flex;
            }

            .success-phase .loader-note {
                display: block;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            @keyframes modalIn {
                from {
                    opacity: 0;
                    transform: translateY(8px) scale(0.98);
                }
                to {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
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
                    <h1 class="title">Пакеты отчетов</h1>
                    <p class="subtitle">Выберите удобный тариф и откройте полный доступ к подробной информации по VIN. Быстро, безопасно и без лишних действий.</p>
                </header>

                <section class="summary" aria-label="Остаток отчетов">
                    <div class="summary-left">
                        <div class="summary-label">Остаток</div>
                        <div class="summary-value">{{ $reportsRemaining }}</div>
                        <div class="summary-meta">полных VIN-отчетов доступно</div>
                    </div>
                </section>

                <section class="plans" aria-label="Тарифы">
                    @forelse ($plans as $plan)
                        @php
                            $isSelected = $selectedPlanId !== null && (int) $selectedPlanId === (int) $plan->id;
                        @endphp

                        <form class="plan-form" method="POST" action="{{ route('vin-mini-app.purchase') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="bot_id" value="{{ request('bot_id') }}">
                            <input type="hidden" name="chat_id" value="{{ request('chat_id') }}">
                            <button type="submit" class="plan-button {{ $isSelected ? 'selected' : '' }}">
                                <div class="plan-main">
                                    <div class="plan-head">
                                        <span class="plan-name">{{ $plan->report_count }} отчёт</span>
                                    </div>
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

        <div class="loader-overlay" id="purchase-loader" aria-live="polite">
            <div class="loader-card">
                <div class="spinner"></div>
                <div class="successful-badge">Готово</div>
                <div class="loader-text">Проверяем вашу подписку…</div>

                <div class="loader-note">
                    Вернуться в бота можно через крестик в левом верхнем углу приложения.
                </div>
            </div>
        </div>

        <script>
            const telegramApp = window.Telegram && window.Telegram.WebApp;
            const loader = document.getElementById('purchase-loader');

            function closeMiniApp() {
                if (telegramApp && typeof telegramApp.close === 'function') {
                    try {
                        telegramApp.close();
                        return true;
                    } catch (error) {
                        console.warn('Telegram WebApp close failed:', error);
                    }
                }

                return false;
            }

            document.querySelectorAll('.plan-form').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (!loader) {
                        form.submit();
                        return;
                    }

                    const submitButton = form.querySelector('.plan-button');
                    const loaderText = loader.querySelector('.loader-text');
                    const formData = new FormData(form);

                    loader.classList.remove('success-phase');
                    loader.classList.add('visible');

                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: formData,
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok || !payload.success) {
                            throw new Error(payload.message || 'Покупка не прошла');
                        }

                        if (loaderText) {
                            loaderText.textContent = `Пакет ${payload.report_count || 0} уже в вашем доступе`;
                        }
                        loader.classList.add('success-phase');
                    } catch (error) {
                        if (loaderText) {
                            loaderText.textContent = 'Не удалось оформить подписку. Попробуйте ещё раз.';
                        }
                    }
                });
            });

            function applyTelegramTheme(scheme) {
                const root = document.documentElement;
                const isDark = scheme === 'dark';
                root.setAttribute('data-theme', isDark ? 'dark' : 'light');

                const theme = telegramApp?.themeParams || {};
                const bg = theme.bg_color || (isDark ? '#0f172a' : '#eef4ff');
                const bg2 = theme.secondary_bg_color || (isDark ? '#111827' : '#f8fbff');
                const text = theme.text_color || (isDark ? '#e5eefb' : '#0f172a');
                const muted = theme.hint_color || (isDark ? '#a5b4cf' : '#64748b');
                const primary = theme.button_color || '#5b5ce6';
                const primaryStrong = theme.button_text_color || '#4338ca';

                root.style.setProperty('--bg', bg);
                root.style.setProperty('--bg-2', bg2);
                root.style.setProperty('--text', text);
                root.style.setProperty('--muted', muted);
                root.style.setProperty('--primary', primary);
                root.style.setProperty('--primary-strong', primaryStrong);
                root.style.setProperty('--primary-soft', `${primary}1F`);
            }

            if (telegramApp) {
                telegramApp.ready();
                telegramApp.expand();

                const preferredScheme = telegramApp.colorScheme ||
                    (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

                applyTelegramTheme(preferredScheme);
                telegramApp.onEvent('themeChanged', () => {
                    applyTelegramTheme(telegramApp.colorScheme || preferredScheme);
                });
            } else {
                const systemTheme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                applyTelegramTheme(systemTheme);
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', (event) => {
                    applyTelegramTheme(event.matches ? 'dark' : 'light');
                });
            }
        </script>
    </body>
</html>
