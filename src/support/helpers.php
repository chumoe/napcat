<?php

if (!function_exists('is_json')) {
    /**
     * 检查字符串是否为有效的JSON格式，并解析为数组
     *
     * @param string $string 需要检查的字符串
     * @param mixed $array 引用参数，用于存储解析后的JSON数组
     * @return bool 如果字符串是有效的JSON格式返回true，否则返回false
     */
    function is_json(string $string, mixed &$array = []): bool
    {
        // 尝试将字符串解码为PHP关联数组
        $array = json_decode($string, true);
        // 检查解码结果是否不为null且JSON解码过程中是否没有发生错误
        return $array !== null && json_last_error() === JSON_ERROR_NONE;
    }
}