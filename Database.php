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

    public function buildQuery(string $query, array $args = []): string
    {
        try {
            $i = -1;
            $query_string = preg_replace_callback(
                '/(\?)[d|f|a|#|$]|(\?)/x',
                function ($matches) use (&$i, $args) {
                    ++$i;
                    $str = match ($matches[0]) {
                        '?#' => match (true) {
                            is_null($args[$i]) => 'NULL',
                            $this->skip() === $args[$i] => $args[$i],
                            is_string($args[$i]) => "`{$args[$i]}`",
                            is_array($args[$i]) => implode(", ", $args[$i] = array_map(fn($a) => "`{$a}`", $args[$i])),
                            default => throw new Exception(),
                        },
                        '?a' => match (true) {
                            is_null($args[$i]) => 'NULL',
                            $this->skip() === $args[$i] => $args[$i],
                            is_array($args[$i]) => (function () use ($i, $args) {
                                if (array_is_list($args[$i])) {
                                    return implode(", ", $args[$i]);
                                } else {
                                    $arraySet = array_map(function ($k, $v): string {
                                        $b = $this->formatValue($v);

                                        return "`$k` = ".(is_string($b) ? addslashes($b) : $b);
                                    }, array_keys($args[$i]), array_values($args[$i]));

                                    return implode(", ", $arraySet);
                                }
                            })(),
                            default => throw new Exception(),
                        },
                        '?d' => match (true) {
                            is_null($args[$i]) => 'NULL',
                            $this->skip() === $args[$i] => $args[$i],
                            default => intval($args[$i])
                        },
                        '?f' => match (true) {
                            is_null($args[$i]) => 'NULL',
                            $this->skip() === $args[$i] => $args[$i],
                            default => floatval($args[$i])
                        },
                        '?' => match (true) {
                            is_null($args[$i]) => 'NULL',
                            $this->skip() === $args[$i] => $args[$i],
                            is_string($args[$i]) => addslashes("'{$args[$i]}'"),
                            default => $this->formatValue($args[$i])
                        },
                        default => throw new Exception(),
                    };

                    return $str;
                },
                $query
            );

            $regex_skip = "/[\{](.+)?({$this->skip()}){1}(.+)?[\}$]/";
            return preg_replace([$regex_skip, '[{|}]'], [''], $query_string);

        } catch (Exception $e) {
            echo $e->getMessage();
            throw new Exception();
        }
    }

    /**
     * Специальное занчение, присутствие которого внутри блока { специальное_значение }
     * запроса означает, что данный блок должен быть удален с данного запроса
     * @return string
     */
    public function skip(): string
    {
        return 'skip_for_block';
    }

    /**
     * Проверяем типы входищего параметра и форматируем
     * в зависимости от типа
     *
     * @param mixed $value
     *
     * @return float|int|string|Exception
     * @throws Exception
     */
    private function formatValue(mixed $value): float|int|string|Exception
    {
        $result = match (true) {
            is_bool($value) => true ? 1 : 0,
            is_string($value) => "'$value'",
            is_int($value) => $value,
            is_null($value) => 'NULL',
            is_float($value) => $value,
            default => throw new Exception(),
        };

        return $result;
    }
}
