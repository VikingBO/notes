<?php
/**
 * Created by PhpStorm.
 * User: a.pilipenko
 * Date: 18.12.14
 * Time: 12:54
 */

/**
 * Сортируем многомерный массив по значению вложенного массива
 * @param $array array многомерный массив который сортируем
 * @param $separator string название поля вложенного массива по которому необходимо отсортировать
 * @return array отсортированный многомерный массив
 */
function customMultiSort($array,$separator) {
    $sortArr = array();
    foreach($array as $key=>$val){
        $sortArr[$key] = $val[$separator];
    }

    array_multisort($sortArr,$array);

    return $array;
}