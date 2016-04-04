<?php
class custom extends def_module {
    public function cms_callMethod($method_name, $args) {
        return call_user_func_array(Array($this, $method_name), $args);
    }

    public function __call($method, $args) {
        throw new publicException("Method " . get_class($this) . "::" . $method . " doesn't exists");
    }
    //TODO: Write your own macroses here

    public function xlsImport($form=0){
        $permission = permissionsCollection::getInstance();
//            var_export($permission->isSv());
        header("Content-Type: text/html; charset=utf-8");
        if($permission->isSv()) {
            $_SESSION['counter'] = 0;
            $_SESSION['countSheetArr'] = 0;
            $text = '<!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="utf-8" />
                            <title>Импорт данных из Excele</title>
                            <link href="/templates/mediamid/css/import_style.css" rel="stylesheet" type="text/css" />
                            <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
                        </head>
                        <body>';

            // Проверяем загружен ли файл
            if (!empty($_FILES['file'])) {
                if (is_uploaded_file($_FILES["file"]["tmp_name"])) {
                    // Если файл загружен успешно, перемещаем его
                    // из временной директории в конечную
                    if (file_exists($_SERVER['DOCUMENT_ROOT']."/files/".$_FILES["file"]["name"])) {
                        $file = $_FILES["file"]["name"];
                    } else {
                        $file = date('Ymd_Hi', time()).'_importCatalog.xls';
                        move_uploaded_file($_FILES["file"]["tmp_name"], $_SERVER['DOCUMENT_ROOT']."/files/".date('Ymd_Hi', time()).'_importCatalog.xls');
                    }
                    $text .=    '<div class="categories">
                                        <input id="file" value="'.$file.'" type="hidden" >
                                        <p class="atention">Файл сохранен, идет обработка.</p>
                                    </div>
                                    <script src="/templates/mediamid/js/import_xls.js" type="text/javascript"></script>
                                    <div class="submit"><input class="submit" type="submit" form="import" value="Начать импорт"></div>
                                    <div class="answer"></div>
                                    </body>
                                    </html>';
                    self::catalogParser();
                    $catalog = $_SESSION['catalog'];
                    $options = '<option disabled value="0" selected="selected">Выберите раздел в который необходимо выгрузить товары</option>';
                    foreach ($catalog['catalogs'] as $val) {
                        $options .= '<option value="'.$val['page_id'].'">'.$val['name'].'</option>';
                    }

                    $_SESSION['options'] = $options;

                    return $text;
                } else {
                    $text .= 'файл не загрузился из за: <br>';
                    switch ($_FILES['file']['error']) {
                        case 1:
                            $text .= 'Размер принятого файла превысил максимально допустимый размер, который задан директивой upload_max_filesize конфигурационного файла php.ini';
                            break;
                        case 2:
                            $text .= 'Размер загружаемого файла превысил значение MAX_FILE_SIZE, указанное в HTML-форме';
                            break;
                        case 3:
                            $text .= 'Загружаемый файл был получен только частично';
                            break;
                        case 4:
                            $text .= 'Файл не был загружен';
                            break;
                        case 5:
                            $text .= 'В документации такой ошибки кода не описано, поэтому ушел видимо на темную сторону силы он';
                            break;
                        case 6:
                            $text .= 'Отсутствует временная папка';
                            break;
                        case 7:
                            $text .= 'Не удалось записать файл на диск';
                            break;
                        case 8:
                            $text .= 'PHP-расширение остановило загрузку файла. PHP не предоставляет способа определить какое расширение остановило загрузку файла; в этом может помочь просмотр списка загруженных расширений из phpinfo()';
                            break;
                    }
                }
            }

            if (!empty($_POST['file'])) {
                $file = $_POST['file'];
                $text =    '<form method="POST" id="import">
                                    <input type="hidden" name="filename" value="'.$file.'">';

                $options = $_SESSION['options'];
                $sheetArr = self::phpexcelereader($file,'dir');

                $select = '';
                foreach ($sheetArr as $k => $v) {
                    $select .= '<p';
                    if($v[1]['type']==1){
                        $select .= ' class="first"';
                    }elseif($v[1]['type']==2){
                        $select .= ' class="second"';
                    }elseif($v[1]['type']==3){
                        $select .= ' class="third"';
                    }
                    $select .= '>'.$v[1]['value'];
                    $select .= '<select form="import" name="'.$k.'" >'.$options.'</select>'.'</p>
                                    <img src="/templates/mediamid/img/delete1.png" alt="Отменить"/>';
                }
                $text .= $select.'</form>';

                return $text;
            } elseif(!empty($form) && $form==1) {
                $formHtml = '<div class="import">
                                <a href="/xlsImport">Из файла</a>
                                <br />
                                <br />
                                <a href="/siteParser">С сайта</a>
                            </div>';
                return $formHtml;
            } else {
                $text .= '<div class="choise">
                                <p>Пожалуйста выберите xls файл который необходимо импортировать</p>
                                <form action="" method="POST" id="first_import" enctype="multipart/form-data">
                                    <input type="file" name="file" alt="Выберите файл" />
                                    <input type="submit" value="Подготовить импорт" />
                                </form>
                            </div>';
            }
            $text .= '</body></html>';

            return $text;
        }
    }

