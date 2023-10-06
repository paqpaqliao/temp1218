<?php


require_once 'set.php';


// $table = "";
// $keyid = $table."_id";
if( !empty($_POST) )  $post = postfun($_POST);
// $sortkey = "sort";



$carttable = $thisdbpre."cartlist";
$cartkeyid = "cartlist_id";




// echo $_SESSION['mem_power'];



if( $_POST['action'] == 'logout' ){

    unset( $_SESSION['mem_power'] );
    unset( $_SESSION['mem_no'] );

    $data["restatus"] = "out";

    echo json_encode($data);
}





if( $_POST['action'] == 'chkmember' ){

    if( $_SESSION['mem_power']=="ysa" &&  $_SESSION['mem_no']>=1  &&  !empty($_SESSION['mem_logintime'])  ){

        $log_time = $_SESSION['mem_logintime'];

        $need_out_time = $log_time + 3600;

        if( time() >= $need_out_time ) {

            // 超過時間，執行登出
            unset( $_SESSION['mem_power'] );
            unset( $_SESSION['mem_no'] );
            unset( $_SESSION['mem_logintime'] );

            $data["restatus"] = "超過登入時間，請重新登入!";

        }else{

            // 未超過時間  ok
            $data["restatus"] = "in";
            $data["point"] = "in";

        }

    }else{

        $data["restatus"] = "out";

    }

    echo json_encode($data); 

}







if( $_POST['action'] == 'commonget' ){

    $keyid = $post['table'].'_id';
    $table = $thisdbpre.$post['table'];


    if( $post['vis']!='' ){
        
        $conditions['where'] = array( 
            $post['vis'] => 1
        );
    }

    if( $post['sortkey']!='' ){

        $conditions['order_by'] = $post['sortkey'].' DESC';
    }

    $result = $db->getRows( $table, $conditions );
    $result = twoarychgkey( $result, $keyid, $thisdbid );

    if( $result==false ){

        $data["restatus"] = "false";

    }else{

        $data["data"] = $result;
    }

    echo json_encode($data);
}





if( $_POST['action'] == 'firstlist' ){

    $keyid = $post['table'].'_id';
    $table = $thisdbpre.$post['table'];


    if( $post['vis']!='' ){
        
        $conditions['where'] = array( 
            $post['vis'] => 1
        );
    }

    $conditions['order_by'] = $post['sortkey'].' DESC';
    $conditions['limit'] = 1;

    $result = $db->getRows( $table, $conditions );
    $result = twoarychgkey( $result, $keyid, $thisdbid );

    if( $result==false ){

        $data["restatus"] = "false";

    }else{

        $data["data"] = $result[0];
    }

    echo json_encode($data);
}






if( $_POST['action'] == 'view' ){

    $keyid = $post['table'].'_id';
    $table = $thisdbpre.$post['table'];

    $conditions['where'] = array( $keyid => $_POST['id'] );
    $conditions['return_type'] = 'single';

    $result = $db->getRows( $table, $conditions );
    $result = arychgkey($result, $keyid, $thisdbid );
    
    echo json_encode($result);
}


    // echo session_id();


    // $table2 = "c_cartlist";
    // $keyid2 = "cartlist_id";




function regetCartlist(){

    
    $db = new DB();

    $table = "c_cartlist";
    $keyid = "cartlist_id";


    // 01. 取得購物車 正品資料 (這邊不能把全部的欄位資料讀出來，因為product類別(可新增類別)會跟結帳商品類別(正品;兌換品;贈品;加購品)衝突!)
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.product_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_cartlist.point, c_product.pointtype, c_product.name, c_product.pic01, c_product.price01, c_product.price02, c_product.extrapoint, c_product.vis, c_product.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 
        'c_cartlist.kind' => 1,
        'c_product.vis'   => 1,
        'c_product.open'  => 1,
    );

    $conditions['jointable'] = 'c_product';
    $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    $cart1 = $db->getRows( $table, $conditions );
    unset($conditions);

    // print_r($cart1);
    // echo "<br><br><br>";

    if( $cart1!=false ){

        // 取得暫存的購物車資料後，因可能是前幾天的紀錄，需要重新比對資料
        // 取得 cart id 後  取產品最新資訊

        // 更新 價錢、點數
        for($i = 0;$i<count($cart1);$i++){

            if( !empty($cart1[$i]['price02'])  &&  $cart1[$i]['price02']>0 ){

                $price[$i] = $cart1[$i]['price02'];

            }else if( $cart1[$i]['price01']>0 ) {

                $price[$i] = $cart1[$i]['price01']; 

            }else{

                $price[$i] = 0;
            }

            $point[$i] = pointCalc($cart1[$i]);

            $cart1[$i]['price'] = $price[$i];
            $cart1[$i]['point'] = $point[$i];
        }

        
        $cart = $cart1;

        $pointdata = pointCalcTotal($cart);
        
        $data["point"] = $pointdata;

    }else{

        // 回傳 無購物資料
        // echo json_encode($data);
        // exit;
    }





    // 02. 取得購物車中 加購品(滿件)資料
    // $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.product_id, c_cartlist.exchange_id, c_cartlist.addproduct_id, c_cartlist.kind, c_cartlist.pointtype, c_cartlist.name, c_cartlist.pic, c_cartlist.price, c_cartlist.qty, c_cartlist.extrapoint, c_addproduct.needqty, c_addproduct.needbuy ';

    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.addproduct_id, c_cartlist.kind, c_addproduct.name, c_cartlist.price,  c_cartlist.qty, 
    c_addproduct.addprice01, c_addproduct.addprice02, c_addproduct.pic01, c_addproduct.needqty, c_addproduct.needbuy, c_addproduct.open, c_addproduct.vis';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind'   => 2,
        'c_addproduct.vis'  => 1,
        'c_addproduct.open' => 1,
    );

    $conditions['jointable'] = 'c_addproduct';
    $conditions['joinkey'] = 'c_addproduct.addproduct_id = c_cartlist.addproduct_id';

    $extra = $db->getRows( $table, $conditions );
    unset($conditions);

    // echo "未處理的加購品資料";
    // print_r($cart2);
    // echo "<br><br><br>";

    if( $extra!=false  &&  $cart1!=false ){

        // 更新價錢
        for($i = 0;$i<count($extra);$i++){

            if( !empty($extra[$i]['addprice02'])  &&  $extra[$i]['addprice02']>0 ){

                $addprice[$i] = $extra[$i]['addprice02'];

            }else if( $extra[$i]['addprice01']>0 ) {

                $addprice[$i] = $extra[$i]['addprice01']; 

            }else{

                $addprice[$i] = 0;
            }

            $extra[$i]['price'] = $addprice[$i];
        }

        // 需要加購品 max，比對計算
        $cart2 = extraCalc( $extra, $cart1 );

        // 更新 加購品資料
        $cart2 = upCartExtra($cart2);

        foreach($cart2 as $val) {
            array_push( $cart, $val );
        }
    }






    // 03. 取得購物車 兌換品資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.exchange_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_exchange.name, c_exchange.pic01, c_exchange.point, c_exchange.vis, c_exchange.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 3,
        'c_exchange.vis'  => 1,
        'c_exchange.open' => 1,
    );

    $conditions['jointable'] = 'c_exchange';
    $conditions['joinkey'] = 'c_exchange.exchange_id = c_cartlist.exchange_id';

    $cart3 = $db->getRows( $table, $conditions );
    unset($conditions);

    $haspoint = getPoint('all');
    $exchg_haspoint = $haspoint;

    // print_r($cart3);
    // echo "<br><br><br>";
    // echo count($cart3)."<br><br><br>";

    if( $cart3!=false ) {

        if( $cart1!=false ) {

            // 更新 兌換點數
            for($i = 0;$i<count($cart3);$i++){

                if( $haspoint >= $cart3[$i]['point'] ){

                    // 有足夠點數，更新最新 兌換點數
                    $cart3[$i]['price'] = $cart3[$i]['point'];

                }else{

                    // 刪除db該筆 兌換品
                    $condition = array( $keyid => $cart3[$i][$keyid] );
                    $del = $db->delete( $table, $condition );
                    unset($conditions);

                    // 刪除撈出來的資料
                    unset($cart3[$i]);
                }

                // 計算使用兌換後 還有的點數
                $haspoint = $haspoint - $cart3[$i]['point'];
              
            }

            foreach($cart3 as $val) {
                array_push( $cart, $val );
            }

        }else{

            // 刪除 兌換品
            $conditions['where'] = array( 
                'c_cartlist.kind' => 3
            );

            $conditions['search'] = array( 
                'session_id' => session_id()
            );

            // $condition = array( $keyid => $cart3[$i][$keyid] );
            $del = $db->delete( $table, $condition );
            unset($conditions);
        }
    }




    // 04. 取得購物車 贈品資料

    up_giveaway($cart1);


    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.giveaway_id, c_cartlist.price, c_cartlist.kind, c_cartlist.qty, c_giveaway.name, c_giveaway.pic01, c_giveaway.needbuy, c_giveaway.fullqty, c_giveaway.fullprice, c_giveaway.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 4,
        'c_giveaway.open' => 1,
    );
    
    $conditions['jointable'] = 'c_giveaway';
    $conditions['joinkey'] = 'c_giveaway.giveaway_id = c_cartlist.giveaway_id';


    $cart4 = $db->getRows( $table, $conditions );
    unset($conditions);

    // print_r($cart3);
    // echo "<br><br><br>";
    // echo count($cart3)."<br><br><br>";

    if( $cart4!=false ) {

        if( $cart1!=false ) {

            foreach($cart4 as $val) {
                array_push( $cart, $val );
            }

        // }else{

        }
    }




    // 05. 取得購物車中 加購品(滿額)資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.addproduct_amount_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_addproduct_amount.name, c_addproduct_amount.needprice, c_addproduct_amount.pic01, c_addproduct_amount.addprice01, c_addproduct_amount.addprice02, c_addproduct_amount.vis, c_addproduct_amount.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 5,
        'c_addproduct_amount.vis' => 1,
        'c_addproduct_amount.open' => 1,
    );

    $conditions['jointable'] = 'c_addproduct_amount';
    $conditions['joinkey'] = 'c_addproduct_amount.addproduct_amount_id = c_cartlist.addproduct_amount_id';

    $extra02 = $db->getRows( $table, $conditions );
    unset($conditions);

    // echo "未處理的加購品資料";
    // print_r($cart2);
    // echo "<br><br><br>";

    if( $extra02!=false  &&  $cart1!=false ){

        // 更新價錢
        for($i = 0;$i<count($extra02);$i++){

            if( !empty($extra02[$i]['addprice02'])  &&  $extra02[$i]['addprice02']>0 ){

                $addprice[$i] = $extra02[$i]['addprice02'];

            }else if( $extra02[$i]['addprice01']>0 ) {

                $addprice[$i] = $extra02[$i]['addprice01']; 

            }else{

                $addprice[$i] = 0;
            }

            $extra02[$i]['price'] = $addprice[$i];
        }


        // 需要加購品 max，比對計算
        $cart5 = extraCalc02( $extra02, $cart1, 0 );
        // print_r($cart2);
        // echo "<br><br><br>";

        $cart5 = upCartExtra($cart5);


        foreach($cart5 as $val) {
            array_push( $cart, $val );
        }
    }



    if( $cart!='' ){

        $result = array_values($cart);
        // print_r($result);
        // echo "<br><br><br>";
    }


    if( count($cart)>0 ){

        return $cart;

    }else{

        return false;
    }

}



