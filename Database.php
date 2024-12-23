<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $i = -1;
        $query_string = preg_replace_callback(
            '/(\?)[d|f|a|#|$]|(\?)/x',
            function ($matches) use (&$i, $args) {
                ++$i;
                $str = match ($matches[0]) {
                    '?#' => $this->checkNullAndSkip($args[$i]) ?? match (true) {
                        is_string($args[$i]) => "`{$args[$i]}`",
                        is_array($args[$i]) => implode(
                            ", ",
                            array_map(fn($a) => "`{$a}`", $args[$i]),
                        ),
                        default => throw new Exception(),
                    },
                    '?a' => $this->checkNullAndSkip($args[$i]) ?? match (true) {
                        is_array($args[$i]) => (function () use ($i, $args) {
                            if (array_is_list($args[$i])) {
                                return implode(", ", $args[$i]);
                            } else {
                                $arraySet = array_map(
                                    function ($k, $v): string {
                                        $b = $this->formatValue($v);

                                        return "`$k` = " . (is_string($b)
                                                ? addslashes($b) : $b);
                                    },
                                    array_keys($args[$i]),
                                    array_values($args[$i]),
                                );

                                return implode(", ", $arraySet);
                            }
                        })(),
                        default => throw new Exception('Ошибка спецификатора'),
                    },
                    '?d' => $this->checkNullAndSkip($args[$i]) ?? intval($args[$i]),
                    '?f' => $this->checkNullAndSkip($args[$i]) ?? floatval($args[$i]),
                    '?' => $this->checkNullAndSkip($args[$i]) ?? match (true) {
                        is_string($args[$i]) => addslashes("'{$args[$i]}'"),
                        default => $this->formatValue($args[$i])
                    },
                    default => throw new Exception('Неправильный идентификатор'),
                };

                return $str;
            },
            $query,
        );

        $regex_skip = "/[\{](.+)?({$this->skipValue()}){1}(.+)?[\}$]/";
        return preg_replace([$regex_skip, '[{|}]'], [''], $query_string);
    }

    /**
     * Специальное значение, размещенный внутри блока {специальное_значение}
     * запроса означает, что данный блок должен быть удален с данного запроса
     *
     * @return string
     */
    public function skipValue(): string
    {
        return 'skip_for_block';
    }

    /**
     * Проверяем типы входящего параметра и
     * форматируем в зависимости от типа
     *
     * @param mixed $value
     *
     * @return float|int|string|Exception
     * @throws Exception
     */
    private function formatValue(mixed $value): float|int|string|Exception
    {
        return match (true) {
            is_bool($value) => true ? 1 : 0,
            is_string($value) => "'$value'",
            is_int($value), is_float($value) => $value,
            is_null($value) => 'NULL',
            default => throw new Exception('Ошибка типа'),
        };
    }

    /**
     * Проверка на null и специальное значение
     * @param mixed $value
     *
     * @return mixed
     */
    private function checkNullAndSkip(mixed $value): mixed
    {
        return match (true) {
            is_null($value) => 'NULL',
            $this->skipValue() === $value => $value,
            default => null
        };
    }
}
