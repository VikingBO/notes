<?php
/**
 * Created by PhpStorm.
 * User: a.pilipenko
 * Date: 18.12.14
 * Time: 12:07
 */

class custom extends def_module {

    /**
     *  This function creates and returns an array
     *  of the calendar in the form of xml tree
     */
    public function calendar() {
        $y=date("Y");
        $m=date("m");

        $month_stamp=mktime(0,0,0,$m,1,$y);
        $day_count=date("t",$month_stamp);
        $first=date("N",$month_stamp);
        $last=date("N",mktime(0,0,0,$m,$day_count,$y));

        $dayArr = array();
        $weeksArr = array();
        $week = 1;
        $day = 1;
        for($i=1;$i>0;){
            if ($day<=$day_count) {
                if($first>$i){
                    $dayArr['attribute:day'] = 0;
                    --$day;
                } else {
                    if(date("N",mktime(0,0,0,$m,$day,$y))==6 || date("N",mktime(0,0,0,$m,$day,$y))==7){
                        $dayArr['attribute:weekend'] = 1;
                    } else {
                        $dayArr['attribute:weekend'] = 0;
                    }
                    $dayArr['attribute:day'] = $day;
                    $dayArr['value'] = $day;
                }
                $daysArr[] = $this->parseTemplate('',$dayArr);
                ++$i;
                if(date("N",mktime(0,0,0,$m,$day,$y))>=7 || $day>=$day_count){
                    $weekArr = array('subnodes:days'=> $daysArr);
                    $weekArr['attribute:week'] = $week;
                    $weekArr['value'] = $week;
                    $weeksArr[] = $this->parseTemplate('',$weekArr);
                    ++$week;
                    $daysArr = array();
                }
                ++$day;
            } elseif ($day>$day_count) {
                $i=0;
            }
        }
        $monthArr = array('subnodes:weeks'=>$weeksArr);
        $monthArr['day_count'] = $day_count;
        $monthArr['start'] = $first;
        $monthArr['last'] = $last;
        return $this->parseTemplate('', $monthArr);
    }
}