if( $_POST['action'] == 'cartlist' ){

    $table = "c_cartlist";
    $keyid = "cartlist_id";

    $table2 = "c_fare";
    $keyid2 = "fare_id";


    // 取得運費規則
    $fare = getFare($table2, $keyid2);
    // print_r($fare);

    if( $fare==false ){

        $data["restatus"] = "nofare";

        // 回傳 發生錯誤
        echo json_encode($data);
        exit();

    }else{

        $data["fare"] = $fare;
    }





    // 01. 取得購物車 正品資料 (這邊不能把全部的欄位資料讀出來，因為product類別(可新增類別)會跟結帳商品類別(正品;兌換品;贈品;加購品)衝突!)
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.product_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_cartlist.point, c_product.pointtype, c_product.name, c_product.pic01, c_product.price01, c_product.price02, c_product.extrapoint, c_product.vis, c_product.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 
        'c_cartlist.kind' => 1,
        'c_product.vis'   => 1,
        'c_product.open'  => 1,
    );

    $conditions['jointable'] = 'c_product';
    $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    $cart1 = $db->getRows( $table, $conditions );
    unset($conditions);

    // print_r($cart1);
    // echo "<br><br><br>";

    if( $cart1!=false ){

        // 取得暫存的購物車資料後，因可能是前幾天的紀錄，需要重新比對資料
        // 取得 cart id 後  取產品最新資訊

        // 更新 價錢、點數
        for($i = 0;$i<count($cart1);$i++){

            if( !empty($cart1[$i]['price02'])  &&  $cart1[$i]['price02']>0 ){

                $price[$i] = $cart1[$i]['price02'];

            }else if( $cart1[$i]['price01']>0 ) {

                $price[$i] = $cart1[$i]['price01']; 

            }else{

                $price[$i] = 0;
            }

            $point[$i] = pointCalc($cart1[$i]);

            $cart1[$i]['price'] = $price[$i];
            $cart1[$i]['point'] = $point[$i];
        }

        
        $cart = $cart1;

        $pointdata = pointCalcTotal($cart);
        
        $data["point"] = $pointdata;

    }else{

        // 回傳 無購物資料
        // echo json_encode($data);
        // exit;
    }





    // 02. 取得購物車中 加購品(滿件)資料
    // $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.product_id, c_cartlist.exchange_id, c_cartlist.addproduct_id, c_cartlist.kind, c_cartlist.pointtype, c_cartlist.name, c_cartlist.pic, c_cartlist.price, c_cartlist.qty, c_cartlist.extrapoint, c_addproduct.needqty, c_addproduct.needbuy ';

    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.addproduct_id, c_cartlist.kind, c_addproduct.name, c_cartlist.price,  c_cartlist.qty, 
    c_addproduct.addprice01, c_addproduct.addprice02, c_addproduct.pic01, c_addproduct.needqty, c_addproduct.needbuy, c_addproduct.open, c_addproduct.vis';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind'   => 2,
        'c_addproduct.vis'  => 1,
        'c_addproduct.open' => 1,
    );

    $conditions['jointable'] = 'c_addproduct';
    $conditions['joinkey'] = 'c_addproduct.addproduct_id = c_cartlist.addproduct_id';

    $extra = $db->getRows( $table, $conditions );
    unset($conditions);

    // echo "未處理的加購品資料";
    // print_r($cart2);
    // echo "<br><br><br>";

    if( $extra!=false  &&  $cart1!=false ){

        // 更新價錢
        for($i = 0;$i<count($extra);$i++){

            if( !empty($extra[$i]['addprice02'])  &&  $extra[$i]['addprice02']>0 ){

                $addprice[$i] = $extra[$i]['addprice02'];

            }else if( $extra[$i]['addprice01']>0 ) {

                $addprice[$i] = $extra[$i]['addprice01']; 

            }else{

                $addprice[$i] = 0;
            }

            $extra[$i]['price'] = $addprice[$i];
        }

        // 需要加購品 max，比對計算
        $cart2 = extraCalc( $extra, $cart1 );

        // 更新 加購品資料
        $cart2 = upCartExtra($cart2);

        foreach($cart2 as $val) {
            array_push( $cart, $val );
        }
    }






    // 03. 取得購物車 兌換品資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.exchange_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_exchange.name, c_exchange.pic01, c_exchange.point, c_exchange.vis, c_exchange.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 3,
        'c_exchange.vis'  => 1,
        'c_exchange.open' => 1,
    );

    $conditions['jointable'] = 'c_exchange';
    $conditions['joinkey'] = 'c_exchange.exchange_id = c_cartlist.exchange_id';

    $cart3 = $db->getRows( $table, $conditions );
    unset($conditions);

    $haspoint = getPoint('all');
    $exchg_haspoint = $haspoint;

    // print_r($cart3);
    // echo "<br><br><br>";
    // echo count($cart3)."<br><br><br>";

    if( $cart3!=false ) {

        if( $cart1!=false ) {

            // 更新 兌換點數
            for($i = 0;$i<count($cart3);$i++){

                if( $haspoint >= $cart3[$i]['point'] ){

                    // 有足夠點數，更新最新 兌換點數
                    $cart3[$i]['price'] = $cart3[$i]['point'];

                }else{

                    // 刪除db該筆 兌換品
                    $condition = array( $keyid => $cart3[$i][$keyid] );
                    $del = $db->delete( $table, $condition );
                    unset($conditions);

                    // 刪除撈出來的資料
                    unset($cart3[$i]);
                }

                // 計算使用兌換後 還有的點數
                $haspoint = $haspoint - $cart3[$i]['point'];
              
            }

            foreach($cart3 as $val) {
                array_push( $cart, $val );
            }

        }else{

            // 刪除 兌換品
            $conditions['where'] = array( 
                'c_cartlist.kind' => 3
            );

            $conditions['search'] = array( 
                'session_id' => session_id()
            );

            // $condition = array( $keyid => $cart3[$i][$keyid] );
            $del = $db->delete( $table, $condition );
            unset($conditions);
        }
    }





    up_giveaway($cart1);


    // 04. 取得購物車 贈品資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.giveaway_id, c_cartlist.price, c_cartlist.kind, c_cartlist.qty, c_giveaway.name, c_giveaway.pic01, c_giveaway.needbuy, c_giveaway.fullqty, c_giveaway.fullprice, c_giveaway.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );


    $conditions['where'] = array( 
        'c_cartlist.kind' => 4,
        'c_giveaway.open' => 1,
    );
    
    $conditions['jointable'] = 'c_giveaway';
    $conditions['joinkey'] = 'c_giveaway.giveaway_id = c_cartlist.giveaway_id';


    $cart4 = $db->getRows( $table, $conditions );
    unset($conditions);

    // print_r($cart3);
    // echo "<br><br><br>";
    // echo count($cart3)."<br><br><br>";

    if( $cart4!=false ) {

        if( $cart1!=false ) {

            foreach($cart4 as $val) {
                array_push( $cart, $val );
            }

        }else{


        }
    }





    // 05. 取得購物車中 加購品(滿額)資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.addproduct_amount_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_addproduct_amount.name, c_addproduct_amount.needprice, c_addproduct_amount.pic01, c_addproduct_amount.addprice01, c_addproduct_amount.addprice02, c_addproduct_amount.vis, c_addproduct_amount.open ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 5,
        'c_addproduct_amount.vis' => 1,
        'c_addproduct_amount.open' => 1,
    );

    $conditions['jointable'] = 'c_addproduct_amount';
    $conditions['joinkey'] = 'c_addproduct_amount.addproduct_amount_id = c_cartlist.addproduct_amount_id';

    $extra02 = $db->getRows( $table, $conditions );
    unset($conditions);

    // echo "未處理的加購品資料";
    // print_r($cart2);
    // echo "<br><br><br>";

    if( $extra02!=false  &&  $cart1!=false ){

        // 更新價錢
        for($i = 0;$i<count($extra02);$i++){

            if( !empty($extra02[$i]['addprice02'])  &&  $extra02[$i]['addprice02']>0 ){

                $addprice[$i] = $extra02[$i]['addprice02'];

            }else if( $extra02[$i]['addprice01']>0 ) {

                $addprice[$i] = $extra02[$i]['addprice01']; 

            }else{

                $addprice[$i] = 0;
            }

            $extra02[$i]['price'] = $addprice[$i];
        }


        // 需要加購品 max，比對計算
        $cart5 = extraCalc02( $extra02, $cart1, 0 );
        // print_r($cart2);
        // echo "<br><br><br>";

        $cart5 = upCartExtra($cart5);


        foreach($cart5 as $val) {
            array_push( $cart, $val );
        }
    }




    if( $cart!='' ){

        $result = array_values($cart);
        // print_r($result);
        // echo "<br><br><br>";
    }
    

    $result = twoarychgkey( $result, $keyid, $thisdbid );
    $total  = cartTotalCalc($result);

    if( $total!='' ){

        $_SESSION['c_subtotal'] = $total;
        
        if( $total >= $fare['free'] ){
            
            $fee = 0;

        }else{

            $fee = $fare['fare'];
        }
 
        $_SESSION['c_fee'] = $fee;
    }


    if( $result==false ){

        $data["restatus"] = "false";

    }else{
      
        $data["data"] = $result;
        $data["subtotal"] = $total;
        $data["fee"] = $fee;
    }



    // $data["aaa"] = $aaaa;

    // 完成後 return OK, 回傳資料
    echo json_encode($data);

}


