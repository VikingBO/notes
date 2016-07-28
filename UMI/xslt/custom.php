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

    // проверяем страницу на каноничность    
    public function makeRelCanonical($page_id){
			if(!$page_id) return;
			$current_page_id = cmsController::getInstance()->getCurrentElementId();
			$hierarchy_col = umiHierarchy::getInstance();
			$domain_col = domainsCollection::getInstance();
			$page = $hierarchy_col->getElement($page_id, true, true);

			if(!$page) return;
			
			if($page_id == false){
				if($current_page_id == false && defined('VIA_HTTP_SCHEME')){
					throw new publicException('cant get current element via HTTP SCHEME MODE');
				}
				$page_id = $current_page_id;
				
				$object_id = $page->getObjectId();
				$parents_ids = $hierarchy_col->getObjectInstances($object_id, true, true);
				if(count($parents_ids) == 0 || count($parents_ids) == 1 || $parents_ids[0] == $page_id){
					return '';
				}
				$first_parent_id = $parents_ids[0];
				$path = $hierarchy_col->getPathById($first_parent_id);
				$domain_id = $hierarchy_col->getElement($first_parent_id, true, true)->getDomainId();
				$domain_name = $domain_col->getDomain($domain_id)->getHost();

				return '<link rel="canonical" href="' . 'http://' . $domain_name . $path . '"/>';
			}else{
				$page_id = intval($page_id);
				if($page_id == 0){
					throw new publicException('wrong id given');
				}
				if($page == false){
					throw new publicException('page with id = ' . $page_id . ' not found');
				}
				$object_id = $page->getObjectId();
				$parents_ids = $hierarchy_col->getObjectInstances($object_id, true, true);
				if(count($parents_ids) == 0 || count($parents_ids) == 1 || $parents_ids[0] == $page_id){
					return '';
				}
				$first_parent_id = $parents_ids[0];
				$path = $hierarchy_col->getPathById($first_parent_id);
				$domain_id = $hierarchy_col->getElement($first_parent_id, true, true)->getDomainId();
				$domain_name = $domain_col->getDomain($domain_id)->getHost();

				return '<link rel="canonical" href="' . 'http://' . $domain_name . $path . '"/>';
			}
		}

    // проверяем страницу на каноничность 
	public function makeRelCanonicalId($page_id = false){
		$hierarchy_col = umiHierarchy::getInstance();

		if($page_id){
			$page_id = intval($page_id);
			if($page_id == 0){
				throw new publicException('wrong id given');
			}
			$page = $hierarchy_col->getElement($page_id, true, true);
			if($page == false){
				throw new publicException('page with id = ' . $page_id . ' not found');
			}
			$object_id = $page->getObjectId();
			$parents_ids = $hierarchy_col->getObjectInstances($object_id, true, true);
			if(count($parents_ids) == 0 || count($parents_ids) == 1 || $parents_ids[0] == $page_id){
				return '';
			}
			return $parents_ids[0];
		}
	}
}