    /**
     * Макрос импорта товаров каталога из файла
     */
    public function importCatalog () {
        header("Content-Type: text/html; charset=utf-8");
        $hierarchy = umiHierarchy::getInstance();
        $permissions = permissionsCollection::getInstance();
        $catalog = $_SESSION['catalog'];
        $post = $_POST;
        $file = $_POST['filename'];

        $sheetArr = self::phpexcelereader($file,'item',$post);
        $text=array();

        foreach($sheetArr as $k=>$v) {
            if (!empty($v[2]['value'])) {
                $import_id = false;
                $name = false;
                $price = false;
                $page_id = 0;

                foreach ($catalog['items'] as $item) {
                    if ($item['import_id']==$v[1]['value']) {
                        $import_id = true;
                    }
                    if ($item['name']==$v[2]['value']) {
                        $name = true;
                    }
                    if ($item['price']==$v[4]['value']) {
                        $price = true;
                    }
                    if ($import_id || $name) {
                        $page_id = $item['page_id'];
                        break;
                    }
                }

                if ($import_id) {
                    if ($name) {
                        $pageElement = $hierarchy->getElement($page_id);
                        $object = $pageElement->getObject();

                        if (!$price) {
                            if($catalog['catalogs'][$v['catalog']]['item_type_id']){
                                $type_id = $catalog['catalogs'][$v['catalog']]['item_type_id'];
                                $object->setTypeId($type_id);
                            } else {
                                $type_id = 86;
                                $object->setTypeId($type_id);
                            }
                            $pageElement->setValue('price', $v[4]['value']);
                            $pageElement->setValue('guarantee_maintenance', !empty($v[5]['value'])?$v[5]['value']:'');
                            $pageElement->setValue('import_margin', !empty($v[6]['value'])?$v[6]['value']:0);
                            $pageElement->setUpdateTime();
                            $pageElement->setIsUpdated();
                            $pageElement->commit();
                            $permissions->setDefaultElementPermissions($pageElement,337);
                            $text[] = self::parseTemplate('', array(
                                'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: изменена цена<br /></p>'
                            ));
                        } else {
                            if($catalog['catalogs'][$v['catalog']]['item_type_id']){
                                $type_id = $catalog['catalogs'][$v['catalog']]['item_type_id'];
                                $object->setTypeId($type_id);
                            } else {
                                $type_id = 86;
                                $object->setTypeId($type_id);
                            }
                            $permissions->setDefaultElementPermissions($pageElement,337);
                            $text[] = self::parseTemplate('', array(
                                'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: не изменялся<br /></p>'
                            ));
                        }
                    } else {
                        $pageElement = $hierarchy->getElement($page_id);

                        if (!$price) {
                            if($catalog['catalogs'][$v['catalog']]['item_type_id']){
                                $type_id = $catalog['catalogs'][$v['catalog']]['item_type_id'];
                                $object->setTypeId($type_id);
                            } else {
                                $type_id = 86;
                                $object->setTypeId($type_id);
                            }
                            $pageElement->setValue('price', $v[4]['value']);
                            $pageElement->setName($v[2]['value']);
                            $pageElement->setValue('guarantee_maintenance', !empty($v[5]['value'])?$v[5]['value']:'');
                            $pageElement->setValue('import_margin', !empty($v[6]['value'])?$v[6]['value']:0);
                            $pageElement->setUpdateTime();
                            $pageElement->setIsUpdated();
                            $pageElement->commit();
                            $permissions->setDefaultElementPermissions($pageElement,337);
                            $text[] = self::parseTemplate('', array(
                                'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: изменена цена и название<br /></p>'
                            ));
                        } else {
                            if($catalog['catalogs'][$v['catalog']]['item_type_id']){
                                $type_id = $catalog['catalogs'][$v['catalog']]['item_type_id'];
                                $object->setTypeId($type_id);
                            } else {
                                $type_id = 86;
                                $object->setTypeId($type_id);
                            }
                            $pageElement->setName($v[2]['value']);
                            $pageElement->setValue('guarantee_maintenance', !empty($v[5]['value'])?$v[5]['value']:'');
                            $pageElement->setValue('import_margin', !empty($v[6]['value'])?$v[6]['value']:0);
                            $pageElement->setUpdateTime();
                            $pageElement->setIsUpdated();
                            $pageElement->commit();
                            $permissions->setDefaultElementPermissions($pageElement,337);
                            $text[] = self::parseTemplate('', array(
                                'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: изменено название<br /></p>'
                            ));
                        }
                    }
                } else {
                    if ($name) {
                        $pageElement = $hierarchy->getElement($page_id);

                        if (!$price) {
                            if($catalog['catalogs'][$v['catalog']]['item_type_id']){
                                $type_id = $catalog['catalogs'][$v['catalog']]['item_type_id'];
                                $object->setTypeId($type_id);
                            } else {
                                $type_id = 86;
                                $object->setTypeId($type_id);
                            }
                            $pageElement->setValue('import_id', $v[1]['value']);
                            $pageElement->setValue('price', $v[4]['value']);
                            $pageElement->setValue('guarantee_maintenance', !empty($v[5]['value'])?$v[5]['value']:'');
                            $pageElement->setValue('import_margin', !empty($v[6]['value'])?$v[6]['value']:0);
                            $pageElement->setUpdateTime();
                            $pageElement->setIsUpdated();
                            $pageElement->commit();
                            $permissions->setDefaultElementPermissions($pageElement,337);
                            $text[] = self::parseTemplate('', array(
                                'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: изменена цена и идентификатор импорта<br /></p>'
                            ));
                        } else {
                            if($catalog['catalogs'][$v['catalog']]['item_type_id']){
                                $type_id = $catalog['catalogs'][$v['catalog']]['item_type_id'];
                                $object->setTypeId($type_id);
                            } else {
                                $type_id = 86;
                                $object->setTypeId($type_id);
                            }
                            $pageElement->setValue('import_id', $v[1]['value']);
                            $pageElement->setValue('guarantee_maintenance', !empty($v[5]['value'])?$v[5]['value']:'');
                            $pageElement->setValue('import_margin', !empty($v[6]['value'])?$v[6]['value']:0);
                            $pageElement->setUpdateTime();
                            $pageElement->setIsUpdated();
                            $pageElement->commit();
                            $permissions->setDefaultElementPermissions($pageElement,337);
                            $text[] = self::parseTemplate('', array(
                                'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: изменен идентификатор импорта<br /></p>'
                            ));
                        }
                    } else {
                        if($catalog['catalogs'][$v['catalog']]['item_type_id']){
                            $type_id = $catalog['catalogs'][$v['catalog']]['item_type_id'];
                        } else {
                            $type_id = 86;
                        }

                        $idCreatePage = $hierarchy->addElement($v['catalog'], 56, $v[2]['value'], $hierarchy->convertAltName($v[2]['value']), $type_id, 1, 1, 1);

                        if ($idCreatePage) {
                            $newPage = $hierarchy->getElement($idCreatePage);
                            $newPage->setValue('price', $v[4]['value']);
                            $newPage->setValue('import_id', $v[1]['value']);
                            $newPage->setValue('guarantee_maintenance', !empty($v[5]['value'])?$v[5]['value']:'');
                            $newPage->setValue('import_margin', !empty($v[6]['value'])?$v[6]['value']:0);
                            $newPage->setIsActive();
                            $newPage->setUpdateTime();
                            $newPage->setIsUpdated();
                            $newPage->commit();
                            $permissions->setDefaultElementPermissions($newPage,337);
                            $text[] = self::parseTemplate('', array(
                                'node:text' => '<p><br /> артикул:'.$newPage->getValue('import_id').'<br /> название:'.$newPage->getName().'<br /> состояние: добавлен<br /></p>'
                            ));
                        }
                    }
                }
            }
        }
        return self::parseTemplate('', array("subnodes:items" => $text));
    }