// 計算 點數
// function pointCalc($item, $qty){

function pointCalc($item){

    $point_x1 = 25;
    $point_x2 = 100;

    $sub = 0;

    if( $item['pointtype']==1 ){

        $sub = round($item['price']/$point_x1);
        // $sub = $item['price']/$point_x1*$qty;
    }

    if( $item['pointtype']==2 ){

        $sub = round($item['price']/$point_x2);
        // $sub = $item['price']/$point_x2*$qty;
    }

    return $sub;
}



function pointCalcTotal($cart){

    $i = 0;

    foreach( $cart as $list ) {

        // $point_x1 = 25;
        // $point_x2 = 100;

        $subadd[$i] = 0;
        $subuse[$i] = 0;

        if( $list['kind']==1 ){

            $subadd[$i] = ($list['point']+$list['extrapoint']) * $list['qty'];

            // if( $list['pointtype']==1 ){

            //     $subadd[$i] = $list['price']*$list['qty']/$point_x1;
            // }

            // if( $list['pointtype']==2 ){

            //     $subadd[$i] = $list['price']*$list['qty']/$point_x2;
            // }

            // if( $list['pointtype']==0 ){

            //     $subadd[$i] = 0;
            // }

            // if( $list['extrapoint']!='' ){

            //     $subadd[$i] += $list['extrapoint']*$list['qty'];
            // }

            // echo "sub: ".$subadd[$i]."<br>";

            $addpoint += $subadd[$i];

            // echo "total: ".$addpoint."<br>";
            // echo "total&extra: ".$addpoint."<br>";
        }


        if( $list['kind']==3 ){
            $subuse[$i] = $list['price']*$list['qty'];
            $usepoint += $subuse[$i];
        }

        $i++;
    }


    $point['addpoint'] = $addpoint;
    $point['usepoint'] = $usepoint;

    return $point;
    
}


function getFare(){

    $db = new DB();
    $table = "c_fare";
    $keyid = "fare_id";

    // 取得運費規則
    $conditions['select'] = ' fare, free ';
    $conditions['return_type'] = 'single';
    $conditions['where'] = array( $keyid => 4 );

    $fee = $db->getRows( $table, $conditions );
    unset($conditions);

    if( $fee==false ){

        return false;
 
    }else{

        return $fee;
    }

}


function cartTotalCalc($ary) {

    if( $ary ){

        $i=0;

        foreach($ary as $val) {

            if( $val['kind']==1 || $val['kind']==2 || $val['kind']==5 ){

                $sub[$i] = $val['price']*$val['qty'];

                $total += $sub[$i];
            }

            $i++;
        }

        return $total;
    }

}




// $post['addpd'] = "5";
// $post['addqty'] = "1";



if( $_POST['action'] == 'addgoods' ){

    $table1 = "c_product";
    $keyid1 = "product_id";

    $table2 = "c_cartlist";
    $keyid2 = "cartlist_id";

    if( isset($post['addpd'])  &&  isset($post['addqty']) ){

        // 判斷購物車中是否有同樣商品
        $conditions['search'] = array( 
            'session_id' => session_id()
        );

        $conditions['where'] = array( $keyid1 => $post['addpd'] );
        $conditions['return_type'] = 'single';
        $chk = $db->getRows( $table2, $conditions );
        unset($conditions);

        if( is_array($chk) ){

            // 有，修改數量
            $listid = $chk['cartlist_id'];
            $newqty = $chk['qty']+$post['addqty'];

            // $point = pointCalc($chk, $newqty);

            $newData = array(
                'qty' => $newqty,
                // 'point' => $point,
            );

            $condition = array( $keyid2 => $listid );
            $edit = $db->update( $table2, $newData, $condition );


            if( $edit!=false ){

                $data["restatus"] = "edit ok";

            }else{

                $data["restatus"] = "edit false";
            }

        }else{

            // 沒有，加入

            // 先依 id 取得商品資料
            $conditions['where'] = array( $keyid1 => $post['addpd'] );
            $conditions['return_type'] = 'single';
            $result = $db->getRows( $table1, $conditions );
            unset($conditions);


            // 寫入 cart
            if ( is_array($result) ) {

                if( !empty($result['price02'])  &&  $result['price02']>0 ){

                    $price = $result['price02'];

                }else if( $result['price01']>0 ) {

                    $price = $result['price01'];  

                }else{

                    return false;
                }

                // 寫入金額，才能計算
                // $result['price'] = $price;
               
                // 計算點數
                // $point = pointCalc($result, $post['addqty']);

                $newData = array(
                    'session_id' => session_id(),
                    // 'mid' => $post['mid'],
                    'product_id' => $post['addpd'],
                    'pointtype' => $result['pointtype'],
                    'kind'  => 1,
                    'name'  => $result['name'],
                    'price' => $price,
                    'qty'   => $post['addqty'],
                    // 'pic'   => $result['pic01'],
                    // 'point' => $point,
                    // 'extrapoint' => $result['extrapoint'],
                    'day' => date('Y-m-d H:i:s')
                );
                
                $add = $db->insert( $table2 , $newData );

                if( $add!=false ){

                    $data["restatus"] = "add ok";
                }else{

                    $data["restatus"] = "add false";
                }
               
            }
        }

        unset($chk);
        unset($result);
        unset($add);
        unset($edit);
    }

    // 完成後 return OK, 回傳資料
    echo json_encode($data);

}


if( $_POST['action'] == 'editgoods' ){

    $table1 = "c_product";
    $keyid1 = "product_id";

    $table2 = "c_cartlist";
    $keyid2 = "cartlist_id";

    if( isset($post['cno'])  &&  isset($post['qty']) ){

        // 判斷購物車中是否有同樣商品
        $conditions['search'] = array( 
            'session_id' => session_id()
        );

        $conditions['where'] = array( $keyid2 => $post['cno'] );
        $conditions['return_type'] = 'single';
        $chk = $db->getRows( $table2, $conditions );
        unset($conditions);


        // 有 ↓
        if( $chk!=false ){

            // 再次確認商品是否可以購買 ↓
            $conditions['where'] = array( 
                $keyid1 => $chk[$keyid1],
                'vis' => 1,
                'open' => 1
            );
            $conditions['return_type'] = 'single';
            $result = $db->getRows( $table1, $conditions );
            unset($conditions);


            // 可 ，修改數量 ↓
            if( $result!=false ){

                $listid = $chk[$keyid2];
                $newqty = $post['qty'];

                $newData = array(
                    'qty' => $newqty
                );

                $condition = array( $keyid2 => $listid );
                $edit = $db->update( $table2, $newData, $condition );
            }

        // 沒有 無法修改
        }else{

            // 顯示錯誤
            Msg_Alert01('發生錯誤','../index.php');
        }


        if( $edit==false ){

            $data["restatus"] = "false";

        }else{

            $data["restatus"] = "ok";
        }

        unset($chk);
        unset($result);
        unset($edit);
    }

    // 完成後 return OK, 回傳資料
    echo json_encode($data);

}




function getPoint($key){

    $db = new DB();

    $ordertable = "c_orderform";
    $orderkeyid = "orderform_id";

    $carttable = "c_cartlist";
    $cartkeyid = "cartlist_id";


    // 1. 計算累積點數

        $conditions['select'] = ' orderform_id, orderno, buyday, status, status_txt, total, freight, addpoint, usepoint, retpoint ';
        $conditions['where'] = array( 
            'mid' => $_SESSION['mem_no'],
            // 'mid' => 38
        );

        $start_date = date('Y-m-d H:i:s', strtotime("-730 day"));
        $conditions['other_sql'] = ' AND buyday >= "'.$start_date.'"';
        $conditions['order_by'] = ' buyday DESC ';


        $result = $db->getRows( $ordertable, $conditions );
        unset($conditions);
        // print_r($result);


        if( $result!=false ){

            if( is_array($result) ){

                foreach( $result as $ary ) {

                    if( $ary['status']!= '已取消'){

                        $add = $add+ $ary['addpoint'];
                        $use = $use+ $ary['usepoint'];
                        $cut = $cut+ $ary['retpoint'];

                    }
                    // $i++;
                }
            }
        }


        $p = $add-$use-$cut;

        // echo "<br><br><br>";
        // print_r($result);

        // echo "<br><br><br>";
        // echo $add."<br>";
        // echo $use."<br>";
        // echo $cut."<br>";


    // 2. 扣除購物車裡面使用的點數

        $conditions['search'] = array( 
            'session_id' => session_id()
        );
        $conditions['where'] = array( 'kind' => 3 );

        $cartuse = $db->getRows( $carttable, $conditions );
        unset($conditions);

        $exchg = 0;

        if( $cartuse!=false ){

            if( is_array($cartuse) ){

                foreach( $cartuse as $val ) {

                    $exchg = $exchg+ ($val['price']*$val['qty']);
                }
            }
        }


        $p = $p - $exchg;


    // ok > return 目前可使用點數

    if( $key=='all' ){

        return $p+$exchg;

    }else{

        return $p;
    } 

}



