<?php
    require 'phpQuery/phpQuery.php';
    require 'config.php';
    $mysqli = new mysqli($host,$user,$pass,$db);
    if (!$mysqli->set_charset("utf8")) {
        printf("Ошибка при загрузке набора символов utf8: %s\n", $mysqli->error);
    }

    if ($mysqli->connect_errno)
    {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
    } else {
        $update_prod = array();
        if ($query_result = $mysqli->query("SELECT product_id as id, model as m FROM product WHERE model NOT LIKE 'Product%' AND image IS NULL LIMIT 5", MYSQLI_USE_RESULT)) {
            while($row = mysqli_fetch_array($query_result))
            {

                sleep(1);
                $url = "http://market.yandex.ua/search.xml?text=".urlencode($row['m']);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL,$url); // set url to post to
                curl_setopt($ch, CURLOPT_FAILONERROR, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// allow redirects
                curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable
                curl_setopt($ch, CURLOPT_TIMEOUT, 3); // times out after 4s
                curl_setopt($ch, CURLOPT_POST, 0); // set POST method
                //curl_setopt($ch, CURLOPT_POSTFIELDS, "url=index%3Dbooks&field-keywords=PHP+MYSQL"); // add POST fields
                $result = curl_exec($ch); // run the whole process
                $document = phpQuery::newDocument($result);
                $offer_id = $document->find('div.b-offers:first')->attr('id');
                if($offer_id){
                    sleep(1);
                    $url = "http://market.yandex.ua/model.xml?srnum=40&modelid=".$offer_id;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL,$url); // set url to post to
                    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// allow redirects
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // times out after 4s
                    curl_setopt($ch, CURLOPT_POST, 0); // set POST method
                    //curl_setopt($ch, CURLOPT_POSTFIELDS, "url=index%3Dbooks&field-keywords=PHP+MYSQL"); // add POST fields
                    $result = curl_exec($ch); // run the whole process

                    $document = phpQuery::newDocument($result);
                    $image_wrapper = $document->find('span.b-model-pictures__big > a');
                    if(count($image_wrapper) > 0){
                        $image_el = $image_wrapper[0];
                        $pq = pq($image_el);

                        $image_link = $pq->attr('href');
                        curl_setopt($ch, CURLOPT_URL,$image_link); // set url to post to
                        $result = curl_exec($ch); // run the whole process

                        $fp = fopen($image_path.'/'.$row['m'].'.'.pathinfo($image_link, PATHINFO_EXTENSION), 'wb');
                        fwrite($fp, $result);
                        fclose($fp);
                        $update_prod[$row['id']]['path'] = 'data/'.$row['m'].'.'.pathinfo($image_link, PATHINFO_EXTENSION);
                        $update_prod[$row['id']]['name'] = $row['m'];
                    } else {
                        echo "No image span<br />";
                    }


                    curl_close($ch);

                } else {
                    echo $row['m']." не числится на Yandex.Market <br />";
                }

            }
            $query_result->close();
        }

        foreach($update_prod as $id=>$prod){
            $query = "UPDATE product SET image = '".$prod['path']."' WHERE product_id = ".$id;
            $qr = $mysqli->query($query);
            if($mysqli->affected_rows > 0){
                echo $prod['name']." изображение обновлено <br />";
            } else {
                echo $prod['name']." изображение небыло обновлено <br />";
            }
        }


    }

