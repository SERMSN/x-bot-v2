<?php

namespace App\Services\Telegram\Support;

class CallbackAction
{
    public static function parse(mixed $data): string
    {
        if (is_object($data) && method_exists($data, "get")) {
            try {
                $action = $data->get("action");
                if (is_string($action) && $action !== "") {
                    return $action;
                }
            } catch (\Throwable) {
                return "";
            }
        }

        if (is_array($data) && isset($data["action"])) {
            return (string) $data["action"];
        }

        if (!is_string($data)) {
            return "";
        }

        $value = trim($data);
        if ($value === "") {
            return "";
        }

        if (str_starts_with($value, "{")) {
            $decoded = json_decode($value, true);
            if (is_array($decoded) && isset($decoded["action"])) {
                return (string) $decoded["action"];
            }
        }

        $action = str_replace('{"action":"', "", $value);
        $action = str_replace('"}', "", $action);

        return trim($action, '"\'');
    }
}