    /**
     * Разбираем Excele таблицу по заданному пути файла
     * @param string $file название xls файла с таблицей
     * @param string $type тип данных для поиска может
     *                     иметь два значения:
     *                     dir - парсим разделы
     *                     item - парсим товары
     * @param array $post передаем $_POST данные из формы
     *                    для парсинга товаров
     * @return array|bool возвращаем массив разделов, товаров
     *                    или false в случае отсутствия названия файла
     * @throws PHP Excele Reader https://code.google.com/p/php-excel-reader/
     */
    private function phpexcelereader ($file='', $type='', $post=array()) {
        $sheetArr = array();

        if($file){
            require_once('./phpexcelereader/excel_reader2.php');
            $fileEx = explode('.', $file);
            $xls = false;
            foreach ($fileEx as $f) {
                if ($f == 'xls') {
                    $xls = true;
                }
            }

            if ($xls) {
                if ($type=='dir') {
                    $data = new Spreadsheet_Excel_Reader('./files/'.$file, false);

                    $highestRowIndex=$data->rowcount();
                    for($i=1;$i<$highestRowIndex;++$i){
                        $val = $data->val($i,1);
                        if(empty($val)){
                            $highestRowIndex=$i;
                        }
                    }

                    for($row=1; $row<$highestRowIndex; ++$row){
                        $val_first = $data->val($row,1);
                        if(((int)$val_first)==0){
                            $sheetArr[$row][1]['type'] = $data->val($row, 8);
                            $sheetArr[$row][1]['value'] = $data->val($row, 1);
                        }
                    }
                } else if ($type=='item' && !empty($post)) {
                    $data = new Spreadsheet_Excel_Reader('./files/'.$file);
                    $highestRowIndex=$data->rowcount();
                    $highestColumnIndex = $data->colcount();
                    for($i=1;$i<$highestRowIndex;++$i){
                        $val = $data->val($i,1);
                        if(empty($val)){
                            $highestRowIndex=$i;
                        }
                    }

                    foreach($post as $k=>$v){
                        if($k!='filename') {
                            $firstType = $data->val($k, 8);
                            $margin = $data->val($k, 6);

                            for ($row = $k+1; $row<$highestRowIndex; ++$row) {
                                $val_first = $data->val($row, 1);
                                $type = $data->val($row, 8);

                                if((int)$val_first==0 && $type>$firstType){
                                    $secondMargin = $data->val($row, 6);
                                }

                                for ($col = 1; $col<=$highestColumnIndex; ++$col) {
                                    if (empty($val_first) || ((int)$val_first==0 && $type==$firstType)) {
                                        $row = $highestRowIndex;
                                    } else if ((int)$val_first!=0) {
                                        $sheetArr[$row]['catalog'] = $v;
                                        $val = $data->val($row, $col);
                                        $next = $data->val($row, 7);
                                        if (!empty($val) && empty($next)) {
                                            if ($col==4) {
                                                $percent = $data->val($row, 6);
                                                if (!empty($percent)){
                                                    $plus = round($val*$percent/100);
                                                    $sheetArr[$row][$col]['value'] = intval((int)$val+(int)$plus);
                                                } else {
                                                    if(!empty($secondMargin)){
                                                        $plus = round($val*$secondMargin/100);
                                                        $sheetArr[$row][$col]['value'] = intval((int)$val+(int)$plus);
                                                    } else {
                                                        if(!empty($margin)){
                                                            $plus = round($val*$margin/100);
                                                            $sheetArr[$row][$col]['value'] = intval((int)$val+(int)$plus);
                                                        } else {
                                                            $sheetArr[$row][$col]['value'] = intval((int)$val);
                                                        }
                                                    }
                                                }

                                                /*if (!empty($margin) && empty($secondMargin) && empty($percent)) {
                                                    $plus = round($val*$margin/100);
                                                    $sheetArr[$row][$col]['value'] = intval((int)$val+(int)$plus);
                                                } else if (!empty($margin) && !empty($secondMargin) && empty($percent)) {

                                                } else if (!empty($margin) && !empty($secondMargin) && !empty($percent)) {
                                                    $plus = round($val*$percent/100);
                                                    $sheetArr[$row][$col]['value'] = intval((int)$val+(int)$plus);
                                                } else if (empty($margin) && empty($percent)) {
                                                    $sheetArr[$row][$col]['value'] = intval((int)$val);
                                                }*/
                                            } else {
                                                $sheetArr[$row][$col]['value'] = $val;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return $sheetArr;
        }
        return false;
    }

    /**
     * Собираем информацию о каталоге и товарах
     */
    private function catalogParser () {
        $catalog = array();
        $hierarchy = umiHierarchy::getInstance();

        $pages = '';
        $pages = new selector('pages');
        $pages->types('object-type')->id(85);

        foreach($pages->result() as $v){
            $catalog['catalogs'][$v->id]['name'] = $v->getName();
            $catalog['catalogs'][$v->id]['page_id'] = $v->id;
            $catalog['catalogs'][$v->id]['item_type_id'] = $v->getValue('item_type_id');
            $catalog['catalogs'][$v->id]['parent'] = $hierarchy->getParent($v->id);
        }
//            ksort($catalog['catalogs']);
        if(!empty($catalog['catalogs'])){
            asort($catalog['catalogs']);
        }

        $pages = '';
        $pages = new selector('pages');
        $pages->types('object-type')->name('catalog','object');
        $pages->where('import_id')->isnotnull();

        foreach($pages->result() as $v){
            $catalog['items'][$v->id]['name'] = $v->getName();
            $catalog['items'][$v->id]['page_id'] = $v->id;
            $catalog['items'][$v->id]['import_id'] = $v->getValue('import_id');
            $catalog['items'][$v->id]['price'] = $v->getValue('price');
            $catalog['items'][$v->id]['parent'] = $hierarchy->getParent($v->id);
        }
        if(!empty($catalog['items'])){
            ksort($catalog['items']);
        }

        $_SESSION['catalog'] = $catalog;
    }


    private function get_result($url){
        $curl = curl_init();
        $headers = array(
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            'Connection: keep-alive'
        );

//            $cookie = dirname(__DIR__)."/cookie.txt";
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl,CURLOPT_FOLLOWLOCATION,1);
        curl_setopt($curl,CURLOPT_URL,$url);
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
//            curl_setopt($curl,CURLOPT_COOKIEFILE,$cookie);
//            curl_setopt($curl,CURLOPT_COOKIEJAR,$cookie);
        $res = curl_exec($curl);

        return $res;
    }

    /**
     * Парсер дополнительных данных о товаре с сайта поставщика
     */
    public function siteParser (){
        self::catalogParser();
        $catalogs = $_SESSION['catalog'];

        $text = '<!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset="utf-8" />
                            <title>Синхронизация данных с сайтом</title>
                            <link href="/templates/mediamid/css/synchronize.css" rel="stylesheet" type="text/css" />
                            <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
                        </head>
                        <body>
                            <div class="items_list">
                            <form method="POST" action="/udata/custom/requestSiteParser/" id="synchronize">
                                <ul>';
        $cat = self::filter($catalogs['catalogs'],55);
        $notEmpty = false; // для проверки каталога на наличие товара
        if(!empty($cat)){
            foreach($cat as $c){
                $cat1 = self::filter($catalogs['catalogs'],$c['page_id']);
                $items = self::filter($catalogs['items'],$c['page_id']);

                $text .= '<li class="catalog1 hide">
                             <input type="checkbox">'.$c['name'];

                if(!empty($cat1)){
                    $text .= '<ul class="catalog_check">';

                    foreach($cat1 as $c1){
                        $cat2 = self::filter($catalogs['catalogs'],$c1['page_id']);
                        $items1 = self::filter($catalogs['items'],$c1['page_id']);

                        $text .= '<li class="catalog2 hide">
                                    <input type="checkbox">'.$c1['name'];

                        if(!empty($cat2)){
                            $text .= '<ul class="catalog_check">';

                            foreach($cat2 as $c2) {
                                $items2 = self::filter($catalogs['items'],$c2['page_id']);

                                if (!empty($items2)){
                                    $text .= '<li class="catalog3">
                                                <input type="checkbox">'.$c2['name'].'
                                                <a class="lever down" >&#709;</a>
                                                <ul class="item_check hide">';
                                    foreach($items2 as $i2){
                                        $text .= '  <li class="item">
                                                        <input type="checkbox" name="'.$i2['import_id'].'" value="'.$i2['page_id'].'">
                                                        '.$i2['name'].'
                                                    </li>';
                                    }

                                    $text .= '</ul>
                                            </li>';
                                    $notEmpty = true;
                                } else {
                                    if(!$notEmpty){
                                        $notEmpty = false;
                                    }
                                }
                            }

                            $text .= '</ul>';
                        } else {
                            $notEmpty = false;
                        }
                        if (!empty($items1)){
                            $text .= '<a class="lever down" >&#709;</a><ul class="item_check hide">';
                            foreach($items1 as $i1){
                                $text .= '<li class="item">
                                            <input type="checkbox" name="'.$i1['import_id'].'" value="'.$i1['page_id'].'">
                                            '.$i1['name'].'
                                        </li>';
                            }
                            $text .= '</ul>';
                        }
                        if(!$notEmpty && empty($items1)){
                            $text .= '<input type="hidden" value="empty">';
                        }
                        $text .= '</li>';
                    }

                    $text .= '</ul>';
                } else {
                    $notEmpty = false;
                }
                if (!empty($items)){
                    $text .= '<a class="lever down" >&#709;</a>
                            <ul class="item_check hide">';

                    foreach($items as $i){
                        $text .= '<li class="item">
                                    <input type="checkbox" name="'.$i['import_id'].'" value="'.$i['page_id'].'">
                                    '.$i['name'].'
                                </li>';
                    }

                    $text .= '</ul>';
                }

                if(!$notEmpty && empty($items)){
                    $text .= '<input type="hidden" value="empty">';
                }

                $text .= '</li>';
            }
        }
        $text .= '</ul>
                </form>
                </div>
                <div class="answer"></div>
                <div class="submit">
                    <input class="submit" type="submit" form="synchronize" value="Start">
                </div>
                <script type="text/javascript" src="/templates/mediamid/js/synchronize.js"></script>
                </body></html>';
        return $text;
    }

    private function filter($arg=array(),$parent=0){
        $result=array();
        if(!empty($arg) && !empty($parent)){
            foreach($arg as $v){
                if(!empty($v['parent']) && $v['parent']==$parent){
                    $result[]=$v;
                }
            }
            return $result;
        } else {
            return false;
        }
    }

    public function requestSiteParser(){
        require_once('./simplehtmldom/simple_html_dom.php');
        $hierarchy = umiHierarchy::getInstance();
        $text = array();
        $value = false;
        $key = false;

        if($_SESSION['sitepars']==1 && !empty($_SESSION['sitepost'])){
            list($key, $value) = each($_SESSION['sitepost']);

            reset($_SESSION['sitepost']);
            $_SESSION['sitepost'] = array_reverse($_SESSION['sitepost'],true);
            array_pop($_SESSION['sitepost']);

            /*$text[] = self::parseTemplate('', array(
                            'node:text' => $key.'=>'.$value.'_next'
                        ));*/
        } else if($_SESSION['sitepars']!=1 && empty($_SESSION['sitepost']) && !empty($_POST)){
            $_SESSION['sitepost'] = $_POST;
            $_SESSION['sitepars'] = 1;

            list($key, $value) = each($_SESSION['sitepost']);

            reset($_SESSION['sitepost']);
            $_SESSION['sitepost'] = array_reverse($_SESSION['sitepost'],true);
            array_pop($_SESSION['sitepost']);

            /*$text[] = self::parseTemplate('', array(
                            'node:text' => $key.'=>'.$value.'_first'
                        ));*/
        } else {
            unset($_SESSION['sitepars']);
            unset($_SESSION['sitepost']);
        }

//        return self::parseTemplate('', array("subnodes:items" => $text));
//        die;

        if($value){
            $pageElement = $hierarchy->getElement($value);
            $simpleHtml = str_get_html(self::get_result('http://www.regard.ru/catalog/tovar'.$key.'.htm'));
            /*$characteristics = $simpleHtml->find('div#tabs>div#tabs-1 table',0);
            $icharacteristics = iconv('windows-1251','utf-8',$characteristics->outertext);
            $pageElement->setValue('characteristics', $icharacteristics);*/
            $characteristics = $simpleHtml->find('div#tabs>div#tabs-1 table>tr');
            $proc= 0;
            foreach($characteristics as $tr){
                $td = iconv('windows-1251','utf-8',$tr->find('td',0)->plaintext);
                $td_replace = str_replace('&nbsp;','',$td);
                $td_trim = trim($td_replace);
                $td_convert = $hierarchy->convertAltName($td_trim);
                $td1 = iconv('windows-1251','utf-8',$tr->find('td',-1)->plaintext);
                $td1_replace = str_replace('&nbsp;','',$td1);
                $td1_trim = trim($td1_replace);

                if($tr->class=='head' && substr_count($td_trim,'Процессор')>=1){
                    $proc = 1;
                }
                if($proc && $td_trim=='Производитель'){
                    $proc1 = $hierarchy->convertAltName('Процессор');
                    $pageElement->setValue($proc1,$td1_trim);
                } else {
                    $pageElement->setValue($td_convert,$td1_trim);
                }
                /*$text[] = self::parseTemplate('', array(
                    'node:text' => $text1
                ));*/
            }

            $iavailabyliti = iconv('windows-1251','utf-8',$simpleHtml->find('div.action_block>div>span.green',0)->innertext);
            if($iavailabyliti=='есть'){
                $pageElement->setValue('availability', '1');
            } else {
                $pageElement->setValue('availability', '0');
            }

            if(!file_exists('./images/cms/data/'.$key)){
                if(mkdir('./images/cms/data/'.$key, 0755)){
                    $images = $simpleHtml->find('div.block_img a.img_full_size');
                    $i = 1;

                    foreach($images as $val){
                        if($i==1){
                            $href = basename($val->href);
                            if(!file_exists('./images/cms/data/'.$key.'/'.$href)){
                                copy('http://www.regard.ru'.$val->href,'./images/cms/data/'.$key.'/'.$href);
                            }
                            $pageElement->setValue('photo','./images/cms/data/'.$key.'/'.$href);
                            $pageElement->setValue('photo_0','./images/cms/data/'.$key.'/'.$href);
                        } else {
                            $photo = $i-1;
                            $href = basename($val->href);
                            if(!file_exists('./images/cms/data/'.$key.'/'.$href)){
                                copy('http://www.regard.ru'.$val->href,'./images/cms/data/'.$key.'/'.$href);
                            }
                            $pageElement->setValue('photo_'.(int)$photo,'./images/cms/data/'.$key.'/'.$href);
                        }
                        $i++;
                    }
                    $pageElement->setUpdateTime();
                    $pageElement->setIsUpdated();
                    $pageElement->commit();
                    $text[] = self::parseTemplate('', array(
                        'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: синхронизирован<br /></p>'
                    ));
                } else {
                    $text[] = self::parseTemplate('', array(
                        'node:text' => '<p>Не получилось создать папку</p>'
                    ));
                    return self::parseTemplate('', array("subnodes:items" => $text));
                }
            } else {
                $images = $simpleHtml->find('div.block_img a.img_full_size');
                $i = 1;

                foreach($images as $val){
                    if($i==1){
                        $href = basename($val->href);
                        if(!file_exists('./images/cms/data/'.$key.'/'.$href)){
                            copy('http://www.regard.ru'.$val->href,'./images/cms/data/'.$key.'/'.$href);
                        }
                        $pageElement->setValue('photo','./images/cms/data/'.$key.'/'.$href);
                        $pageElement->setValue('photo_0','./images/cms/data/'.$key.'/'.$href);
                    } else {
                        $photo = $i-1;
                        $href = basename($val->href);
                        if(!file_exists('./images/cms/data/'.$key.'/'.$href)){
                            copy('http://www.regard.ru'.$val->href,'./images/cms/data/'.$key.'/'.$href);
                        }
                        $pageElement->setValue('photo_'.(int)$photo,'./images/cms/data/'.$key.'/'.$href);
                    }
                    $i++;
                }
                $pageElement->setUpdateTime();
                $pageElement->setIsUpdated();
                $pageElement->commit();
                $text[] = self::parseTemplate('', array(
                    'node:text' => '<p><br /> артикул:'.$pageElement->getValue('import_id').'<br /> название:'.$pageElement->getName().'<br /> состояние: синхронизирован<br /></p>'
                ));
            }
        }
        return self::parseTemplate('', array("subnodes:items" => $text));
    }

};