// $haspoint = getPoint();
// echo $haspoint;

// $post['addpd'] = "4";
// $post['addqty'] = "1";





if( $_POST['action'] == 'addexchange' ){
 

    $table1 = $thisdbpre."exchange";
    $keyid1 = "exchange_id";

    $table2 = $thisdbpre."cartlist";
    $keyid2 = "cartlist_id";


    // 1. 確認購物車狀態
    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 'kind' => 1 );



    $chkcart = $db->getRows( $table2, $conditions );
    unset($conditions);

    if( $chkcart==false ){

        $data["restatus"] = "nobuy";

        echo json_encode($data);

        exit();
    }



    // 2. 確認登入狀態
    if( $_SESSION['mem_power']!='ysa' ){

        $data["restatus"] = "notlogin";

        echo json_encode($data);

        exit();
    
    }else{

        if( isset($post['addpd'])  &&  isset($post['addqty']) ){


            // 3. 取得目前可以兌換的點數(已經扣除加入購物車中點數)
            $haspoint = getPoint('');
            // echo $haspoint;


            // 4. 兌換需要點數
            $conditions['where'] = array( $keyid1 => $post['addpd'] );
            $conditions['return_type'] = 'single';
            $needsch = $db->getRows( $table1, $conditions );

            $need = $needsch['point'];

            unset($needsch);
            unset($conditions);

            // 有足夠的點數，可以兌換
            if( $haspoint >= $need ){

                // 3. 扣點 & 加入購物車 

                // 判斷購物車中是否有同樣商品
                $conditions['search'] = array( 
                    'session_id' => session_id()
                );

                $conditions['where'] = array( $keyid1 => $post['addpd'] );
                $conditions['return_type'] = 'single';
                $chk = $db->getRows( $table2, $conditions );
                unset($conditions);

                if( is_array($chk) ){

                    // 有，修改數量
                    $listid = $chk['cartlist_id'];
                    $oldqty = $chk['qty']+$post['addqty'];

                    $newData = array(
                        'qty' => $oldqty
                    );

                    $condition = array( $keyid2 => $listid );
                    $edit = $db->update( $table2, $newData, $condition );

                }else{

                    // 沒有，加入


                    // 先依 id 取得商品資料
                    $conditions['where'] = array( $keyid1 => $post['addpd'] );
                    $conditions['return_type'] = 'single';
                    $result = $db->getRows( $table1, $conditions );
                    unset($conditions);

                    // 寫入 cart
                    if ( is_array($result) ) {
                   
                        $newData = array(
                            'session_id' => session_id(),
                            // 'mid' => $post['mid'],
                            'exchange_id' => $post['addpd'],
                            'kind'  => 3,
                            'name'  => $result['name'],
                            'price' => $result['point'],
                            'qty'   => $post['addqty'],
                            // 'pic'   => $result['pic01'],
                            'day' => date('Y-m-d H:i:s')
                        );
                        
                        $edit = $db->insert( $table2 , $newData );
                    }

                }


                if( $edit==false ){

                    $data["restatus"] = "false";

                }else{

                    $data["restatus"] = "ok";
                }

                unset($chk);
                unset($result);
                // unset($add);
                unset($edit);


            }else{

                $data["restatus"] = "notenough";

                $data["hasp"] = $haspoint;
                // $data["need"] = $need;
            }



        }


    }


    // 完成後 return OK, 回傳資料
    echo json_encode($data);

}





// 滿額
if( $_POST['action'] == 'getExtra_amount' ){

    $table = $thisdbpre."addproduct_amount";
    $keyid = "addproduct_amount_id";

    
    // $cart = $post['cart'];

    // 取得購物車 正品資料
    // $conditions['select'] = ' cartlist_id, product_id, kind, qty, price ';
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.product_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_product.name, c_product.pic01, c_product.price01, c_product.price02 ';
   
    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 1,
        'c_product.vis' => 1,
        'c_product.open' => 1,
    );

    $conditions['jointable'] = 'c_product';
    $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    $cart = $db->getRows( 'c_cartlist', $conditions );
    unset($conditions);

        
    // 更新 價錢
    for( $i=0; $i<count($cart); $i++ ){

        if( !empty($cart[$i]['price02'])  &&  $cart[$i]['price02']>0 ){

            $price[$i] = $cart[$i]['price02'];

        }else if( $cart[$i]['price01']>0 ) {

            $price[$i] = $cart[$i]['price01']; 

        }else{

            $price[$i] = 0;
        }

        $cart[$i]['price'] = $price[$i];

        // 目前購物總金額
        $tempsub += $cart[$i]['price']*$cart[$i]['qty'];
    }


    // 先計算 目前購物總金額
    // $cartsum = count($cart);

    // for( $i=0; $i<$cartsum; $i++ ){

    //     echo $tempsub;
    // }



    // 將 符合滿額 的加購品資料撈出來
    $conditions['where'] = array( 'vis' => 1 );
    $conditions['order_by'] = ' sort DESC ';
    $conditions['select'] = ' addproduct_amount_id, name, addprice01, addprice02, pic01, needprice, open, closetxt ';
    $conditions['other_sql'] = ' And needprice <= '.$tempsub;

    $ary = $db->getRows( $table, $conditions );
    unset($conditions);

    // print_r($ary);
    // echo "<br><br><br><br>";
    // print_r($cart);

    if( $ary!=false ){

        // 計算
        $result = extraCalc02( $ary, $cart, $tempsub );
        $result = twoarychgkey( $result, $keyid, $thisdbid );
        // print_r($result);
        // echo "<br><br><br><br>";

        $sum = count($result);

        if( $result==false ){

            $data["restatus"] = "false";
            $data["data"] = $cart;

        }else{

            $data["data"] = $result;
        }

    }else{
        
        $data["restatus"] = "false";
    }


    $data["tto"] = $tempsub;

    echo json_encode($data);

}

if( $_POST['action'] == 'addextra_amount' ){

    $table1 = "c_addproduct_amount";
    $keyid1 = "addproduct_amount_id";

    $table2 = "c_cartlist";
    $keyid2 = "cartlist_id";


    if( isset($post['addpd'])  &&  isset($post['addqty']) ){

        // 判斷購物車中是否有同樣商品
        $conditions['search'] = array( 
            'session_id' => session_id()
        );

        $conditions['where'] = array( $keyid1 => $post['addpd'] );
        $conditions['return_type'] = 'single';
        $chk = $db->getRows( $table2, $conditions );
        unset($conditions);

        if( is_array($chk) ){

            // 這邊不提供 在 加購物列表中 點選 不能修改數量
            $data["restatus"] = "repeat";

            // 有，修改數量
            // $listid = $chk['cartlist_id'];
            // $oldqty = $chk['qty']+$post['addqty'];

            // $newData = array(
            //     'qty' => $oldqty
            // );

            // $condition = array( $keyid2 => $listid );
            // $edit = $db->update( $table2, $newData, $condition );

        }else{

            // 沒有，加入


            // 先依 id 取得商品資料
            $conditions['where'] = array( $keyid1 => $post['addpd'] );
            $conditions['return_type'] = 'single';
            $result = $db->getRows( $table1, $conditions );
            unset($conditions);


            // 寫入 cart
            if ( is_array($result) ) {

                if( !empty($result['addprice02'])  &&  $result['addprice02']>0 ){

                    $price = $result['addprice02'];

                }else if( $result['addprice01']>0 ) {

                    $price = $result['addprice01'];  

                }else{

                    return false;
                }
           
                $newData = array(
                    'session_id' => session_id(),
                    // 'mid' => $post['mid'],
                    'addproduct_amount_id' => $post['addpd'],
                    // 'pointtype' => $result['pointtype'],
                    'kind'  => 5,
                    'name'  => $result['name'],
                    'price' => $price,
                    'qty'   => $post['addqty'],
                    // 'pic'   => $result['pic01'],
                    'day' => date('Y-m-d H:i:s')
                );
                
                $add = $db->insert( $table2 , $newData );               
            }

            if( $add==false ){

                $data["restatus"] = "false";

            }else{

                $data["restatus"] = "ok";
            }

        }


        unset($chk);
        unset($result);
        unset($add);

        // 完成後 return OK, 回傳資料
        echo json_encode($data);
    }

}

if( $_POST['action'] == 'editextra_amount' ){

    $table1 = "c_addproduct_amount";
    $keyid1 = "addproduct_amount_id";

    $table2 = "c_cartlist";
    $keyid2 = "cartlist_id";


    if( isset($post['cno'])  &&  isset($post['qty']) ){

        // 確認 購物車中是否有同樣商品
        $conditions['search'] = array( 
            'session_id' => session_id()
        );

        $conditions['where'] = array( $keyid2 => $post['cno'] );
        $conditions['return_type'] = 'single';
        $chk = $db->getRows( $table2, $conditions );
        unset($conditions);

        // 有 ↓
        if( $chk!=false ){

            // 再次確認 加購品開啟 是可以購買狀況 ↓
            $conditions['where'] = array( 
                $keyid1 => $chk[$keyid1],
                'vis' => 1
            );
            // 比對資料 extraCalc() 是2維，所以這邊就算只撈單筆資料也是不可以用 'single'
            // $conditions['return_type'] = 'single';
            $conditions['select'] = ' addproduct_amount_id, needprice ';
            $extra = $db->getRows( $table1, $conditions );
            unset($conditions);


            $conditions['select'] = ' cartlist_id, product_id, kind, price, qty ';
            $conditions['search'] = array( 'session_id' => session_id() );
            $conditions['where'] = array( 'kind' => 1 );

            $cart = $db->getRows( $table2, $conditions );
            unset($conditions);


            // 比對 確認加購品條件 購買上限
            if( $extra!=false  &&  $cart!= false ){

                $result = extraCalc02( $extra, $cart, 0 );
            }

            $max = $result[0]['max'];


            // 可 ，修改數量 ↓
            if( $post['qty']<=$max ){

                $listid = $chk[$keyid2];
                $newqty = $post['qty'];

                $newData = array(
                    'qty' => $newqty
                );

                $condition = array( $keyid2 => $listid );
                $edit = $db->update( $table2, $newData, $condition );
                unset($conditions);
            
            }else if( $post['qty']>$max ){

                Msg_Alert01('錯誤: 超過上限。','../index.php');  
            }

            if( $max==0 ){

                // 刪除該項目
                $condition = array( $keyid2 => $listid );
                $del = $db->delete( $table2, $condition );
                unset($conditions);
            }


        // 沒有 無法修改
        }else{

            // 顯示錯誤
            Msg_Alert01('錯誤: 加購品(滿額)數量修改錯誤。','../index.php');
        }


        if( $edit==false ){

            $data["restatus"] = "false";

        }else{

            $data["restatus"] = "ok";
        }

        unset($chk);
        unset($extra);
        unset($cart);
        unset($result);
        unset($edit);

        // 完成後 return OK, 回傳資料
        echo json_encode($data);
    }

}

