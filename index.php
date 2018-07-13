<?php
// 短信配置文件
// +----------------------------------------------------------------------
// | PHP version 5.3+
// +----------------------------------------------------------------------
// | Copyright (c) 2012-2014 http://www.myzy.com.cn, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 阶级娃儿 <262877348@qq.com> 群：304104682
// +----------------------------------------------------------------------
header("Content-Type: Text/Html;Charset=UTF-8");
require "./vendor/autoload.php";
//require 'src/PHPQRImgCode.php';

// 创建二维码类
$qrcode_obj = new \think\PHPQRImgCode();

$imgg = $qrcode_obj->radius_img('./logo.jpg', 20);

imagepng($imgg, 'new_log.png');
imagedestroy($imgg);

$config = [
    'ecc'                => 'H',    // L-smallest, M, Q, H-best
    'size'               => 12,    // 1-50
    'dest_file'          => 'now_logo.jpg',
    'quality'            => 90,
    'logo'               => 'new_log.png',
    'logo_size'          => 100,
    'logo_outline_size'  => 15,
    'logo_outline_color' => '#FFFFFF',
    'logo_radius'        => 15,
    'logo_opacity'       => 100,
];

// 二维码地址生成
$url = 'http://www.baidu.com/id/OZ25125625';

// 设定配置
$qrcode_obj->set_config($config);

// 创建二维码
$qrcode = $qrcode_obj->generate($url);

// 显示二维码
echo '<img src="'.$qrcode.'?t='.time().'">';



