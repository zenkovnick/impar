<?php
    require 'phpQuery/phpQuery.php';

    // Поиск книг на сервере amazon.com
    $url = "http://market.yandex.ua/model.xml?text=LG%2042LA660V&srnum=40&modelid=9374571&hid=90639";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url); // set url to post to
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// allow redirects
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // times out after 4s
    curl_setopt($ch, CURLOPT_POST, 0); // set POST method
    //curl_setopt($ch, CURLOPT_POSTFIELDS, "url=index%3Dbooks&field-keywords=PHP+MYSQL"); // add POST fields
    $result = curl_exec($ch); // run the whole process
    curl_close($ch);

    $document = phpQuery::newDocument($result);

    $image_wrapper = $document->find('span.b-model-pictures__big > a');
    if(count($image_wrapper) > 0){
        $image_el = $image_wrapper[0];
        $pq = pq($image_el);

        $image_link = $pq->attr('href');
        $image = file_get_contents($image_link);

        $fp = fopen('temp/LG_42LA660V.'.pathinfo($image_link, PATHINFO_EXTENSION), 'w');
        fwrite($fp, file_get_contents($url));
        fclose($fp);
    } else {
        echo "No image span";
    }
