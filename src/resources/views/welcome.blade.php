<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'X-Bot') }}</title>
        <style>
            :root {
                --bg-start: #f4f7ff;
                --bg-end: #eefbf7;
                --text: #10212e;
                --muted: #486174;
                --card: #ffffff;
                --line: #d6e3ef;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
                color: var(--text);
                background: radial-gradient(circle at 10% 20%, #ffffff 0%, var(--bg-start) 45%, var(--bg-end) 100%);
            }

            .container {
                max-width: 1100px;
                margin: 0 auto;
                padding: 28px 16px 48px;
            }

            .hero {
                background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 60%, #e8fff8 100%);
                border: 1px solid var(--line);
                border-radius: 20px;
                padding: 28px;
                box-shadow: 0 14px 34px rgba(18, 57, 97, 0.08);
            }

            .hero h1 {
                margin: 0 0 12px;
                font-size: clamp(1.8rem, 4.2vw, 2.8rem);
                line-height: 1.1;
            }

            .hero p {
                margin: 0;
                font-size: 1.05rem;
                color: var(--muted);
                max-width: 760px;
                line-height: 1.55;
            }

            .bots {
                margin-top: 24px;
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
            }

            .bot-card {
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 16px;
                padding: 18px;
                box-shadow: 0 8px 24px rgba(31, 61, 94, 0.07);
                min-height: 180px;
            }

            .bot-icon {
                width: 46px;
                height: 46px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 12px;
                background: #f2f8ff;
                font-size: 1.5rem;
                margin-bottom: 12px;
            }

            .bot-card h3 {
                margin: 0 0 8px;
                font-size: 1.15rem;
            }

            .bot-card p {
                margin: 0;
                color: var(--muted);
                line-height: 1.5;
                font-size: 0.95rem;
            }

            .footer-link {
                margin-top: 20px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: #0b61d3;
                text-decoration: none;
                font-weight: 600;
            }

            .footer-link:hover {
                text-decoration: underline;
            }

            @media (max-width: 900px) {
                .bots {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <main class="container">
            <section class="hero">
                <h1>Сайт ботов X-Bot</h1>
                <p>
                    Мы собрали полезных Telegram-ботов в одном месте: от быстрых погодных сводок и проверки VIN до
                    коротких предсказаний для настроения. Выбирайте нужного помощника и запускайте сразу в чате.
                </p>

                <div class="bots">
                    <article class="bot-card">
                        <div class="bot-icon" aria-hidden="true">🌤️</div>
                        <h3>Погодный бот</h3>
                        <p>
                            Показывает актуальную погоду и краткий прогноз по городу. Удобно для быстрых проверок перед
                            поездкой, прогулкой или рабочим днем.
                        </p>
                    </article>

                    <article class="bot-card">
                        <div class="bot-icon" aria-hidden="true">🚗</div>
                        <h3>VIN-бот</h3>
                        <p>
                            Помогает работать с VIN-кодами автомобиля: быстрые подсказки, проверка структуры VIN и удобный
                            формат ответа прямо в Telegram.
                        </p>
                    </article>

                    <article class="bot-card">
                        <div class="bot-icon" aria-hidden="true">🔮</div>
                        <h3>Оракул-предсказатель</h3>
                        <p>
                            Небольшой бот для вдохновения: короткие предсказания и советы, когда нужно принять решение
                            или просто поднять настроение.
                        </p>
                    </article>
                </div>

                <a class="footer-link" href="{{ url('/admin') }}">Перейти в закрытую админ-панель</a>
            </section>
        </main>
    </body>
</html>
