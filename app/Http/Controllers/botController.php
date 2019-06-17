<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Redis;
use DateTime;
use DateTimezone;
use DB;


class botController extends Controller
{
    public function refresh_redis_user_list(){
        $select_query = "SELECT telegram_user_id FROM telegram_db.user_table WHERE status='active' AND verification_status='active'";
        $user_info = DB::select( DB::raw($select_query));
        $user_id_array = array();
        foreach($user_info as $user){
            $user_id_array[$user->telegram_user_id] = "true";
        }
        $user_id_json = json_encode($user_id_array);
        Redis::set('users', $user_id_json);
        
        return "user_list_refreshed";
    }
    
    public function insert_user(){
        $insert_query = "INSERT INTO telegram_db.user_table (telegram_user_id, email, phone, status, hr_id) VALUES ('test_user_id', 'test@test.com', '01710396902', 'active', '1095')";
        $insert = DB::insert( DB::raw($insert_query));
        
        return "insert done";
    }
    //
    public function checkBot(){
        Redis::set('inbox_count', '0');
        $id = Redis::get('inbox_count');

        //$id = $id+1;
        //$bot_key = $_ENV['BOT_NAME'];
        return $id;
    }


    public function check_redis_inbox(){
        
        $inbox_count = Redis::get('inbox_count');
        $last_update_id = Redis::get('last_update_id');

        $inbox = Redis::rpop('inbox');

        echo $inbox;
        for($i = 0;$i<$inbox_count; $i++){
            $inbox = Redis::rpop('inbox');


            echo $inbox;

        }
        echo "<br>";
        echo $inbox_count;
        echo "<br>";
        echo $last_update_id;
        echo "<br>";
        echo "Finish";

    }
    public function archive_db(){
        $inbox_count = Redis::get('inbox_count');
        $last_update_id = Redis::get('last_update_id');

        $insert_query = "INSERT INTO telegram_db.message_table (update_id,chat_id,fname,lname,message,reply,message_date,reply_date) 
                        VALUES";
        
        if($inbox_count >0){
            for($i = 0;$i<$inbox_count; $i++){
                $inbox = Redis::rpop('inbox');
    
                $obj_json = json_decode($inbox,true);
    
                $update_id = $obj_json['update_id'];
                $chat_id = $obj_json['chat_id'];
                $fname = addslashes($obj_json['fname']);
                $lname = addslashes($obj_json['lname']);
                $message = addslashes($obj_json['message']);
                $reply = addslashes($obj_json['reply']);
                $message_date = $obj_json['message_date'];
                $reply_date = $obj_json['reply_date'];
    
                $insert_query .= "('"
                                    .$update_id."'"
                                    .",'".$chat_id."'"
                                    .",'".$fname."'"
                                    .",'".$lname."'"
                                    .",'".$message."'"
                                    .",'".$reply."'"
                                    .",'".$message_date."'"
                                    .",'".$reply_date."'"
                                ."),";
    
    
            }
            $insert_query = trim($insert_query,",");
            DB::insert( DB::raw($insert_query));
     
            Redis::set('inbox_count', '0');
            $insert_last_update_id = "UPDATE telegram_db.track_update_table SET last_update_id = $last_update_id,created_at = now() 
                                    WHERE id=1";
            DB::update( DB::raw($insert_last_update_id));

            return "Successfully archived data from redis to mysql";
        }
        else{
            return "Inbox Was Empty";
        }
        
    }
    public function chatup(){
        //$this->refresh_redis_user_list();
        //-------------------Get Last Update ID------------------------------------------
        $last_update_id_temp = Redis::get('last_update_id');
        $last_update_id = $last_update_id_temp + 1;
        $bot_token = $_ENV['BOT_TOKEN'];
        //------------------ Get New Updates -------------------------------------------
        
        $get_update_json = file_get_contents("https://api.telegram.org/bot$bot_token/getUpdates?offset=$last_update_id");

        echo "URL:  ";
        echo "<br>";
        echo "https://api.telegram.org/bot$bot_token/getUpdates?offset=$last_update_id";
        echo "<br>";
        echo $get_update_json;
        echo "<br>";

        $msg_updates = json_decode($get_update_json,true);
        $msg_array = $msg_updates['result'];
        
        
        If(count($msg_array)>0){
            
            //------------------ Read Updates ----------------------------------------------
            foreach($msg_array as $msg ){
                //---------------  validate if user is Registered------------------------------- 
                $GLOBALS['user_valid_flag'] = "invalid";

                $user_list = Redis::get('users');
                $user_array = json_decode($user_list,true);

                $telegram_id = $msg['message']['from']['id'];

                //dd($telegram_id);

                if(isset($user_array[$telegram_id])){
                    $GLOBALS['user_valid_flag'] = "valid";    
                }

                // foreach($user_array as $user){
                //     if($telegram_id == $user){
                //         $GLOBALS['user_valid_flag'] = "valid";
                //     }
                // }



                if(isset($msg['update_id'])){
                    $update_id_temp = $msg['update_id'];    
                }
                else{
                    $update_id_temp = 0;
                }
                if(isset($msg['message']['chat']['id'])){
                    $chat_id_temp = $msg['message']['chat']['id'];
                }
                else{
                    $chat_id_temp = 0;
                }
                if(isset($msg['message']['from']['first_name'])){
                    $first_name_temp = $msg['message']['from']['first_name'];
                }else{
                    $first_name_temp = 'NA';
                }
                if(array_key_exists('last_name', $msg['message']['from'])){
                    $last_name_temp = $msg['message']['from']['last_name'];
                }
                else{
                    $last_name_temp = 'NA';
                }
                
                if(isset($msg['message']['text'])){
                    $message_temp = $msg['message']['text'];
                }else{
                    $message_temp = 'NA';
                }
                if(isset($msg['message']['date'])){
                    $date = $msg['message']['date'];
                    //date_default_timezone_set("Asia/Dhaka");
                    $dt = new DateTime("@$date",new DateTimezone('Asia/Dhaka'));  // convert UNIX timestamp to PHP DateTime
                    $date_temp = $dt->format('Y-m-d H:i:s');
                }else{
                    $date_temp = '0000-00-00 00:00:00';
                }
                
                
                //--------------- Prepare Reply -------------------------
                $reply = $this->prepare_reply($message_temp);
                // echo "Reply:<br>";
                // echo $reply;

                $reply_arr = explode(" ",$reply);

                if($reply_arr[0] == "TELEGRAM_CODE##"){
                    //save entry in user table
                    $verification_code = $reply_arr[1];
                    $data_string = $reply_arr[2];
                    $data_arr = $reply_arr = explode("||",$data_string);
                    $email = $data_arr[0];
                    $phone = $data_arr[1];
                    $status = $data_arr[2];
                    $hr_id = $data_arr[3];
                    $telegram_user_id = $msg['message']['from']['id'];

                    $insert_query = "INSERT INTO telegram_db.user_table (telegram_user_id, email, phone, status, hr_id,verification_code,verification_status) VALUES ('$telegram_user_id', '$email', '$phone', '$status', '$hr_id','$verification_code','pending')";
                    $insert = DB::insert( DB::raw($insert_query));

                    //send reply
                    $message_send_response = $this->sendMessage($chat_id_temp,"Please enter verification code sent in sms", $bot_token);
                    echo "<br>";
                    echo $message_send_response;
                    echo "<br>";
                }
                else if($reply_arr[0] == "VALIDATION_CODE:"){
                    $telegram_user_id = $msg['message']['from']['id'];
                    $validation_code = $reply_arr[1];
                    
                    $select_query = "SELECT verification_code FROM telegram_db.user_table WHERE telegram_user_id = '$telegram_user_id'";
                    $select_user = DB::select( DB::raw($select_query));

                    if(count($select_user)==0){
                        $message_send_response = $this->sendMessage($chat_id_temp,"Please register for this service. Use the following command REGISTER {YOUR HR ID} {YOUR MOBILE NUMBER}. Please provide the mobile number that is registered in HR for verification. Example: REGISTER 1095 01710396902", $bot_token);
                    }
                    else{
                        $db_verification_code = $select_user[0]->verification_code;
                        if($db_verification_code == $validation_code){
                            // USER VERIFIED.
                            $update_query = "UPDATE telegram_db.user_table SET verification_status = 'active' WHERE telegram_user_id = '$telegram_user_id'";
                            $update_user = DB::select( DB::raw($update_query));
                            $message_send_response = $this->sendMessage($chat_id_temp,"Congratulations. Successfully verified for SCL TELEGRAM BOT SERVICES. Please write HELP ME to get the list of commands", $bot_token);

                            $this->refresh_redis_user_list();
                        }
                        else{
                            // USER VERIFICATION FAILED
                            $message_send_response = $this->sendMessage($chat_id_temp,"WRONG VERIFICATION CODE", $bot_token);
                        }
                    }
                }
                else{
                    //-------------- Send Reply -----------------------------
                    $message_send_response = $this->sendMessage($chat_id_temp,$reply, $bot_token);
                    echo "<br>";
                    echo $message_send_response;
                    echo "<br>";
                }




                
                //-------------Insert Log------------------------

                $current_time = date("Y-m-d H:i:s");

                $message_obj = array();
                $message_obj['update_id'] = addslashes($update_id_temp);
                $message_obj['chat_id'] = addslashes($chat_id_temp);
                $message_obj['fname'] = addslashes($first_name_temp);
                $message_obj['lname'] = addslashes($last_name_temp);
                $message_obj['message'] = addslashes($message_temp);
                $message_obj['reply'] = addslashes($reply);
                $message_obj['message_date'] = addslashes($date_temp);
                $message_obj['reply_date'] = addslashes($current_time);

                $msg_json = json_encode($message_obj);
                
                Redis::lpush('inbox',$msg_json);                
                $inbox_count = Redis::get('inbox_count');
                $inbox_count += 1;
                Redis::set('inbox_count', $inbox_count);
                Redis::set('last_update_id', $update_id_temp);                
            }

            $inbox_count = Redis::get('inbox_count');
            $capacity = $_ENV['REDIS_INBOX_CAPACITY'];
            // if($inbox_count > $capacity){
            //     $this->archive_db();
            // }
        }
        else{
            echo "No new Message";
        }
    }
    //-------------------- Send Msg API -------------------------

