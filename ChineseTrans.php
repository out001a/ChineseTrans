<?php
class ChineseTrans {
    /**
     * 汉字转拼音（汉字编码是utf-8）
     *
     * @param $str
     *      要进行转换的字符串
     * @param $fuzzy
     *      是否启用转模糊音，默认开启
     * @param $strict
     *      是否启用严格模式，默认开启。在严格模式下，任何没有匹配到拼音的字符都被忽略，不出现在结果中；否则会把这些字符按原样添加到结果里
     * @param $py_table
     *      使用指定的拼音表，既可以是数组，也可以是一个php文件的路径。格式见默认的 ./tables/py_table.php
     * @return array
     *
     * @author shanhuanming
     */
    public static function pinyin($str, $fuzzy=true, $strict=true, $py_table=array()) {
        if (!$py_table) {
            $py_table = @include(__DIR__ . '/tables/py_table.php'); // 用require的话，当文件不存在时会引发一个 fatal error
        } else {
            if (!is_array($py_table)) {
                $py_table = @include($py_table);
            }
        }
        if (!$py_table || !is_array($py_table)) {
            throw new Exception('pinyin table is empty!', -1);
        }

        $py_list = array();
        $py_temp = array();
        $chars = self::utf8StrSplit($str);

        foreach ($chars as $char) {
            $char_pys = array();
            if (isset($py_table[$char])) {
                $char_pys = explode(',', $py_table[$char]);
            } else {
                if ($strict) {
                    continue;
                } else {
                    $char_pys = array($char);
                }
            }

            foreach ($char_pys as $py) {
                if (empty($py_list)) {
                    $py_temp[] = $py;
                } else {
                    foreach ($py_list as $v) {
                        $py_temp[] = "{$v}{$py}";
                    }
                }
            }

            $py_list = $py_temp;
            $py_temp = array();
        }

        return $py_list;
    }

    /**
     * 将搜索词按alt_table里的映射进行替换
     * 具体的映射规则在/web/dataSource/tables/alt_table.php里可以看到
     *
     * homophone_table(.mini).php是比alt_table更全的表，包含对同音多音字的转换，如果有需要可以使用（但是文件更大，转换和搜索效率会更低）
     *
     * @param $str
     *      要进行转换的字符串
     * @param $return_as_array
     *      返回一个数组还是字符串。默认false返回字符串
     * @param $brace_polyphone_remove_bar
     *      去除多音字value中的竖线(|)，并为其加上{}。这个参数仅在建索引时用到，且只有使用同音多音字转换($use_homophone=true)时才有效
     * @return string|array
     *
     * @author shanhuanming
     */
    public static function altSearchStr($str, $return_as_array=false, $brace_polyphone_remove_bar=false) {
        // 是否启用同音多音字转换。默认不启用(false)，若要启用就改成true
        // 改完后搜索词会按新规则转换，而索引字段alt的转换只有修改了索引机器上的该变量并跑完索引之后才会生效
        $use_homophone = false;

        if ($use_homophone) {
            $table_name = 'homophone_table.incomplete.mini';
        } else {
            $table_name = 'alt_table';
        }
        $table = @include(__DIR__ . "/tables/{$table_name}.php");
        if (!$table || !is_array($table)) {
            throw new Exception("table is empty! - {$table_name}", -1);
        }

        // return str_replace(array_keys($table), array_values($table), $str); // 效率太低
        $mapped = array();

        $chars  = self::utf8StrSplit($str);
        foreach ($chars as $char) {
            if (isset($table[$char])) {
                if ($use_homophone && $brace_polyphone_remove_bar && strpos($table[$char], '|')) {
                    $mapped[] = "{" . str_replace('|', '', $table[$char]) . "}";
                } else {
                    $mapped[] = $table[$char];
                }
            } else {
                $mapped[] = $char;
            }
        }

        if ($return_as_array) {
            return $mapped;
        } else {
            return implode('', $mapped);
        }
    }

    static function utf8StrSplit($str) {
        $split = 1;
        $array = array();
        for($i = 0; $i < strlen($str); ) {
            $value = ord($str[$i]);
            if ($value < 128) {
                $split = 1;
            } else {
                if ($value >= 192 && $value <= 223) {
                    $split = 2;
                } elseif ($value >= 224 && $value <= 239) {
                    $split = 3;
                } elseif ($value >= 240 && $value <= 247) {
                    $split = 4;
                }
            }
            $key = NULL;
            for ($j = 0; $j < $split; $j++, $i++) {
                $key .= $str[$i];
            }
            array_push($array, $key);
        }
        return $array;
    }

}