if( $_POST['action'] == 'chkextra_amount' ){


    $carttable = $thisdbpre."cartlist";
    $cartkeyid = "cartlist_id";


    // 取得 購物車 正品資料
    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 1,
        'c_product.vis'  => 1,
        'c_product.open' => 1,
    );

    $conditions['jointable'] = 'c_product';
    $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    $cart = $db->getRows( $carttable, $conditions );
    unset($conditions);

    // print_r($cart);
    // echo "<br><br><br>";

    // 更新 價錢
    for( $i=0; $i<count($cart); $i++ ){

        if( !empty($cart[$i]['price02'])  &&  $cart[$i]['price02']>0 ){

            $price[$i] = $cart[$i]['price02'];

        }else if( $cart[$i]['price01']>0 ) {

            $price[$i] = $cart[$i]['price01']; 

        }else{

            $price[$i] = 0;
        }

        $cart[$i]['price'] = $price[$i];

    }






    // 取得 購物車 加購品資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.addproduct_amount_id, c_cartlist.kind, c_cartlist.qty, c_addproduct_amount.needprice ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 'c_cartlist.kind' => 5 );
    // 比對資料 extraCalc() 是2維，所以這邊就算只撈單筆資料也是不可以用 'single'
    $conditions['jointable'] = 'c_addproduct_amount';
    $conditions['joinkey'] = 'c_addproduct_amount.addproduct_amount_id = c_cartlist.addproduct_amount_id';

    $extra = $db->getRows( $carttable, $conditions );
    unset($conditions);

    // print_r($extra);
    // echo "<br><br><br>";


    // 若購物車本就沒有加購品，就不需 修改or刪除 加購品
    if( $extra!=false ){

        $result = extraCalc02( $extra, $cart, 0 );
        // print_r($result);
        // echo "<br><br><br>";

        // for( $i=0; $i<count($result); $i++ ){

        //     // echo $result[$i][$cartkeyid]."<br>";
        //     // echo $result[$i]['qty']."<br>";
        //     // echo $result[$i]['max']."<br>";

        //     // qty > max  減到 max
        //     if( $result[$i]['qty'] > $result[$i]['max'] ){

        //         // echo "qty > max  減到 max";

        //         $newData = array(
        //             'qty' => $result[$i]['max']
        //         );

        //         $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
        //         $edit = $db->update( $carttable, $newData, $condition );

        //         if( $edit!=false ){

        //             $data["restatus"] = "ok"; 

        //         }else{

        //             $data["restatus"] = "false"; 
        //         }
        //     }

        //     // max = 0, 刪除該筆加購品
        //     if( $result[$i]['max']<=0 ){

        //         // echo "max = 0, 刪除該筆加購品";

        //         $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
        //         $del = $db->delete( $carttable, $condition );

        //         if( $del==true ){

        //             $data["restatus"] = "ok"; 

        //         }else{

        //             $data["restatus"] = "false"; 
        //         }
        //     }

        //     // qty <= max 不動
        //     if( $result[$i]['qty'] <= $result[$i]['max'] ){

        //         $data["restatus"] = "noact"; 
        //     }

        // }


        $re = upCartExtra02($result);
        
        // if( $re==false ){
        //     $data["restatus"] = "false"; 
        // }else{
            $data["restatus"] = "ok"; 
        // }        

    }else{

        $data["restatus"] = "noact";  
    }

    echo json_encode($data);

}



// 滿件
if( $_POST['action'] == 'getExtra' ){

    $table = $thisdbpre."addproduct";
    $keyid = "addproduct_id";


    // 取得購物車 正品資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.product_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_product.name, c_product.pic01, c_product.price01, c_product.price02 ';
   
    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 1,
        'c_product.vis' => 1,
        'c_product.open' => 1,
    );

    $conditions['jointable'] = 'c_product';
    $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    $cart = $db->getRows( 'c_cartlist', $conditions );
    unset($conditions);

    // 更新 價錢
    for( $i=0; $i<count($cart); $i++ ){

        if( !empty($cart[$i]['price02'])  &&  $cart[$i]['price02']>0 ){

            $price[$i] = $cart[$i]['price02'];

        }else if( $cart[$i]['price01']>0 ) {

            $price[$i] = $cart[$i]['price01']; 

        }else{

            $price[$i] = 0;
        }

        $cart[$i]['price'] = $price[$i];
    }



    // 先將 顯示的加購品資料撈出來
    $conditions['where'] = array( 'vis' => 1, 'open' => 1 );
    $conditions['order_by'] = ' sort DESC ';
    $conditions['select'] = ' addproduct_id, name, addprice01, addprice02, pic01, needqty, needbuy, open, closetxt ';
    $ary = $db->getRows( $table, $conditions );

    // print_r($ary);
    // echo "<br><br><br><br>";
    // print_r($cart);

    // 比對
    if( $ary!=false  &&  $cart!=false ){

        $result = extraCalc( $ary, $cart );
        $result = twoarychgkey( $result, $keyid, $thisdbid );
        // print_r($result);
        // echo "<br><br><br><br>";

        $sum = count($result);


        // 將比對回來的資料處理，max=0 未達條件，刪除不顯示
        for( $i=0; $i<$sum; $i++ ){

            // echo $i." : <br>";
            // print_r($result[$i]);
            // echo "<br><br><br>";

            if( $result[$i]['max']==0 ){

                // 刪除該筆資料
                unset($result[$i]);
            }
        }

        $result = array_values($result);

    }else{

        $result = false;
    }

 
    // print_r($result);
    // echo "<br><br><br>";


    if( $result==false ){

        $data["restatus"] = "false";
        $data["data"] = $cart;

    }else{

        $data["data"] = $result;
    }

    echo json_encode($data);

}

if( $_POST['action'] == 'addextra' ){

    $table1 = "c_addproduct";
    $keyid1 = "addproduct_id";

    $table2 = "c_cartlist";
    $keyid2 = "cartlist_id";


    if( isset($post['addpd'])  &&  isset($post['addqty']) ){

        // 判斷購物車中是否有同樣商品
        $conditions['search'] = array( 
            'session_id' => session_id()
        );

        $conditions['where'] = array( $keyid1 => $post['addpd'] );
        $conditions['return_type'] = 'single';
        $chk = $db->getRows( $table2, $conditions );
        unset($conditions);

        if( is_array($chk) ){

            // 這邊不提供 在 加購物列表中 點選 不能修改數量
            $data["restatus"] = "repeat";

            // 有，修改數量
            // $listid = $chk['cartlist_id'];
            // $oldqty = $chk['qty']+$post['addqty'];

            // $newData = array(
            //     'qty' => $oldqty
            // );

            // $condition = array( $keyid2 => $listid );
            // $edit = $db->update( $table2, $newData, $condition );

        }else{

            // 沒有，加入


            // 先依 id 取得商品資料
            $conditions['where'] = array( $keyid1 => $post['addpd'] );
            $conditions['return_type'] = 'single';
            $result = $db->getRows( $table1, $conditions );
            unset($conditions);


            // 寫入 cart
            if ( is_array($result) ) {

                if( !empty($result['addprice02'])  &&  $result['addprice02']>0 ){

                    $price = $result['addprice02'];

                }else if( $result['addprice01']>0 ) {

                    $price = $result['addprice01'];  

                }else{

                    return false;
                }
           
                $newData = array(
                    'session_id' => session_id(),
                    // 'mid' => $post['mid'],
                    'addproduct_id' => $post['addpd'],
                    // 'pointtype' => $result['pointtype'],
                    'kind'  => 2,
                    'name'  => $result['name'],
                    'price' => $price,
                    'qty'   => $post['addqty'],
                    // 'pic'   => $result['pic01'],
                    'day' => date('Y-m-d H:i:s')
                );
                
                $add = $db->insert( $table2 , $newData );               
            }

            if( $add==false ){

                $data["restatus"] = "false";

            }else{

                $data["restatus"] = "ok";
            }

        }


        unset($chk);
        unset($result);
        unset($add);

        // 完成後 return OK, 回傳資料
        echo json_encode($data);
    }

}

