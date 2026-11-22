<?php

namespace App\Services\Psychologist;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyButton;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;

class PsychologistMessageBuilder
{
    public const BTN_TALK = "💬 Поговорить";
    public const BTN_RESET = "🔄 Начать заново";
    public const BTN_EXERCISE = "🧘 Упражнение";
    public const BTN_MOOD = "📊 Дневник";
    public const BTN_HELP = "🆘 Помощь";
    public const BTN_SETTINGS = "⚙️ Настройки";

    public function consentText(): string
    {
        return "Привет. Я — Тихо.\n\n"
            . "Здесь можно спокойно поговорить о том, что происходит внутри: о тревоге, усталости, отношениях или о том, что пока трудно назвать словами.\n\n"
            . "Я не врач и не психотерапевт. Я не ставлю диагнозы, не назначаю лекарства и не заменяю живого специалиста. Если тебе сейчас угрожает опасность, пожалуйста, звони 112 или 103.\n\n"
            . "Я работаю с текстом, мне 18+. Всё, что ты напишешь, не сохраняется в логах переписки. Я могу запомнить только краткое резюме, чтобы не начинать каждый раз с нуля. Его можно удалить в любой момент.\n\n"
            . "Если это подходит — нажми кнопку ниже.";
    }

    public function startText(): string
    {
        return "Я здесь. Можно начать с одного вопроса: что сейчас занимает больше всего места внутри?\n\n"
            . "Не нужно формулировать красиво. Достаточно пары слов.";
    }

    public function helpText(): string
    {
        return "Как я могу помочь:\n\n"
            . "• «Поговорить» — свободный разговор и поддержка.\n"
            . "• «Упражнение» — дыхание, заземление или разбор мысли.\n"
            . "• «Дневник» — отметить настроение по шкале 1–10.\n"
            . "• «Настройки» — выбрать стиль общения.\n"
            . "• «Начать заново» — новая сессия, память сохраняется.\n"
            . "• «Забыть переписку» — удалить память.\n\n"
            . "Я не ставлю диагнозы, не назначаю лекарства и не заменяю терапию. В кризисной ситуации звони 112 или 103.\n\n"
            . "Память можно удалить в любой момент — кнопка «Забыть переписку».";
    }

    public function crisisText(): string
    {
        return "Мне важно, чтобы ты был(а) в безопасности. Если сейчас есть мысли причинить себе вред или рядом есть опасность — пожалуйста, не оставайся один(а).\n\n"
            . "Позвони 112 — единый номер экстренных служб, или 103 — скорая помощь. Напиши близкому человеку, которому доверяешь, и скажи, что тебе нужна поддержка прямо сейчас.\n\n"
            . "Если можешь, ответь одним словом: ты сейчас в безопасности?";
    }

    public function forgetConfirmText(): string
    {
        return "Удалить сохранённую память обо мне?\n\n"
            . "Я забуду темы, цели и краткие резюме прошлых разговоров. Текущий диалог продолжится, но контекст тоже очистится. Технические записи без текста останутся для диагностики.";
    }

    public function forgetDoneText(): string
    {
        return "Готово. Я больше не помню прошлые темы и не храню переписку.\n\n"
            . "Если захочешь начать заново — просто напиши, что происходит.";
    }

    public function forgetEmptyText(): string
    {
        return "Сейчас у меня нет сохранённой памяти о тебе. Можно начинать с чистого листа.";
    }

    public function moodText(): string
    {
        return "Как ты сейчас, по шкале от 1 до 10?\n\n"
            . "1 — очень тяжело, 10 — спокойно и есть силы. Здесь нет правильного ответа.";
    }

    public function moodSavedText(int $score): string
    {
        $support = match (true) {
            $score <= 3 => "Спасибо, что отметил(а). Если сейчас совсем тяжело, давай попробуем что-то очень маленькое — например, дыхание или заземление.",
            $score <= 6 => "Спасибо. Средние значения тоже важны: они показывают, что день был непростым, но ты справляешься.",
            default => "Спасибо. Хорошо, что есть силы. Можно сохранить это состояние и понять, что именно помогает.",
        };

        return "Записал(а): {$score}/10.\n\n{$support}";
    }

    public function exerciseMenuText(): string
    {
        return "Выбери упражнение:\n\n"
            . "• Дыхание — успокоить тело за 2 минуты.\n"
            . "• Заземление — вернуться в момент, когда мысли уносят.\n"
            . "• Разбор мысли — проверить мысль, которая давит.";
    }

    public function breathingText(): string
    {
        return "Дыхание 4–6\n\n"
            . "Сделай вдох на 4 счёта и выдох на 6. Не глубоко, а мягко. Повтори 6–8 раз.\n\n"
            . "Если закружилась голова — вернись к обычному дыханию.\n\n"
            . "Что заметил(а) в теле после нескольких циклов?";
    }

