<?php
    require 'phpQuery/phpQuery.php';
    require 'config.php';

    session_start();

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
                $result = curl_exec($ch); // run the whole process

                $document = phpQuery::newDocument($result);
                $offer_id = $document->find('div.b-offers:first')->attr('id');
                if($offer_id){
                    sleep(1);
                    $url = "http://market.yandex.ua/model.xml?srnum=40&modelid=".$offer_id;
                    curl_setopt($ch, CURLOPT_URL,$url); // set url to post to
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

                        $update_prod = $_SESSION['up'] ? $_SESSION['up'] : array();
                        $update_prod[$row['id']]['path'] = 'data/'.$row['m'].'.'.pathinfo($image_link, PATHINFO_EXTENSION);
                        $update_prod[$row['id']]['name'] = $row['m'];
                        $_SESSION['up'] = $update_prod;

                    } else {
                        $image_wrapper = $document->find('span.b-model-pictures__big > img');
                        if(count($image_wrapper) > 0){
                            $image_el = $image_wrapper[0];
                            $pq = pq($image_el);

                            $image_link = $pq->attr('src');
                            curl_setopt($ch, CURLOPT_URL,$image_link); // set url to post to
                            $result = curl_exec($ch); // run the whole process

                            $fp = fopen($image_path.'/'.$row['m'].'.'.pathinfo($image_link, PATHINFO_EXTENSION), 'wb');
                            fwrite($fp, $result);
                            fclose($fp);

                            $update_prod = $_SESSION['up'] ? $_SESSION['up'] : array();

                            $update_prod[$row['id']]['path'] = 'data/'.$row['m'].'.'.pathinfo($image_link, PATHINFO_EXTENSION);
                            $update_prod[$row['id']]['name'] = $row['m'];
                            $_SESSION['up'] = $update_prod;
                        } else {
                            $result = getFromGoogle($row['id'], $row['m'], $image_path);
                            if(!$result){
                                echo $row['m']." Не удается получить изображение <br />";
                            }
                        }
                    }


                    curl_close($ch);

                } else {
                    $result = getFromGoogle($row['id'], $row['m'], $image_path);
                    if(!$result){
                        echo $row['m']." не числится на Yandex.Market <br />";
                    }
                }

            }
            $query_result->close();
        }
        $update_prod = $_SESSION['up'];
        $index = 0;
        foreach($update_prod as $id=>$prod){
            $query = "UPDATE product SET image = '".$prod['path']."' WHERE product_id = ".$id;
            $qr = $mysqli->query($query);
            if($mysqli->affected_rows > 0){
                echo $prod['name']." изображение обновлено <br />";
                $index++;
            } else {
                echo $prod['name']." изображение небыло обновлено <br />";
            }
        }
        echo "<br />Скачано {$index} изображений";
        session_unset();
        session_destroy();
    }

    function getFromGoogle($id, $prod, $image_path, $start = null){
        $url = "https://ajax.googleapis.com/ajax/services/search/images?v=1.0&q=".urlencode($prod);
        if($start){
            $url .= "&start=".$start;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url); // set url to post to
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// allow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // times out after 4s
        curl_setopt($ch, CURLOPT_POST, 0); // set POST method
        $result = curl_exec($ch); // run the whole process
        if(curl_errno($ch)){
            echo curl_error($ch);
        } else {
            $data = json_decode($result, true);
            $deltas = array();
            $image_url = null;
            foreach($data['responseData']['results'] as $key => $result){
                if(filter_var($result['url'], FILTER_VALIDATE_URL)){
                    if($result['width'] > 750 && $result['height'] < 850) {

                        $image_url = $result['url'];
                        break;
                    } else {
                        $deltas[abs(800 - $result['width'])] = $result['url'];
                    }
                }
            }
            if($image_url || count($deltas) > 0){
                if(!$image_url){
                    ksort($deltas);
                    foreach($deltas as $delta){
                        curl_setopt($ch, CURLOPT_URL, $delta); // set url to post to
                        $result = curl_exec($ch); // run the whole process
                        if($result){
                            $image_url = $delta;
                            break;
                        }
                    }
                } else {
                    curl_setopt($ch, CURLOPT_URL, $image_url); // set url to post to
                    $result = curl_exec($ch); // run the whole process
                    if(!$result){
                        ksort($deltas);
                        foreach($deltas as $delta){
                            curl_setopt($ch, CURLOPT_URL, $delta); // set url to post to
                            $result = curl_exec($ch); // run the whole process
                            if($result){
                                $image_url = $delta;
                                break;
                            }
                        }
                    }
                }

                if($image_url && $result){
                    $fp = fopen($image_path.'/'.$prod.'.'.pathinfo($image_url, PATHINFO_EXTENSION), 'wb');
                    fwrite($fp, $result);
                    fclose($fp);

                    $update_prod = $_SESSION['up'] ? $_SESSION['up'] : array();
                    $update_prod[$id]['path'] = 'data/'.$prod.'.'.pathinfo($image_url, PATHINFO_EXTENSION);
                    $update_prod[$id]['name'] = $prod;
                    $_SESSION['up'] = $update_prod;
                    return true;
                } else {
                    $result = getFromGoogle($id, $prod, $image_path, $start ? $start + 4 : 4);
                    return $result;
                }

            } else {
                $result = getFromGoogle($id, $prod, $image_path, $start ? $start + 4 : 4);
                return $result;
            }
        }

    }