if( $_POST['action'] == 'editextra' ){

    $table1 = "c_addproduct";
    $keyid1 = "addproduct_id";

    $table2 = "c_cartlist";
    $keyid2 = "cartlist_id";


    if( isset($post['cno'])  &&  isset($post['qty']) ){

        // 確認 購物車中是否有同樣商品
        $conditions['search'] = array( 
            'session_id' => session_id()
        );

        $conditions['where'] = array( $keyid2 => $post['cno'] );
        $conditions['return_type'] = 'single';
        $chk = $db->getRows( $table2, $conditions );
        unset($conditions);

        // 有 ↓
        if( $chk!=false ){

            // 再次確認 加購品開啟 是可以購買狀況 ↓
            $conditions['where'] = array( 
                $keyid1 => $chk[$keyid1],
                'vis' => 1
            );
            // 比對資料 extraCalc() 是2維，所以這邊就算只撈單筆資料也是不可以用 'single'
            // $conditions['return_type'] = 'single';
            $conditions['select'] = ' addproduct_id, needqty, needbuy ';
            $extra = $db->getRows( $table1, $conditions );
            unset($conditions);


            $conditions['select'] = ' cartlist_id, product_id, kind, qty ';
            $conditions['search'] = array( 'session_id' => session_id() );
            $conditions['where'] = array( 'kind' => 1 );

            $cart = $db->getRows( $table2, $conditions );
            unset($conditions);


            // 比對 確認加購品條件 購買上限
            if( $extra!=false  &&  $cart!= false ){

                $result = extraCalc( $extra, $cart );
            }

            $max = $result[0]['max'];


            // 可 ，修改數量 ↓
            if( $post['qty']<=$max ){

                $listid = $chk[$keyid2];
                $newqty = $post['qty'];

                $newData = array(
                    'qty' => $newqty
                );

                $condition = array( $keyid2 => $listid );
                $edit = $db->update( $table2, $newData, $condition );
            
            }else if( $post['qty']>$max ){

                Msg_Alert01('錯誤: 超過上限。','../index.php');  
            }

            if( $max==0 ){

                // 刪除該項目
                $condition = array( $keyid2 => $listid );
                $del = $db->delete( $table2, $condition );
                unset($conditions);
            }


        // 沒有 無法修改
        }else{

            // 顯示錯誤
            Msg_Alert01('錯誤: 加購品(滿件)數量修改錯誤。','../index.php');
        }


        if( $edit==false ){

            $data["restatus"] = "false";

        }else{

            $data["restatus"] = "ok";
        }

        unset($chk);
        unset($extra);
        unset($cart);
        unset($result);
        unset($edit);

        // 完成後 return OK, 回傳資料
        echo json_encode($data);
    }

}

if( $_POST['action'] == 'chkextra' ){


    $carttable = $thisdbpre."cartlist";
    $cartkeyid = "cartlist_id";


    // 取得 購物車 正品資料
    $conditions['search'] = array( 
        'session_id' => session_id()
    );

    $conditions['where'] = array( 
        'c_cartlist.kind' => 1,
        'c_product.vis' => 1,
        'c_product.open' => 1,
    );

    $conditions['jointable'] = 'c_product';
    $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    $cart = $db->getRows( $carttable, $conditions );
    unset($conditions);

    // print_r($cart);
    // echo "<br><br><br>";

    // 更新 價錢
    for( $i=0; $i<count($cart); $i++ ){

        if( !empty($cart[$i]['price02'])  &&  $cart[$i]['price02']>0 ){

            $price[$i] = $cart[$i]['price02'];

        }else if( $cart[$i]['price01']>0 ) {

            $price[$i] = $cart[$i]['price01']; 

        }else{

            $price[$i] = 0;
        }

        $cart[$i]['price'] = $price[$i];
    }




    // 取得 購物車 加購品資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.addproduct_id, c_cartlist.kind, c_cartlist.qty, c_addproduct.needqty, c_addproduct.needbuy ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 'c_cartlist.kind' => 2 );
    // 比對資料 extraCalc() 是2維，所以這邊就算只撈單筆資料也是不可以用 'single'
    $conditions['jointable'] = 'c_addproduct';
    $conditions['joinkey'] = 'c_addproduct.addproduct_id = c_cartlist.addproduct_id';

    $extra = $db->getRows( $carttable, $conditions );
    unset($conditions);

    // print_r($extra);
    // echo "<br><br><br>";


    // 若購物車本就沒有加購品，就不需 修改or刪除 加購品
    if( $extra!=false ){

        $result = extraCalc( $extra, $cart );
        // print_r($result);
        // echo "<br><br><br>";

        // for( $i=0; $i<count($result); $i++ ){

        //     // echo $result[$i][$cartkeyid]."<br>";
        //     // echo $result[$i]['qty']."<br>";
        //     // echo $result[$i]['max']."<br>";

        //     // qty > max  減到 max
        //     if( $result[$i]['qty'] > $result[$i]['max'] ){

        //         // echo "qty > max  減到 max";

        //         $result[$i]['qty'] = $result[$i]['max'];

        //         $newData = array(
        //             'qty' => $result[$i]['max']
        //         );

        //         $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
        //         $edit = $db->update( $carttable, $newData, $condition );

        //         if( $edit!=false ){

        //             $data["restatus"] = "ok"; 

        //         }else{

        //             $data["restatus"] = "false"; 
        //         }
        //     }


        //     // max = 0, 刪除該筆加購品
        //     if( $result[$i]['max']<=0 ){

        //         // echo "max = 0, 刪除該筆加購品";

        //         $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
        //         $del = $db->delete( $carttable, $condition );

        //         if( $del==true ){

        //             $data["restatus"] = "ok"; 

        //         }else{

        //             $data["restatus"] = "false"; 
        //         }
        //     }


        //     // qty <= max 不動
        //     if( $result[$i]['qty'] <= $result[$i]['max'] ){

        //         $data["restatus"] = "noact"; 
        //     }

        // }

        $re = upCartExtra($result);
        

        if( $re==false ){
            $data["restatus"] = "false"; 
        }else{
            $data["restatus"] = "ok"; 
        }

    }else{

        $data["restatus"] = "noact";  
    }


    echo json_encode($data);

}



function upCartExtra($result) {

    $db = new DB();
    $carttable = "c_cartlist";
    $cartkeyid = "cartlist_id";

    for( $i=0; $i<count($result); $i++ ){

        // echo $result[$i][$cartkeyid]."<br>";
        // echo $result[$i]['qty']."<br>";
        // echo $result[$i]['max']."<br>";

        // qty > max  減到 max
        if( $result[$i]['qty'] > $result[$i]['max'] ){

            // echo "qty > max  減到 max";

            // 先修改 db 資料
            $newData = array(
                'qty' => $result[$i]['max']
            );

            $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
            $edit = $db->update( $carttable, $newData, $condition );
            unset($conditions);

            // 再修改 陣列資料
            $result[$i]['qty'] = $result[$i]['max'];

        }


        // max = 0, 刪除該筆加購品
        if( $result[$i]['max']<=0  ||  $result[$i]['qty']==0  ){

            // echo "max = 0, 刪除該筆加購品";

            // 先刪除 db 資料
            $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
            $del = $db->delete( $carttable, $condition );
            unset($conditions);

            // 再刪除 陣列回傳資料，(若先刪除陣列資料後，就沒有id可對應cartlist id刪除)
            unset($result[$i]);

        }


        // qty <= max 不動
        if( $result[$i]['qty'] <= $result[$i]['max'] ){

            // $data["restatus"] = "noact"; 
        }

    }


    return $result;
}


function upCartExtra02($result) { 

    for( $i=0; $i<count($result); $i++ ){

        // echo $result[$i][$cartkeyid]."<br>";
        // echo $result[$i]['qty']."<br>";
        // echo $result[$i]['max']."<br>";

        // qty > max  減到 max
        if( $result[$i]['qty'] > $result[$i]['max'] ){

            // echo "qty > max  減到 max";

            $newData = array(
                'qty' => $result[$i]['max']
            );

            $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
            $edit = $db->update( $carttable, $newData, $condition );
            unset($conditions);

            // 再修改 陣列資料
            $result[$i]['qty'] = $result[$i]['max'];

            // if( $edit!=false ){

            //     $data["restatus"] = "ok"; 

            // }else{

            //     $data["restatus"] = "false"; 
            // }
        }

        // max = 0, 刪除該筆加購品
        if( $result[$i]['max']<=0 ){

            // echo "max = 0, 刪除該筆加購品";

            $condition = array( $cartkeyid => $result[$i][$cartkeyid] );
            $del = $db->delete( $carttable, $condition );

            // 再刪除 陣列回傳資料，(若先刪除陣列資料後，就沒有id可對應cartlist id刪除)
            unset($result[$i]);
        }

        // qty <= max 不動
        if( $result[$i]['qty'] <= $result[$i]['max'] ){

            // $data["restatus"] = "noact"; 
        }

    }
    
}



function extraCalc($extra, $cart) {


    $db = new DB();
    $table = "c_cartlist";

    // 需比對現在購物車中是否有此加購品
    // 取出購物車 加購品
    $conditions['select'] = ' cartlist_id, session_id, addproduct_id, kind ';
    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 'kind' => 2 );

    $addcart = $db->getRows( $table, $conditions );
    unset($conditions);


    // 比對
    for( $i=0; $i<count($extra); $i++ ){

        $yesqty[$i] = 0;
        $extra[$i]['max'] = 0;
        $extra[$i]['dis'] = '';

        // echo count($cart)."<br>";

        for( $j=0; $j<count($cart); $j++ ){
            
            $sch = ",".$cart[$j]['product_id'].",";
            $schresult = strstr( $extra[$i]['needbuy'], $sch);

            if( $schresult!=false ){

                // echo "位置: ".$schresult."<br>"; 
   
                $yesqty[$i] = $yesqty[$i]+$cart[$j]['qty'];
             
            }else{

                // echo "null"."<br>"; 
            }

            // if( $extra[$i]['addproduct_id'] == $cart[$j]['addproduct_id'] ){

            //     $extra[$i]['dis'] = 'dis';
            // }
        }



        for( $k=0; $k<count($addcart); $k++ ){
            
            if( $extra[$i]['addproduct_id'] == $addcart[$k]['addproduct_id'] ){

                $extra[$i]['dis'] = 'dis';
            }
        }
        
        // echo "數量: ".$yesqty[$i]."<br>";
 
        $extra[$i]['max'] = floor( $yesqty[$i] / $extra[$i]['needqty'] );

    }


    // echo "<br><br><br>";
    // print_r($yesqty);
    // print_r($extra);

    // 將沒有達到條件的 (=0) 的資料刪除
    // for( $i=0; $i<count($yesqty); $i++ ){

    //     if( $yesqty[$i]==0  OR  $yesqty[$i]< $extra[$i]['needqty']  ){

    //         unset($extra[$i]);
    //     }
    // }


    // $extra = array_values($extra);
    // print_r($extra);

    return $extra;

}

