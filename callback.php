<?php
//DEBUG
file_put_contents("debug.txt", file_get_contents("php://input"),FILE_APPEND);
$receive = json_decode(file_get_contents("php://input"));

use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\GuzzleHTTPClient;
use LINE\LINEBot\Message\MultipleMessages;
use LINE\LINEBot\Message\RichMessage\Markup;

require "vendor/autoload.php";
require_once "vendor/joshcam/mysqli-database-class/MysqliDb.php";

$setting = require('config.php');

$db = new MysqliDb(Array(
    'host' => $setting['dbhost'],
    'username' => $setting['dbusername'],
    'password' => $setting['dbpassword'],
    'db' => $setting['dbname'],
    'port' => $setting['dbport'],
    'charset' => $setting['dbcharset']
));

$db = MysqliDb::getInstance();

$content = $receive->result[0]->content;
$text = $content->text;
$from = $content->from;
$createdTime = $content->createdTime;

if (isset($content->location->address)){
    $address = $content->location->address;
    $latitude = $content->location->latitude;
    $longitude = $content->location->longitude;
}

$contentType = $content->contentType;

if (isset($content->contentMetadata->mid)){
    $sendProfile_mid = $content->contentMetadata->mid;
    $sendProfile_displayName = $content->contentMetadata->displayName; 
}

$config = [
    'channelId' => $setting['channelId'],
    'channelSecret' => $setting['channelSecret'],
    'channelMid' => $setting['channelMid']
];

$bot = new LINEBot($config, new GuzzleHTTPClient($config));

$profile = $bot->getUserProfile([$from]);
$callbackerDisplayname = $profile['contacts'][0]['displayName'];
$callbackerPic = $profile['contacts'][0]['pictureUrl'];

$db->insert ('callback_log', array(
    'from_displayname'=>$callbackerDisplayname,
    'from_pic'=>$callbackerPic,
    'from'=>$from,
    'created_time'=>$createdTime,
    'content_type'=>$contentType,
    'address'=>isset($address)?$address:"",
    'latitude'=>isset($latitude)?$latitude:"",
    'longitude'=>isset($longitude)?$longitude:"",
    'sendprofile_mid'=>isset($sendProfile_mid)?$sendProfile_mid:"",
    'sendprofile_displayname'=>isset($sendProfile_displayName)?$sendProfile_displayName:"",
    'text'=>isset($text)?$text:""
));

if (preg_match("/^給個數字/i", $text)) {
    $param = explode(" ",$text);
    if ($param[1]!="" && $param[2]!=""){
	$r = rand($param[1],$param[2]);
        $bot->sendText([$from], "來，開個{$r}");
    }
}

if (preg_match("/^你是誰/i", $text)){
    $markup = (new Markup(1040))
        ->setAction('event1', '一介資男', 'https://www.mxp.tw/blog/','web')
        ->addListener('event1', 0, 0, 1040, 1040);
    $bot->sendRichMessage([$from], 'http://dummyimage.com/1040x1040/000000/fff.jpg', "官方Blog", $markup);
    $bot->sendText([$from], "敲敲設計有限公司：");
    $bot->sendLocation([$from],'台北市信義路4段45號',25.0335717,121.5449681);
}

if (preg_match("/^公車/i", $text)){
    $param = explode(" ",$text);
    if ($param[1]!=""){
        $bot->sendText([$from], "如果你沒亂打公車代號，那現在公車狀態是：\nhttp://pda.5284.com.tw/MQS/businfo2.jsp?routename={$param[1]}");
    }
}

$bot->sendText([$from], "您好 {$callbackerDisplayname}，我的使用方式：\n1. 查詢機器人資訊。輸入\n「你是誰」\n2. 指定範圍，隨機選個數字。輸入\n「給個數字 1 100」\n，從1~100隨機選一個數字。\n3. 查詢公車最新動態。輸入\n「公車 藍5」\n，空格後輸入完整公車名（或號碼）");