    public function send_msg(Request $request){
        $req_body = json_decode($request->getContent(), true);

        $chat_id = $req_body["chat_id"];
        $msg = urldecode ($req_body["msg"]);
        //$token =  $_ENV['BOT_TOKEN'];
        $token = "603092158:AAFB6qrAxPLZt1xTcfOeb3GRTqQ0fYtVUVY";
        $this->sendMessage($chat_id, $msg, $token);
        //$this->sendMessage($chat_id, $chat_id, $token);
        //$this->sendMessage($chat_id, $token, $token);
        print_r($req_body);
        return "Success";
    }
    //------------------- Utility Functions ----------------------
    public function sendMessage($chatID, $messaggio, $token) {
        echo "<br>";
        echo "sending message to " . $chatID;

        // test keyboard make
        // $reply_keyboard_markup = array();
        
        // $keyboard = array();
        
        // $keyboard_button = array(); 
        // $keyboard_button["text"] = "Share Contact";
        // $keyboard_button["request_contact"] = true;

        // array_push($keyboard,$keyboard_button);
        // $reply_keyboard_markup["keyboard"] = $keyboard;
        // $keyboard_json = json_encode($reply_keyboard_markup);

        //

        $url = "https://api.telegram.org/bot" . $token . "/sendMessage?chat_id=" . $chatID;
        $url = $url . "&text=" . urlencode($messaggio);//. "&reply_markup=" .$keyboard_json;
        $ch = curl_init();
        $optArray = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true
        );
        curl_setopt_array($ch, $optArray);
        $result = curl_exec($ch);
        curl_close($ch);
    }

    public function get_http_response_code($url) {
        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
    }

    public function  validate_command_message($msg){
        $return_obj = array();

        $getMsgArray = explode(' ', $msg);
        $count = count($getMsgArray);

        if($count > 3){
            $return_obj["status"] = "invalid";
            $return_obj["msg"] = "Invalid command";
            return $return_obj;
        }else{
            if(strtoupper($msg) == "HELP ME"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "OUTAGE ALL"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "POWER ALL"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "IIG ALL"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "ICX ALL"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "ITC ALL"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "VITAL LINK DOWN"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "VITAL SITE DOWN"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "VITAL LINK OTHER"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            elseif(strtoupper($msg) == "VITAL SITE OTHER"){
                $return_obj["status"] = "valid";
                $return_obj["msg"] = "NA";
                return $return_obj;
            }
            else{
                if($count == 2 && strtoupper($getMsgArray[0]) == "EMP"){
                    $return_obj["status"] = "valid";
                    $return_obj["msg"] = "NA";
                    return $return_obj;
                }
                elseif($count == 3 && strtoupper($getMsgArray[0]) == "OUTAGE" && strtoupper($getMsgArray[1]) == "CLIENT"){
                    $return_obj["status"] = "valid";
                    $return_obj["msg"] = "NA";
                    return $return_obj;
                }
                elseif($count == 3 && strtoupper($getMsgArray[0]) == "OUTAGE" && strtoupper($getMsgArray[1]) == "DISTRICT"){
                    $return_obj["status"] = "valid";
                    $return_obj["msg"] = "NA";
                    return $return_obj;
                }
                elseif($count == 3 && strtoupper($getMsgArray[0]) == "OUTAGE" && strtoupper($getMsgArray[1]) == "STATUS"){
                    $return_obj["status"] = "valid";
                    $return_obj["msg"] = "NA";
                    return $return_obj;
                }
                elseif($count == 3 && strtoupper($getMsgArray[0]) == "TICKET" && strtoupper($getMsgArray[1]) == "STATUS" && preg_match('/^\d+$/', $getMsgArray[2])){
                    $return_obj["status"] = "valid";
                    $return_obj["msg"] = "NA";
                    return $return_obj;
                }
                if($count == 3 && strtoupper($getMsgArray[0]) == "REGISTER"){
                    $return_obj["status"] = "valid";
                    $return_obj["msg"] = "NA";
                    return $return_obj;
                }
                if($count == 1 && substr($getMsgArray[0], 0, 3) == "TBC"){
                    $return_obj["status"] = "valid";
                    $return_obj["msg"] = "NA";
                    return $return_obj;
                }
                else{
                    $return_obj["status"] = "invalid";
                    $return_obj["msg"] = "Invalid Command";
                    return $return_obj;
                }

            }

        }

    }

    public function prepare_reply($msg){
        
        
        set_time_limit (0);
        $validate_array = $this->validate_command_message($msg);
        if($validate_array["status"] == "invalid"){
            $text = "
                Invalid command. Please follow the below command formats.
                \n1) Help<space>Me (Example : Help Me)
                \nDetails: It will show you this command list
                \n2) Emp<space>EmpName (Example : Emp Ahnaf)
                \nDetails: It will give you information(Contact Number,Designation..etc) about the employee
                \nData Source: HR Database
                \n3) Outage<space>ALL (Example : Outage all)
                \nDetails: It will give you all current NodeDown
                \nData Source: UNMS
                \n4) Outage<space>DISTRICT<space>DistrictName (Example : Outage District Dhaka)
                \nDetails: It will give you all current NodeDown From the mentioned district
                \nData Source: UNMS
                \n5) Outage<space>CLIENT<space>ClientName (Example : Outage Client Robi)
                \nDetails: It will give you all current NodeDown of the mentioned client
                \nData Source: UNMS						  
                \n6) Outage<space>STATUS<space>SiteName (Example : Outage Status dhsab4)
                \nDetails: It will give you status of the mentioned Node
                \nData Source: UNMS
                \n7) Power<space>ALL (Example : Power All)
                \nDetails: It will give you list of open faults for power alarm 
                \nData Source: Phoenix
                \n8) IIG<space>ALL (Example : IIG ALL)
                \nDetails: It will give you list of open IIG faults 
                \nData Source: Phoenix
                \n9) ICX<space>ALL (Example : ICX ALL)
                \nDetails: It will give you list of open ICX faults 
                \nData Source: Phoenix
                \n10) ITC<space>ALL (Example : ITC ALL)
                \nDetails: It will give you list of open ITC faults 
                \nData Source: Phoenix
				\n11) TICKET<space>STATUS<space>ID (Example : ticket status 59759)
                \nDetails: It will give you a summary status of mentioned ticket 
                \nData Source: Phoenix
                \n12) VITAL<space>LINK<space>DOWN (Example : VITAL LINK DOWN)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category 'Link Down' 
                \nData Source: Phoenix
                \n13) VITAL<space>LINK<space>OTHER (Example : VITAL LINK OTHER)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category other than 'Link Down' 
                \nData Source: Phoenix
                \n14) VITAL<space>SITE<space>DOWN (Example : VITAL SITE DOWN)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category 'Site Down' 
                \nData Source: Phoenix
                \n15) VITAL<space>SITE<space>OTHER (Example : VITAL SITE OTHER)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category other than 'Site Down' 
                \nData Source: Phoenix
                ";
            
            return $text;
        }




        $getMsgArray = explode(' ', $msg);
        $count = count($getMsgArray);

        if(strtoupper($getMsgArray[0]) == 'REGISTER'){
            $hr_id = $getMsgArray[1];
            $phone = $getMsgArray[2];
            // Check if the user is already registered
            
            // Get User Info
             

            
            $get_user_api_url = "http://172.20.17.50/micro_service_api/public/api/get_user?hr_id=$hr_id";
            echo $get_user_api_url;
            
            $user_info_json = file_get_contents($get_user_api_url);

            echo "<br>JSON<br>".$user_info_json;

            $user_info = json_decode($user_info_json,true);
            
            $count = $user_info["count"];
            if($count > 1){
                return "Error Code: 2508. Please contact OSS. Your Chat ID is:";
            }else{
                $data = $user_info["user_list"];
                $user = $data[0];
                
                $email = $user["email"];
                $mobile_in_hr_db = $user["phone"];
                $status = $user["account_status"];

                echo "Phone match check:<br>";
                echo "user given number: ".$phone."<br>";
                echo "number in hr db: ".$mobile_in_hr_db."<br>";
                
                if($phone==$mobile_in_hr_db){
                    //////////////varified/////////////
                    
                    //->make varification code
                    $verification_code_number = $phone % $hr_id;
                    $verification_code = "TBC:".$verification_code_number;
                    
                    //-Create data string
                    $data_string = "$email||$mobile_in_hr_db||$status||$hr_id";

                    //Send SMS to user
                    $text = "Please use the following verification in telegram BOT-  ".$verification_code;
                    $text = urlencode($text);
                    $contact_number = urlencode($phone);
                    
                    $send_sms_api = "http://172.20.17.50/micro_service_api/public/api/send_sms?text=$text&phone=$contact_number";
                    $send_sms = file_get_contents($send_sms_api);
                    //return code 
                    return "TELEGRAM_CODE## $verification_code $data_string";
                }else{
                    //Not varified
                    return "Your provided mobile number did not match with SCL HR Database";
                }
            }

            
        }
        else if($count == 1 && substr($getMsgArray[0], 0, 3) == "TBC"){
            //Validation
            return "VALIDATION_CODE: $getMsgArray[0]";
        }
        else if($GLOBALS['user_valid_flag'] == "invalid"){
            return "You are not registered to use this service. Please register with the following command. REGISTER {HR ID} {MOBILE NUMBER}. EXAMPLE: REGISTER 1095 01710396902. Please provide mobile number which is registered in HR Database";
        }
        else if(strtoupper($getMsgArray[0]) == '/start'){
            $text = "Hi I am here to help you with information. Please use the following command patterns:\n
                \n1) Help<space>Me (Example : Help Me)
                \nDetails: It will show you this command list
                \n2) Emp<space>EmpName (Example : Emp Ahnaf)
                \nDetails: It will give you information(Contact Number,Designation..etc) about the employee
                \nData Source: HR Database
                \n3) Outage<space>ALL (Example : Outage all)
                \nDetails: It will give you all current NodeDown
                \nData Source: UNMS
                \n4) Outage<space>DISTRICT<space>DistrictName (Example : Outage District Dhaka)
                \nDetails: It will give you all current NodeDown From the mentioned district
                \nData Source: UNMS
                \n5) Outage<space>CLIENT<space>ClientName (Example : Outage Client Robi)
                \nDetails: It will give you all current NodeDown of the mentioned client
                \nData Source: UNMS						  
                \n6) Outage<space>STATUS<space>SiteName (Example : Outage Status dhsab4)
                \nDetails: It will give you status of the mentioned Node
                \nData Source: UNMS
                \n7) Power<space>ALL (Example : Power All)
                \nDetails: It will give you list of open faults for power alarm 
                \nData Source: Phoenix
                \n8) IIG<space>ALL (Example : IIG ALL)
                \nDetails: It will give you list of open IIG faults 
                \nData Source: Phoenix
                \n9) ICX<space>ALL (Example : ICX ALL)
                \nDetails: It will give you list of open ICX faults 
                \nData Source: Phoenix
                \n10) ITC<space>ALL (Example : ITC ALL)
                \nDetails: It will give you list of open ITC faults 
                \nData Source: Phoenix
				\n11) TICKET<space>STATUS<space>ID (Example : ticket status 59759)
                \nDetails: It will give you a summary status of mentioned ticket 
                \nData Source: Phoenix
                \n12) VITAL<space>LINK<space>DOWN (Example : VITAL LINK DOWN)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category 'Link Down' 
                \nData Source: Phoenix
                \n13) VITAL<space>LINK<space>OTHER (Example : VITAL LINK OTHER)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category other than 'Link Down' 
                \nData Source: Phoenix
                \n14) VITAL<space>SITE<space>DOWN (Example : VITAL SITE DOWN)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category 'Site Down' 
                \nData Source: Phoenix
                \n15) VITAL<space>SITE<space>OTHER (Example : VITAL SITE OTHER)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category other than 'Site Down' 
                \nData Source: Phoenix
                ";
                
                return $text;
        }

        if(strtoupper($getMsgArray[0]) == 'EMP'){	
			$url   = "http://103.15.245.78:8005/site_down_data/addressInfo.php?name=".$getMsgArray[1];

            $response_code = $this->get_http_response_code($url);            
            if($response_code != "200"){
                return "ERROR oCCURED";
            }else{
                $json = file_get_contents($url);
                $json_data = json_decode($json, true);
                $text = "";
                for($i=0;$i<count($json_data);$i++){
                    $text .= '# '.$json_data[$i][0]."\n".'Email: '.$json_data[$i][1]."\n".'Phone : '.$json_data[$i][2]."\n".'Designation : '.$json_data[$i][3]."\n".'Department: '.$json_data[$i][4]."\n"."\n"."\n";
                }
                if($text == ''){
                    $text = 'No person found';
                }
                $text = trim($text,',');
                
                return $text;
            
            
            }



			
        }
        
        else if(strtoupper($getMsgArray[0]) == 'HELP'){
			if(strtoupper($getMsgArray[1]) == 'ME'){
				
				$text = "
                \n1) Help<space>Me (Example : Help Me)
                \nDetails: It will show you this command list
                \n2) Emp<space>EmpName (Example : Emp Ahnaf)
                \nDetails: It will give you information(Contact Number,Designation..etc) about the employee
                \nData Source: HR Database
                \n3) Outage<space>ALL (Example : Outage all)
                \nDetails: It will give you all current NodeDown
                \nData Source: UNMS
                \n4) Outage<space>DISTRICT<space>DistrictName (Example : Outage District Dhaka)
                \nDetails: It will give you all current NodeDown From the mentioned district
                \nData Source: UNMS
                \n5) Outage<space>CLIENT<space>ClientName (Example : Outage Client Robi)
                \nDetails: It will give you all current NodeDown of the mentioned client
                \nData Source: UNMS						  
                \n6) Outage<space>STATUS<space>SiteName (Example : Outage Status dhsab4)
                \nDetails: It will give you status of the mentioned Node
                \nData Source: UNMS
                \n7) Power<space>ALL (Example : Power All)
                \nDetails: It will give you list of open faults for power alarm 
                \nData Source: Phoenix
                \n8) IIG<space>ALL (Example : IIG ALL)
                \nDetails: It will give you list of open IIG faults 
                \nData Source: Phoenix
                \n9) ICX<space>ALL (Example : ICX ALL)
                \nDetails: It will give you list of open ICX faults 
                \nData Source: Phoenix
                \n10) ITC<space>ALL (Example : ITC ALL)
                \nDetails: It will give you list of open ITC faults 
                \nData Source: Phoenix
				\n11) TICKET<space>STATUS<space>ID (Example : ticket status 59759)
                \nDetails: It will give you a summary status of mentioned ticket 
                \nData Source: Phoenix
                \n12) VITAL<space>LINK<space>DOWN (Example : VITAL LINK DOWN)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category 'Link Down' 
                \nData Source: Phoenix
                \n13) VITAL<space>LINK<space>OTHER (Example : VITAL LINK OTHER)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category other than 'Link Down' 
                \nData Source: Phoenix
                \n14) VITAL<space>SITE<space>DOWN (Example : VITAL SITE DOWN)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category 'Site Down' 
                \nData Source: Phoenix
                \n15) VITAL<space>SITE<space>OTHER (Example : VITAL SITE OTHER)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category other than 'Site Down' 
                \nData Source: Phoenix
                ";
                
                return $text;
			}
        }
        
        else if(strtoupper($getMsgArray[0]) == 'OUTAGE'){

			if(strtoupper($getMsgArray[1]) == 'DISTRICT'){
				// echo "district function invade";
				// echo "<br>";

				$district = $getMsgArray[2];
				$district = trim($district," ");

				// echo $district;
				// echo "<br>";

				$url = "http://localhost:8980/unms_api/public/outage_district?district=".$district;

                $response_code = $this->get_http_response_code($url);            
                if($response_code != "200"){
                    return "ERROR oCCURED";
                }else{
                    // echo $url;
                    // echo "<br>";

                    $api_response = file_get_contents($url);

                    // echo $api_response;
                    // echo "<br>";

                    $json_arr = json_decode($api_response,true);

                    // print_r($json_arr);
                    // echo "<br>";

                    if($json_arr['api_response'] == 'OK'){
                        // echo "API response OK";
                        // echo "<br>";
                        $text_arr = $json_arr['data'];
                        $site_count = $json_arr['count'];
                        $site_list = implode(",",$text_arr);
                        //$text = "Count:".$site_count."\n"."Site List:".$site_list;
                        $text = "Count:".$site_count."\n"."Site List:\n";
                        foreach($text_arr as $row){
                            $text .= $row."\n";
                        }
                        return $text;
                    }
                
                }

				


			}
			else if(strtoupper($getMsgArray[1]) == 'CLIENT'){

				// echo "district function invade";
				// echo "<br>";

				$client = $getMsgArray[2];
				$client = trim($client," ");

				// echo $district;
				// echo "<br>";

				$url = "http://localhost:8980/unms_api/public/outage_client?client=".$client;

				// echo $url;
				// echo "<br>";

				$api_response = file_get_contents($url);

				// echo $api_response;
				// echo "<br>";

				$json_arr = json_decode($api_response,true);

				// print_r($json_arr);
				// echo "<br>";

				if($json_arr['api_response'] == 'OK'){
					// echo "API response OK";
					// echo "<br>";
					$text_arr = $json_arr['data'];
					$site_count = $json_arr['count'];
					$site_list = implode(",",$text_arr);
                    //$text = "Count:".$site_count."\n"."Site List:".$site_list;
                    $text = "Count:".$site_count."\n"."Site List:\n";
                    foreach($text_arr as $row){
                        $text .= $row."\n";
                    }

                    return $text;
				}

			}
			else if(strtoupper($getMsgArray[1]) == 'STATUS'){

				// echo "district function invade";
				// echo "<br>";

				$site_status = $getMsgArray[2];
				$site_status = trim($site_status," ");

				// echo $district;
				// echo "<br>";

				$url = "http://localhost:8980/unms_api/public/outage_status?host_name=".$site_status;

				// echo $url;
				// echo "<br>";

				$api_response = file_get_contents($url);

				// echo $api_response;
				// echo "<br>";

				$json_arr = json_decode($api_response,true);

				// print_r($json_arr);
				// echo "<br>";

				if($json_arr['api_response'] == 'OK'){
					// echo "API response OK";
					// echo "<br>";
                    $text = $json_arr['data'];
                    
                    return $text;
				}

			}
			else if(strtoupper($getMsgArray[1]) == 'ALL'){
				$url = "http://localhost:8980/unms_api/public/outage_all";

				// echo $url;
				// echo "<br>";

				$api_response = file_get_contents($url);

				// echo $api_response;
				// echo "<br>";

				$json_arr = json_decode($api_response,true);

				// print_r($json_arr);
				// echo "<br>";

				if($json_arr['api_response'] == 'OK'){
					// echo "API response OK";
					// echo "<br>";
					$text_arr = $json_arr['data'];
					$site_count = $json_arr['count'];
					$site_list = implode(",",$text_arr);
                    //$text = "Count:".$site_count."\n"."Site List:".$site_list;
                    $text = "Count:".$site_count."\n"."Site List:\n";
                    foreach($text_arr as $row){
                        $text .= $row."\n";
                    }


                    return $text;
				}
				
			}
			else{
				$text = "Please use the following command patterns:\n
                \n1) Help<space>Me (Example : Help Me)
                \nDetails: It will show you this command list
                \n2) Emp<space>EmpName (Example : Emp Ahnaf)
                \nDetails: It will give you information(Contact Number,Designation..etc) about the employee
                \nData Source: HR Database
                \n3) Outage<space>ALL (Example : Outage all)
                \nDetails: It will give you all current NodeDown
                \nData Source: UNMS
                \n4) Outage<space>DISTRICT<space>DistrictName (Example : Outage District Dhaka)
                \nDetails: It will give you all current NodeDown From the mentioned district
                \nData Source: UNMS
                \n5) Outage<space>CLIENT<space>ClientName (Example : Outage Client Robi)
                \nDetails: It will give you all current NodeDown of the mentioned client
                \nData Source: UNMS						  
                \n6) Outage<space>STATUS<space>SiteName (Example : Outage Status dhsab4)
                \nDetails: It will give you status of the mentioned Node
                \nData Source: UNMS
                \n7) Power<space>ALL (Example : Power All)
                \nDetails: It will give you list of open faults for power alarm 
                \nData Source: Phoenix
                \n8) IIG<space>ALL (Example : IIG ALL)
                \nDetails: It will give you list of open IIG faults 
                \nData Source: Phoenix
                \n9) ICX<space>ALL (Example : ICX ALL)
                \nDetails: It will give you list of open ICX faults 
                \nData Source: Phoenix
                \n10) ITC<space>ALL (Example : ITC ALL)
                \nDetails: It will give you list of open ITC faults 
                \nData Source: Phoenix
				\n11) TICKET<space>STATUS<space>ID (Example : ticket status 59759)
                \nDetails: It will give you a summary status of mentioned ticket 
                \nData Source: Phoenix
                \n12) VITAL<space>LINK<space>DOWN (Example : VITAL LINK DOWN)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category 'Link Down' 
                \nData Source: Phoenix
                \n13) VITAL<space>LINK<space>OTHER (Example : VITAL LINK OTHER)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category other than 'Link Down' 
                \nData Source: Phoenix
                \n14) VITAL<space>SITE<space>DOWN (Example : VITAL SITE DOWN)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category 'Site Down' 
                \nData Source: Phoenix
                \n15) VITAL<space>SITE<space>OTHER (Example : VITAL SITE OTHER)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category other than 'Site Down' 
                \nData Source: Phoenix
                ";
			}
        }
        else if(strtoupper($getMsgArray[0]) == 'POWER'){

			if(strtoupper($getMsgArray[1]) == 'ALL'){

				$url = "http://localhost:8980/unms_api/public/power_alarm_tt";

				// echo $url;
				// echo "<br>";

				$api_response = file_get_contents($url);

				// echo $api_response;
				// echo "<br>";

				$json_arr = json_decode($api_response,true);

				// print_r($json_arr);
				// echo "<br>";

				if($json_arr['api_response'] == 'OK'){
					// echo "API response OK";
					// echo "<br>";
					$text_arr = $json_arr['data'];
					$site_count = $json_arr['count'];
                    $site_list = implode(",",$text_arr);
                    
                    //$text = "Count:".$site_count."\n"."Site List:".$site_list;
                    $text = "Count:".$site_count."\n"."Site List:\n";
                    foreach($text_arr as $row){
                        $text .= $row."\n";
                    }


                    return $text;
				}

			}

			


        }
        
        else if(strtoupper($getMsgArray[0]) == 'IIG'){

			if(strtoupper($getMsgArray[1]) == 'ALL'){

				$url = "http://localhost:8980/unms_api/public/iig_all";

				// echo $url;
				// echo "<br>";

				$api_response = file_get_contents($url);

				// echo $api_response;
				// echo "<br>";

				$json_arr = json_decode($api_response,true);

				// print_r($json_arr);
				// echo "<br>";

				if($json_arr['api_response'] == 'OK'){
					// echo "API response OK";
					// echo "<br>";
					$text_arr = $json_arr['data'];
					$site_count = $json_arr['count'];
					$site_list = implode(",",$text_arr);
                    
                    //$text = "Count:".$site_count."\n"."Element List:".$site_list;
                    $text = "Count:".$site_count."\n"."Site List:\n";
                    foreach($text_arr as $row){
                        $text .= $row."\n";
                    }



                    return $text;
				}

			}

			


        }
        else if(strtoupper($getMsgArray[0]) == 'ITC'){

			if(strtoupper($getMsgArray[1]) == 'ALL'){

				$url = "http://localhost:8980/unms_api/public/itc_all";

				// echo $url;
				// echo "<br>";

				$api_response = file_get_contents($url);

				// echo $api_response;
				// echo "<br>";

				$json_arr = json_decode($api_response,true);

				// print_r($json_arr);
				// echo "<br>";

				if($json_arr['api_response'] == 'OK'){
					// echo "API response OK";
					// echo "<br>";
					$text_arr = $json_arr['data'];
					$site_count = $json_arr['count'];
					$site_list = implode(",",$text_arr);
                    //$text = "Count:".$site_count."\n"."Element List:".$site_list;
                    $text = "Count:".$site_count."\n"."Site List:\n";
                    foreach($text_arr as $row){
                        $text .= $row."\n";
                    }

                    return $text;
				}

			}

			


		}

		else if(strtoupper($getMsgArray[0]) == 'ICX'){

			if(strtoupper($getMsgArray[1]) == 'ALL'){

				$url = "http://localhost:8980/unms_api/public/icx_all";

				// echo $url;
				// echo "<br>";

				$api_response = file_get_contents($url);

				// echo $api_response;
				// echo "<br>";

				$json_arr = json_decode($api_response,true);

				// print_r($json_arr);
				// echo "<br>";

				if($json_arr['api_response'] == 'OK'){
					// echo "API response OK";
					// echo "<br>";
					$text_arr = $json_arr['data'];
					$site_count = $json_arr['count'];
					$site_list = implode(",",$text_arr);
                    //$text = "Count:".$site_count."\n"."Element List:".$site_list;
                    $text = "Count:".$site_count."\n"."Site List:\n";
                    foreach($text_arr as $row){
                        $text .= $row."\n";
                    }

                    return $text;
				}

			}

			


		}
		else if(strtoupper($getMsgArray[0]) == 'TICKET'){

			if(strtoupper($getMsgArray[1]) == 'STATUS'){

				$ticket_id = $getMsgArray[2]; 

				$url = "http://localhost:8980/unms_api/public/ticket_info?ticket_id=".$ticket_id;

				// echo $url;
				// echo "<br>";
                $response_code = $this->get_http_response_code($url);            
                if($response_code != "200"){
                    return "ERROR oCCURED";
                }else{
                    $api_response = file_get_contents($url);

                    // echo $api_response;
                    // echo "<br>";

                    $json_arr = json_decode($api_response,true);

                    // print_r($json_arr);
                    // echo "<br>";

                    if($json_arr['api_response'] == 'OK'){
                        // echo "API response OK";
                        // echo "<br>";
                        $text = $json_arr['data'];
                        
                        return $text;
                    }
                }
				

			}

			


        }
        // else if(strtoupper($getMsgArray[0]) == 'FAULT'){

		// 	if(strtoupper($getMsgArray[1]) == 'HOURS'){
        //         echo "fault hour reached";
        //         echo "<br>";
        //         //return "nothing";
		// 		$ticket_id = $getMsgArray[2]; 

		// 		$url = "http://localhost:8980/unms_api/public/fault_info?hr_unit=".$ticket_id;

		// 		echo $url;
		// 		echo "<br>";
        //         $response_code = $this->get_http_response_code($url);            
        //         if($response_code != "200"){
        //             return "ERROR oCCURED";
        //         }else{
        //             $api_response = file_get_contents($url);

        //             echo $api_response;
        //             echo "<br>";

        //             $json_arr = json_decode($api_response,true);

        //             print_r($json_arr);
        //             echo "<br>";

        //             if($json_arr['api_response'] == 'OK'){
        //                 echo "API response OK";
        //                 echo "<br>";
        //                 $text = $json_arr['data'];
                        
        //                 echo $text;
        //                 return $text;
        //             }
        //         }
				

		// 	}

			


        // }
        else if(strtoupper($getMsgArray[0]) == 'VITAL'){

			if(strtoupper($getMsgArray[1]) == 'LINK'){
                if(strtoupper($getMsgArray[2]) == 'DOWN'){
                    $url = "http://localhost:8980/unms_api/public/vital_link_down";

				// echo $url;
				// echo "<br>";
                $response_code = $this->get_http_response_code($url);            
                if($response_code != "200"){
                    return "ERROR OCCURED";
                }else{
                    $api_response = file_get_contents($url);
                    $json_arr = json_decode($api_response,true);
                    if($json_arr['api_response'] == 'OK'){
                        $text = $json_arr['data'];                        
                        return $text;
                    }
                }
                }else{
                    $url = "http://localhost:8980/unms_api/public/vital_link_other";
                    $response_code = $this->get_http_response_code($url);
                    if($response_code != "200"){
                        return "ERROR OCCURED";
                    }else{
                        $api_response = file_get_contents($url);
                        $json_arr = json_decode($api_response,true);
                        if($json_arr['api_response'] == 'OK'){
                            $text = $json_arr['data'];                        
                            return $text;
                        }
                    }
                }
                
				
				

            }
            
            if(strtoupper($getMsgArray[1]) == 'SITE'){
                if(strtoupper($getMsgArray[2]) == 'DOWN'){
                    $url = "http://localhost:8980/unms_api/public/vital_site_down";
                    $response_code = $this->get_http_response_code($url);            
                    if($response_code != "200"){
                        return "ERROR OCCURED";
                    }else{
                        $api_response = file_get_contents($url);
                        $json_arr = json_decode($api_response,true);
                        if($json_arr['api_response'] == 'OK'){
                            $text = $json_arr['data'];                        
                            return $text;
                        }
                    }
                    
                }
                else{
                    $url = "http://localhost:8980/unms_api/public/vital_site_other";
                    $response_code = $this->get_http_response_code($url);            
                    if($response_code != "200"){
                        return "ERROR OCCURED";
                    }else{
                        $api_response = file_get_contents($url);
                        $json_arr = json_decode($api_response,true);
                        if($json_arr['api_response'] == 'OK'){
                            $text = $json_arr['data'];                        
                            return $text;
                        }
                    }
                }
				

			}

			


		}
		else{
            $text = "Please use the following command patterns:
                \n1) Help<space>Me (Example : Help Me)
                \nDetails: It will show you this command list
                \n2) Emp<space>EmpName (Example : Emp Ahnaf)
                \nDetails: It will give you information(Contact Number,Designation..etc) about the employee
                \nData Source: HR Database
                \n3) Outage<space>ALL (Example : Outage all)
                \nDetails: It will give you all current NodeDown
                \nData Source: UNMS
                \n4) Outage<space>DISTRICT<space>DistrictName (Example : Outage District Dhaka)
                \nDetails: It will give you all current NodeDown From the mentioned district
                \nData Source: UNMS
                \n5) Outage<space>CLIENT<space>ClientName (Example : Outage Client Robi)
                \nDetails: It will give you all current NodeDown of the mentioned client
                \nData Source: UNMS						  
                \n6) Outage<space>STATUS<space>SiteName (Example : Outage Status dhsab4)
                \nDetails: It will give you status of the mentioned Node
                \nData Source: UNMS
                \n7) Power<space>ALL (Example : Power All)
                \nDetails: It will give you list of open faults for power alarm 
                \nData Source: Phoenix
                \n8) IIG<space>ALL (Example : IIG ALL)
                \nDetails: It will give you list of open IIG faults 
                \nData Source: Phoenix
                \n9) ICX<space>ALL (Example : ICX ALL)
                \nDetails: It will give you list of open ICX faults 
                \nData Source: Phoenix
                \n10) ITC<space>ALL (Example : ITC ALL)
                \nDetails: It will give you list of open ITC faults 
                \nData Source: Phoenix
				\n11) TICKET<space>STATUS<space>ID (Example : ticket status 59759)
                \nDetails: It will give you a summary status of mentioned ticket 
                \nData Source: Phoenix
                \n12) VITAL<space>LINK<space>DOWN (Example : VITAL LINK DOWN)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category 'Link Down' 
                \nData Source: Phoenix
                \n13) VITAL<space>LINK<space>OTHER (Example : VITAL LINK OTHER)
                \nDetails: It will give you list of fauls of links under special monitoring with problem category other than 'Link Down' 
                \nData Source: Phoenix
                \n14) VITAL<space>SITE<space>DOWN (Example : VITAL SITE DOWN)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category 'Site Down' 
                \nData Source: Phoenix
                \n15) VITAL<space>SITE<space>OTHER (Example : VITAL SITE OTHER)
                \nDetails: It will give you list of fauls of sites under special monitoring with problem category other than 'Site Down' 
                \nData Source: Phoenix
                ";
            return $text;
		}




        



    }

}