function extraCalc02($extra, $cart, $tempsub ) {

    $db = new DB();
    $table = "c_cartlist";


    if( empty($tempsub) ||  $tempsub==0 ){

        // 先計算 目前購物總金額
        $sum = count($cart);

        for( $i=0; $i<$sum; $i++ ){

            $tempsub += $cart[$i]['price']*$cart[$i]['qty'];
            // echo $tempsub;
        }
    }



    // 需比對現在購物車中是否有此加購品
    // 取出購物車 加購品
    $conditions['select'] = ' cartlist_id, session_id, addproduct_amount_id, kind ';
    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 'kind' => 5 );

    $addcart = $db->getRows( $table, $conditions );
    unset($conditions);


    // 比對
    for( $i=0; $i<count($extra); $i++ ){

        $extra[$i]['max'] = 0;
        $extra[$i]['dis'] = '';

        // echo count($cart)."<br>";

        for( $j=0; $j<count($addcart); $j++ ){
            
            if( $extra[$i]['addproduct_amount_id'] == $addcart[$j]['addproduct_amount_id'] ){

                $extra[$i]['dis'] = 'dis';
            }
        }
        
        // echo "數量: ".$yesqty[$i]."<br>";
 
        $extra[$i]['max'] = floor( $tempsub / $extra[$i]['needprice'] );

    }

    // echo "<br><br><br>";
    // print_r($extra);

    return $extra;

}



function up_giveaway($cart){

    $db = new DB();

    $table = "c_giveaway";
    $keyid = "giveaway_id";

    $qid = 7;
    $mid = 6;

    $carttable = "c_cartlist";
    $cartkeyid = "cartlist_id";


    // 取得購物車 正品資料
    // $conditions['search'] = array( 
    //     'session_id' => session_id()
    // );
    // $conditions['where'] = array( 
    //     'c_cartlist.kind' => 1,
    //     'c_product.vis'  => 1,
    //     'c_product.open' => 1,
    // );

    // $conditions['jointable'] = 'c_product';
    // $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    // $cart = $db->getRows( $carttable, $conditions );
    // unset($conditions);

    

    // 購物車有正品資料，增修贈品
    if( $cart!=false ){

        // 更新 價錢、點數
        // for($i = 0;$i<count($cart1);$i++){

        //     if( !empty($cart1[$i]['price02'])  &&  $cart1[$i]['price02']>0 ){

        //         $price[$i] = $cart1[$i]['price02'];

        //     }else if( $cart1[$i]['price01']>0 ) {

        //         $price[$i] = $cart1[$i]['price01']; 

        //     }else{

        //         $price[$i] = 0;
        //     }

        //     $point[$i] = pointCalc($cart1[$i]);

        //     $cart1[$i]['price'] = $price[$i];
        //     $cart1[$i]['point'] = $point[$i];
        // }


        // 取得滿件 贈品條件 -----------------------------------------

            $conditions['where'] = array( $keyid => $qid );
            $conditions['return_type'] = 'single';

            $gift_q = $db->getRows( $table, $conditions );
            unset($conditions);

            // print_r($gift_q);
            // echo "<br><br><br><br>";

            $result_q = giveawayCalc( $gift_q, $cart );

            // print_r($result_q);
            // echo "<br><br><br><br>";


            // 確認購物車中是否有滿件贈品資料
            $conditions['search'] = array( 
                'session_id' => session_id()
            );
            $conditions['where'] = array( 
                'kind' => 4,
                'giveaway_id' => $qid
            );
            $conditions['return_type'] = 'single';

            $chk = $db->getRows( $carttable, $conditions );
            unset($conditions);


            // 達到滿件 寫入或修改 贈品數量
            if( $result_q['open']==1  &&  $result_q['reached_q']>=1 ){

                if( $chk == false ){

                    // 沒有，可寫入
                    $newData = array(
                        'session_id' => session_id(),
                        $keyid => $qid,
                        'kind' => 4,
                        'name' => $result_q['name'],
                        'qty' => $result_q['reached_q'],
                        // 'pic' => $result_q['pic'],
                        'day' => date('Y-m-d H:i:s')
                    );

                    $add = $db->insert( $carttable , $newData );

                    if( $add!=false ){

                        $data["restatus"] = "ok"; 

                    }

                }else{

                    // 有，並且數量不同 >> 修改數量
                    // print_r($chk['qty']);

                    if( $chk['qty']!=$result_q['reached_q'] ){

                        $newData = array(
                            'qty' => $result_q['reached_q'],
                        );

                        $condition = array( $cartkeyid => $chk[$cartkeyid] );
                        $edit = $db->update( $carttable, $newData, $condition );
                        unset($condition);
                        
                        if( $edit!=false ){

                            $data["restatus"] = "ok"; 
                        }

                    }else{

                        $data["restatus"] = "noact"; 
                    }

                }


            // 未達滿件，刪除 贈品數量
            }else{

                if( $chk != false ){

                    $condition = array( $cartkeyid => $chk[$cartkeyid] );
                    $del = $db->delete( $carttable, $condition );
                    unset($condition);

                    if( $del==true ){

                        $data["restatus"] = "ok";

                    // }else{

                    //     $data["restatus"] = "false"; 
                    }
                

                }else{

                    $data["restatus"] = "noact"; 
                }
            }


            


        // 取得滿額 贈品條件 -----------------------------------------

            $conditions['where'] = array( $keyid => $mid );
            $conditions['return_type'] = 'single';

            $gift_m = $db->getRows( $table, $conditions );
            unset($conditions);
            // print_r($gift_m);
            // echo "<br><br><br><br>";

            $result_m = giveawayCalc( $gift_m, $cart );
            // print_r($result_m);
            // echo "<br><br><br><br>";


            // 確認購物車中是否有滿額贈品資料
            $conditions['search'] = array( 
                'session_id' => session_id()
            );
            $conditions['where'] = array( 
                'kind' => 4,
                'giveaway_id' => $mid
            );
            $conditions['return_type'] = 'single';

            $chk = $db->getRows( $carttable, $conditions );
            unset($conditions);


            // 達到滿額 寫入或修改 贈品數量
            if( $result_m['open']==1  &&  $result_m['reached_m']>=1 ){

                if( $chk == false ){

                    // 沒有，可寫入
                    $newData = array(
                        'session_id' => session_id(),
                        $keyid => $mid,
                        'kind' => 4,
                        'name' => $result_m['name'],
                        'qty' => $result_m['reached_m'],
                        // 'pic' => $result_m['pic'],
                        'day' => date('Y-m-d H:i:s')
                    );

                    $add = $db->insert( $carttable , $newData );

                    if( $add!=false ){

                        $data["restatus"] = "ok"; 

                    // }else{

                    //     $data["restatus"] = "false"; 
                    }

                }else{

                    // 有 >> 修改數量
                    // print_r($chk);

                    if( $chk['qty']!=$result_m['reached_m'] ){

                        $newData = array(
                            'qty' => $result_m['reached_m'],
                        );

                        $condition = array( $cartkeyid => $chk[$cartkeyid] );
                        $edit = $db->update( $carttable, $newData, $condition );  

                        if( $edit!=false ){

                            $data["restatus"] = "ok"; 

                        // }else{

                        //     $data["restatus"] = "false"; 
                        }

                    }else{

                        $data["restatus"] = "noact"; 
                    }
                  
                }


            // 未達滿額，刪除 贈品數量
            }else{

                if( $chk != false ){

                    $condition = array( $cartkeyid => $chk[$cartkeyid] );
                    $del = $db->delete( $carttable, $condition );
                    unset($condition); 

                    if( $del==true ){

                        $data["restatus"] = "ok";
                    // }else{

                    //     $data["restatus"] = "false"; 
                    }

                    
                }else{

                    $data["restatus"] = "noact"; 
                }

            }

    }

}