    public function groundingText(): string
    {
        return "Заземление 5–4–3–2–1\n\n"
            . "Назови про себя:\n"
            . "5 вещей, которые видишь,\n"
            . "4 — которые слышишь,\n"
            . "3 — которых можешь коснуться,\n"
            . "2 запаха,\n"
            . "1 вкус.\n\n"
            . "Не спеши. Что из этого получилось заметить?";
    }

    public function thoughtRecordText(): string
    {
        return "Разбор мысли\n\n"
            . "1. Что произошло?\n"
            . "2. Какая мысль появилась?\n"
            . "3. Что она говорит о тебе или о будущем?\n"
            . "4. Какие есть факты за и против?\n"
            . "5. Как бы ты сказал(а) другу в такой ситуации?\n\n"
            . "Можно ответить не по пунктам, а как удобно.";
    }

    public function settingsText(): string
    {
        return "Как тебе комфортнее общаться?\n\n"
            . "Мягко — больше поддержки и бережности.\n"
            . "Конкретно — меньше метафор, больше шагов.\n"
            . "Коротко — 1–3 предложения.";
    }

    public function styleSavedText(string $style): string
    {
        $label = match ($style) {
            "direct" => "конкретно",
            "brief" => "коротко",
            default => "мягко",
        };

        return "Хорошо, буду общаться {$label}. Если захочешь изменить — открой «Настройки».";
    }

    public function summaryEmptyText(): string
    {
        return "Пока нечего подводить: в текущей сессии ещё нет разговора. Можно просто начать.";
    }

    public function unknownText(): string
    {
        return "Я рядом. Можно написать, что происходит, или выбрать действие на клавиатуре.";
    }

    public function offlineText(): string
    {
        return "Мне не удалось ответить прямо сейчас. Это техническая пауза, не твоя вина. Попробуй ещё раз через минуту или напиши чуть короче.";
    }

    public function mainReplyKeyboard(): ReplyKeyboard
    {
        return ReplyKeyboard::make()
            ->buttons([
                ReplyButton::make(self::BTN_TALK),
                ReplyButton::make(self::BTN_RESET),
                ReplyButton::make(self::BTN_EXERCISE),
                ReplyButton::make(self::BTN_MOOD),
                ReplyButton::make(self::BTN_HELP),
                ReplyButton::make(self::BTN_SETTINGS),
            ])
            ->chunk(2)
            ->resize(true)
            ->persistent(true);
    }

    public function consentKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("Понятно, начать")->action("psychologist_consent"),
            ])
            ->chunk(1);
    }

    public function helpKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("Забыть переписку")->action("psychologist_forget"),
                Button::make("Настройки")->action("psychologist_settings"),
                Button::make("Экстренная помощь")->action("psychologist_crisis"),
            ])
            ->chunk(2);
    }

    public function forgetKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("Да, забыть")->action("psychologist_forget_confirm"),
                Button::make("Отмена")->action("psychologist_forget_cancel"),
            ])
            ->chunk(2);
    }

    public function moodKeyboard(): Keyboard
    {
        $buttons = [];
        for ($score = 1; $score <= 10; $score++) {
            $buttons[] = Button::make((string) $score)->action("psychologist_mood_{$score}");
        }

        return Keyboard::make()->buttons($buttons)->chunk(5);
    }

    public function exerciseKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("Дыхание")->action("psychologist_exercise_breathing"),
                Button::make("Заземление")->action("psychologist_exercise_grounding"),
                Button::make("Разбор мысли")->action("psychologist_exercise_thought"),
            ])
            ->chunk(1);
    }

    public function settingsKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("Мягко")->action("psychologist_style_warm"),
                Button::make("Конкретно")->action("psychologist_style_direct"),
                Button::make("Коротко")->action("psychologist_style_brief"),
                Button::make("Забыть переписку")->action("psychologist_forget"),
            ])
            ->chunk(2);
    }

    public function afterReplyKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("Итог сессии")->action("psychologist_summary"),
                Button::make("Упражнение")->action("psychologist_exercise"),
                Button::make("Забыть переписку")->action("psychologist_forget"),
            ])
            ->chunk(2);
    }

    public function afterExerciseKeyboard(): Keyboard
    {
        return Keyboard::make()
            ->buttons([
                Button::make("Ещё упражнение")->action("psychologist_exercise"),
                Button::make("Продолжить разговор")->action("psychologist_talk"),
                Button::make("Забыть переписку")->action("psychologist_forget"),
            ])
            ->chunk(2);
    }
}
