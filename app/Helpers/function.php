<?php

if (! function_exists('formatNumber')) {
    function formatNumber($number, $decimalSeparator = '.', $thousandsSeparator = ','): string
    {
        if (! is_numeric($number)) {
            return $number;
        }

        $decimals = floor($number) == $number ? 0 : 2;

        return number_format($number, $decimals, $decimalSeparator, $thousandsSeparator);
    }
}

if (!function_exists('human_amount_cn')) {
    /**
     * 数值转中文口语单位（万/亿）
     * 例：9500 => "9,500"
     *     10500 => "1.05万"
     *     100000 => "10万"
     *     12345678 => "1234.57万"
     *     100000000 => "1亿"
     *
     * @param float|int|string $num
     * @param int $decimals 保留小数位（万/亿时）
     * @param bool $trimZero 是否去掉尾随0
     */
    function human_amount_cn($num, int $decimals = 2, bool $trimZero = true): string
    {
        if ($num === null || $num === '') return '0';

        $n = (float)$num;
        $sign = $n < 0 ? '-' : '';
        $n = abs($n);

        $format = function (float $v) use ($decimals, $trimZero): string {
            $s = number_format($v, $decimals, '.', '');
            if ($trimZero && str_contains($s, '.')) {
                $s = rtrim(rtrim($s, '0'), '.');
            }
            return $s;
        };

        if ($n >= 100000000) {          // 亿
            return $sign . $format($n / 100000000) . '亿';
        }
        if ($n >= 10000) {              // 万
            return $sign . $format($n / 10000) . '万';
        }

        // 小于 1 万：按普通数字展示（可选加千分位）
        return $sign . number_format($n, 0, '.', ',');
    }
}