if( $_POST['action'] == 'chkgiveaway' ){

    $carttable = $thisdbpre."cartlist";
    $cartkeyid = "cartlist_id";


    // 取得購物車 正品資料
    $conditions['select'] = ' c_cartlist.cartlist_id, c_cartlist.product_id, c_cartlist.kind, c_cartlist.price, c_cartlist.qty, c_cartlist.point, c_product.pointtype, c_product.name, c_product.pic01, c_product.price01, c_product.price02, c_product.extrapoint ';

    $conditions['search'] = array( 
        'session_id' => session_id()
    );
    $conditions['where'] = array( 
        'c_cartlist.kind' => 1,
        'c_product.vis'  => 1,
        'c_product.open' => 1,
    );

    $conditions['jointable'] = 'c_product';
    $conditions['joinkey'] = 'c_product.product_id = c_cartlist.product_id';

    $cart = $db->getRows( $carttable, $conditions );
    unset($conditions);



    // 購物車有正品資料，增修贈品
    if( $cart!=false ){

        $table = $thisdbpre."giveaway";
        $keyid = "giveaway_id";
        $qid = 7;
        $mid = 6;

        // 取得滿件 贈品條件 -----------------------------------------

            $conditions['where'] = array( $keyid => $qid );
            $conditions['return_type'] = 'single';

            $gift_q = $db->getRows( $table, $conditions );
            unset($conditions);

            // print_r($gift_q);
            // echo "<br><br><br><br>";

            $result_q = giveawayCalc( $gift_q, $cart );

            // print_r($result_q);
            // echo "<br><br><br><br>";


            // 確認購物車中是否有滿額贈品資料
            $conditions['search'] = array( 
                'session_id' => session_id()
            );
            $conditions['where'] = array( 
                'kind' => 4,
                'giveaway_id' => $qid
            );
            $conditions['return_type'] = 'single';

            $chk = $db->getRows( $carttable, $conditions );
            unset($conditions);


            // 達到滿件 寫入或修改 贈品數量
            if( $result_q['open']==1  &&  $result_q['reached_q']>=1 ){

                if( $chk == false ){

                    // 沒有，可寫入
                    $newData = array(
                        'session_id' => session_id(),
                        $keyid => $qid,
                        'kind' => 4,
                        'name' => $result_q['name'],
                        'qty' => $result_q['reached_q'],
                        // 'pic' => $result_q['pic'],
                        'day' => date('Y-m-d H:i:s')
                    );

                    $add = $db->insert( $carttable , $newData );

                    if( $add!=false ){

                        $data["restatus"] = "ok"; 

                    }

                }else{

                    // 有，並且數量不同 >> 修改數量
                    // print_r($chk['qty']);

                    if( $chk['qty']!=$result_q['reached_q'] ){

                        $newData = array(
                            'qty' => $result_q['reached_q'],
                        );

                        $condition = array( $cartkeyid => $chk[$cartkeyid] );
                        $edit = $db->update( $carttable, $newData, $condition );

                        if( $edit!=false ){

                            $data["restatus"] = "ok"; 
                        }

                    }else{

                        $data["restatus"] = "noact"; 
                    }

                }


            // 未達滿件，刪除 贈品數量
            }else{

                if( $chk != false ){

                    $condition = array( $cartkeyid => $chk[$cartkeyid] );
                    $del = $db->delete( $carttable, $condition );

                    if( $del==true ){

                        $data["restatus"] = "ok";

                    // }else{

                    //     $data["restatus"] = "false"; 
                    }
                
                    unset($del);

                }else{

                    $data["restatus"] = "noact"; 
                }
            }


            


        // 取得滿額 贈品條件 -----------------------------------------

            $conditions['where'] = array( $keyid => $mid );
            $conditions['return_type'] = 'single';

            $gift_m = $db->getRows( $table, $conditions );
            unset($conditions);
            // print_r($gift_m);
            // echo "<br><br><br><br>";

            $result_m = giveawayCalc( $gift_m, $cart );
            // print_r($result_m);
            // echo "<br><br><br><br>";


            // 確認購物車中是否有滿額贈品資料
            $conditions['search'] = array( 
                'session_id' => session_id()
            );
            $conditions['where'] = array( 
                'kind' => 4,
                'giveaway_id' => $mid
            );
            $conditions['return_type'] = 'single';

            $chk = $db->getRows( $carttable, $conditions );
            unset($conditions);


            // 達到滿額 寫入或修改 贈品數量
            if( $result_m['open']==1  &&  $result_m['reached_m']>=1 ){

                if( $chk == false ){

                    // 沒有，可寫入
                    $newData = array(
                        'session_id' => session_id(),
                        $keyid => $mid,
                        'kind' => 4,
                        'name' => $result_m['name'],
                        'qty' => $result_m['reached_m'],
                        // 'pic' => $result_m['pic'],
                        'day' => date('Y-m-d H:i:s')
                    );

                    $add = $db->insert( $carttable , $newData );

                    if( $add!=false ){

                        $data["restatus"] = "ok"; 

                    // }else{

                    //     $data["restatus"] = "false"; 
                    }

                }else{

                    // 有 >> 修改數量
                    // print_r($chk);

                    if( $chk['qty']!=$result_m['reached_m'] ){

                        $newData = array(
                            'qty' => $result_m['reached_m'],
                        );

                        $condition = array( $cartkeyid => $chk[$cartkeyid] );
                        $edit = $db->update( $carttable, $newData, $condition );  

                        if( $edit!=false ){

                            $data["restatus"] = "ok"; 

                        // }else{

                        //     $data["restatus"] = "false"; 
                        }

                    }else{

                        $data["restatus"] = "noact"; 
                    }
                  
                }


            // 未達滿額，刪除 贈品數量
            }else{

                if( $chk != false ){

                    $condition = array( $cartkeyid => $chk[$cartkeyid] );
                    $del = $db->delete( $carttable, $condition );

                    if( $del==true ){

                        $data["restatus"] = "ok";
                    // }else{

                    //     $data["restatus"] = "false"; 
                    }

                    unset($del); 

                }else{

                    $data["restatus"] = "noact"; 
                }

            }



    // 無購物品，刪除所有贈品
    // }else{

    //     $data["restatus"] = "nocart";  

    //     // $conditions['other_sql'] = ' WHERE `session_id` LIKE '.session_id().' AND `kind` = 4 ';

    //     $condition = array( 'session_id' => session_id() );

    //     $del = $db->delete( $carttable, $condition );

    //     // $data["restatus"] = $del; 

    }

    unset($chk);
    unset($add);
    unset($edit);

    // 完成後 return OK, 回傳資料
    echo json_encode($data);

}


function giveawayCalc($gift, $cart) {

    $yesqty['full_q'] = 0;
    $yesqty['full_m'] = 0;

    $gift['reached_q'] = 0;
    $gift['reached_m'] = 0;


    // 滿件
    if( $gift['kind']==1  &&  $gift['open']==1 ){

        for( $j=0; $j<count($cart); $j++ ){

            // echo $cart[$j]['product_id']."<br>";

            if( $cart[$j]['product_id']!='' ){

                $sch = ",".$cart[$j]['product_id'].",";
                $schresult = strstr( $gift['needbuy'], $sch);

                // echo "位置: ".$schresult."<br>"; 

                if( $schresult!=false ){

                    $yesqty['full_q'] = $yesqty['full_q']+$cart[$j]['qty'];
                 
                }else{

                    // echo "null"."<br>"; 
                }
            }
        }

        $gift['reached_q'] = floor( $yesqty['full_q'] / $gift['fullqty'] );

        // echo "設定條件商品數量: ".$yesqty['full_q']."<br>";
        // echo "贈送數量: ".$gift['reached_q']."<br><br><br>";

    }
    



    // 滿額
    if( $gift['kind']==2  &&  $gift['open']==1 ){

        for( $j=0; $j<count($cart); $j++ ){

            // echo $cart[$j]['product_id']."<br>";

            if( $cart[$j]['product_id']!='' ){

                $sch = ",".$cart[$j]['product_id'].",";
                $schresult = strstr( $gift['needbuy'], $sch);

                // echo "位置: ".$schresult."<br>"; 

                if( $schresult!=false ){

                    $yesqty['full_m'] = $yesqty['full_m']+$cart[$j]['qty'];

                    $sub = $cart[$j]['price']*$cart[$j]['qty'];
                    $total += $sub; 
                 
                }else{

                    // echo "null"."<br>"; 
                }

            }
        }

        // echo "目前購物總額: ".$total."<br>"; 

        if( $total>=$gift['fullprice'] ){

            $gift['reached_m'] = floor( $total / $gift['fullprice'] );
        }

    }


    // echo "<br><br><br>";
    // print_r($gift);
    // $gift = array_values($gift);
    // print_r($gift);

    return $gift;

}





    // $table = "c_addproduct";
    // $keyid = "addproduct_id";

    // $cart[0]['product_id'] = "7";
    // $cart[0]['qty'] = "1";

    // $cart[1]['product_id'] = "12";
    // $cart[1]['qty'] = "2";

    // $cart[2]['addproduct_id'] = "14";
    // $cart[2]['qty'] = "3";


    // // 先將 加購品資料撈出來
    // $conditions['where'] = array( 
    //     'vis' => 1
    // );

    // $conditions['select'] = " addproduct_id, name, addprice01, addprice02, pic01, needqty, needbuy, open, closetxt ";

    // $result = $db->getRows( $table, $conditions );
    // print_r($result);
    // echo "<br><br><br>";


    // // 在計算目前購物車中產品，比對條件數量
    // for( $i=0; $i<count($result); $i++ ){

    //     $yesqty[$i] = 0;

    //     echo "第".$i."筆: ".$result[$i]['needbuy']."<br>";

    //     for( $j=0; $j<count($cart); $j++ ){
            
    //         $sch = ",".$cart[$j]['product_id'].",";
    //         $schresult = strstr( $result[$i]['needbuy'], $sch);
     

    //         if( $schresult!=false ){

    //             echo "位置: ".$schresult."<br>"; 
   
    //             $yesqty[$i] = $yesqty[$i]+$cart[$j]['qty'];
             
    //         }else{

    //             // echo "null"."<br>"; 
    //         }

    //         if( $result[$i]['addproduct_id'] == $cart[$j]['addproduct_id'] ){

    //             $result[$i]['dis'] = 'dis';
    //         }
    //     }
        
    //     echo "數量: ".$yesqty[$i]."<br>";

    //     echo "是否在購物車中: ".$result[$i]['dis']."<br>";

       
    // }


    // echo "<br><br><br><br>";
    // // print_r($yesqty);
    // // print_r($result);

    // // 將沒有達到條件的 (=0) 的資料刪除
    // for( $i=0; $i<count($yesqty); $i++ ){

    //     if( $yesqty[$i]==0  OR  $yesqty[$i]< $result[$i]['needqty']  ){

    //         unset($result[$i]);
    //     }
    // }

    // $result = array_values($result);




    // echo "<br><br><br>";
    // print_r($result);





    // $qq = "72,29,68,7,12,3,71,69"; 
    // $sch = "12"; 
    // $con = explode($pan,$name);

    // echo strpos($qq, $sch); 



    // print_r($con);

    // 逐筆 計算資料中 相符的數量



    // $ary = explode(",", $nowbuy);

    // print_r($ary);
    // echo count($ary);

    // $othersql = " And ( ";

    // for( $i=0; $i<count($ary); $i++ ){

    //     $aaa = $aaa." needbuy Like '%".$ary[$i]."%'";

    //     if( $i != count($ary)-1 ){
    //         $aaa = $aaa."  OR  ";
    //     }
    // }

    // $othersql = $othersql.$aaa."  )"; 




    // $othersql = "  And ( needbuy Like '%50%'  OR  needbuy Like '%48%'  OR  needbuy Like '%47%'  ) ";


    // $conditions['where'] = array( 
    //     'vis' => 1
    // );

    // $conditions['other_sql'] = $othersql;


    // $result = $db->getRows( $table, $conditions );
    // // $result = twoarychgkey( $result, $keyid, $thisdbid );

    // print_r($result);





?>