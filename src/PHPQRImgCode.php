<?php
// 类库名称：PHP创建二维码类
// +----------------------------------------------------------------------
// | PHP version 5.6+
// +----------------------------------------------------------------------
// | Copyright (c) 2012-2014 http://www.myzy.com.cn, All rights reserved.
// +----------------------------------------------------------------------
// | Author: 阶级娃儿 <262877348@qq.com> 群：304104682
// +----------------------------------------------------------------------
namespace think;

require_once dirname(__FILE__)."/qrlib.php";
/**
 * public  set_config           设定配置
 * public  generate             创建二维码
 * private create_qrcode        创建纯二维码图片
 * private add_logo             合拼纯二维码图片与logo图片
 * private image_outline        图片对象进行描边
 * private image_fillet         图片对象进行圆角处理
 * private radius_img           图片倒角[新添加2018-07-13]
 * private imagecopymerge_alpha 合拼图片并保留各自透明度
 * private create_dirs          创建目录
 * private hex2rgb              hex颜色转rgb颜色
 * private get_file_ext         获取图片类型
 */

class PHPQRImgCode
{
    /** 默认设定 */
    private $_config = array(
        'ecc'                => 'H',                       // 二维码质量 L-smallest, M, Q, H-best
        'size'               => 15,                       // 二维码尺寸 1-50
        'dest_file'          => 'qrcode.png',        // 创建的二维码路径
        'quality'            => 100,                    // 图片质量
        'logo'               => '',                       // logo路径，为空表示没有logo
        'logo_size'          => null,                // logo尺寸，null表示按二维码尺寸比例自动计算
        'logo_outline_size'  => null,        // logo描边尺寸，null表示按logo尺寸按比例自动计算
        'logo_outline_color' => '#FFFFFF',  // logo描边颜色
        'logo_opacity'       => 100,              // logo不透明度 0-100
        'logo_radius'        => 0,                 // logo圆角角度 0-30
    );

    /**
     * 设定配置
     * @param  Array   $config 配置内容
     */
    public function set_config($config)
    {
        // 允许设定的配置
        $config_keys = array_keys($this->_config);

        // 获取传入的配置，写入设定
        foreach($config_keys as $k=>$v) {
            if(isset($config[$v])){
                $this->_config[$v] = $config[$v];
            }
        }
    }

    /**
     * 创建二维码
     * @param  String $data 二维码内容
     * @return String
     */
    public function generate($data)
    {

        // 创建临时二维码图片
        $tmp_qrcode_file = $this->create_qrcode($data);

        // 合拼临时二维码图片与logo图片
        $result = $this->add_logo($tmp_qrcode_file);

        // 删除临时二维码图片
        if($tmp_qrcode_file!='' && file_exists($tmp_qrcode_file)){
            unlink($tmp_qrcode_file);
        }

        file_exists($this->_config['dest_file'])? $this->_config['dest_file'] : '';

        return $result;
    }

    /**
     * 创建临时二维码图片
     * @param  String $data 二维码内容
     * @return String
     */
    private function create_qrcode($data)
    {
        // 临时二维码图片
        $tmp_qrcode_file = QR_LOG_DIR .'/tmp_qrcode_'.time().mt_rand(100,999).'.png';

        // 创建临时二维码
        QRcode::png($data, $tmp_qrcode_file, $this->_config['ecc'], $this->_config['size'], 2);

        // 返回临时二维码路径
        return file_exists($tmp_qrcode_file)? $tmp_qrcode_file : '';

    }

    /**
     * 合拼临时二维码图片与logo图片
     * @param String $tmp_qrcode_file 临时二维码图片
     */
    private function add_logo($tmp_qrcode_file)
    {
        // 创建目标文件夹
        $this->create_dirs(dirname($this->_config['dest_file']));

        // 获取目标图片的类型
        $dest_ext = $this->get_file_ext($this->_config['dest_file']);

        // 需要加入logo
        if(file_exists($this->_config['logo'])){

            // 创建临时二维码图片对象
            $tmp_qrcode_img = imagecreatefrompng($tmp_qrcode_file);

            // 获取临时二维码图片尺寸
            list($qrcode_w, $qrcode_h, $qrcode_type) = getimagesize($tmp_qrcode_file);

            // 获取logo图片尺寸及类型
            list($logo_w, $logo_h, $logo_type) = getimagesize($this->_config['logo']);

            // 创建logo图片对象
            switch($logo_type) {
                case 1: $logo_img = imagecreatefromgif($this->_config['logo']); break;
                case 2: $logo_img = imagecreatefromjpeg($this->_config['logo']); break;
                case 3: $logo_img = imagecreatefrompng($this->_config['logo']); break;
                default: return '';
            }

            // 设定logo图片合拼尺寸，没有设定则按比例自动计算
            $new_logo_w = isset($this->_config['logo_size'])? $this->_config['logo_size'] : (int)($qrcode_w/5);
            $new_logo_h = isset($this->_config['logo_size'])? $this->_config['logo_size'] : (int)($qrcode_h/5);

            // 按设定尺寸调整logo图片
            $new_logo_img = imagecreatetruecolor($new_logo_w, $new_logo_h);
            imagecopyresampled($new_logo_img, $logo_img, 0, 0, 0, 0, $new_logo_w, $new_logo_h, $logo_w, $logo_h);

            // 判断是否需要描边
            if(!isset($this->_config['logo_outline_size']) || $this->_config['logo_outline_size']>0) {
                list($new_logo_img, $new_logo_w, $new_logo_h) = $this->image_outline($new_logo_img);
            }

            // 判断是否需要圆角处理
            if($this->_config['logo_radius']>0) {
                $new_logo_img = $this->image_fillet($new_logo_img);
            }

            // 合拼logo与临时二维码
            $pos_x = ($qrcode_w-$new_logo_w)/2;
            $pos_y = ($qrcode_h-$new_logo_h)/2;

            imagealphablending($tmp_qrcode_img, true);

            // 合拼图片并保留各自透明度
            $dest_img = $this->imagecopymerge_alpha($tmp_qrcode_img, $new_logo_img, $pos_x, $pos_y, 0, 0, $new_logo_w, $new_logo_h, $this->_config['logo_opacity']);

            // 生成图片
            switch($dest_ext) {
                case 1: $is_bool = imagegif($dest_img, $this->_config['dest_file'], $this->_config['quality']); break;
                case 2: $is_bool = imagejpeg($dest_img, $this->_config['dest_file'], $this->_config['quality']); break;
                case 3: $is_bool = imagepng($dest_img, $this->_config['dest_file'], (int)(($this->_config['quality']-1)/10)); break;
            }
        } else {
            // 不需要加入logo
            $dest_img = imagecreatefrompng($tmp_qrcode_file);

            // 生成图片
            switch($dest_ext){
                case 1: $is_bool = imagegif($dest_img, $this->_config['dest_file'], $this->_config['quality']); break;
                case 2: $is_bool = imagejpeg($dest_img, $this->_config['dest_file'], $this->_config['quality']); break;
                case 3: $is_bool = imagepng($dest_img, $this->_config['dest_file'], (int)(($this->_config['quality']-1)/10)); break;
            }
        }

        return $is_bool;
    }

    /**
     * 对图片对象进行描边
     * @param  Obj   $img 图片对象
     * @return Array
     */
    private function image_outline($img)
    {
        // 获取图片宽高
        $img_w = imagesx($img);
        $img_h = imagesy($img);

        // 计算描边尺寸，没有设定则按比例自动计算
        $bg_w = isset($this->_config['logo_outline_size'])? intval($img_w + $this->_config['logo_outline_size']) : $img_w + (int)($img_w/5);
        $bg_h = isset($this->_config['logo_outline_size'])? intval($img_h + $this->_config['logo_outline_size']) : $img_h + (int)($img_h/5);

        // 创建底图对象
        $bg_img = imagecreatetruecolor($bg_w, $bg_h);

        // 设置底图颜色
        $rgb = $this->hex2rgb($this->_config['logo_outline_color']);
        $bgcolor = imagecolorallocate($bg_img, $rgb['r'], $rgb['g'], $rgb['b']);

        // 填充底图颜色
        imagefill($bg_img, 1, 1, $bgcolor);

        // 合拼图片与底图，实现描边效果
        imagecopy($bg_img, $img, (int)(($bg_w-$img_w)/2), (int)(($bg_h-$img_h)/2), 0, 0, $img_w, $img_h);

        $img = $bg_img;

        return array($img, $bg_w, $bg_h);
    }

    /**
     * 对图片对象进行圆角处理
     * @param  Obj $img 图片对象
     * @return Obj
     */
    private function image_fillet($img)
    {
        // 获取图片宽高
        $img_w = imagesx($img);
        $img_h = imagesy($img);

        // 创建圆角图片对象
        $new_img = imagecreatetruecolor($img_w, $img_h);

        // 保存透明通道
        imagesavealpha($new_img, true);

        // 填充圆角图片
        $bg = imagecolorallocatealpha($new_img, 255, 255, 255, 127);
        imagefill($new_img, 0, 0, $bg);

        // 圆角半径
        $r = $this->_config['logo_radius'];

        // 执行圆角处理
        for($x=0; $x<$img_w; $x++){
            for($y=0; $y<$img_h; $y++){
                $rgb = imagecolorat($img, $x, $y);

                // 不在图片四角范围，直接画图
                if(($x>=$r && $x<=($img_w-$r)) || ($y>=$r && $y<=($img_h-$r))){
                    imagesetpixel($new_img, $x, $y, $rgb);

                // 在图片四角范围，选择画图
                }else{
                    // 上左
                    $ox = $r; // 圆心x坐标
                    $oy = $r; // 圆心y坐标
                    if( ( ($x-$ox)*($x-$ox) + ($y-$oy)*($y-$oy) ) <= ($r*$r) ){
                        imagesetpixel($new_img, $x, $y, $rgb);
                    }

                    // 上右
                    $ox = $img_w-$r; // 圆心x坐标
                    $oy = $r;        // 圆心y坐标
                    if( ( ($x-$ox)*($x-$ox) + ($y-$oy)*($y-$oy) ) <= ($r*$r) ){
                        imagesetpixel($new_img, $x, $y, $rgb);
                    }

                    // 下左
                    $ox = $r;        // 圆心x坐标
                    $oy = $img_h-$r; // 圆心y坐标
                    if( ( ($x-$ox)*($x-$ox) + ($y-$oy)*($y-$oy) ) <= ($r*$r) ){
                        imagesetpixel($new_img, $x, $y, $rgb);
                    }

                    // 下右
                    $ox = $img_w-$r; // 圆心x坐标
                    $oy = $img_h-$r; // 圆心y坐标
                    if( ( ($x-$ox)*($x-$ox) + ($y-$oy)*($y-$oy) ) <= ($r*$r) ){
                        imagesetpixel($new_img, $x, $y, $rgb);
                    }

                }

            }
        }

        return $new_img;
    }

    /** 处理圆角图片
     * @param  string  $imgpath 源图片路径
     * @param  integer $radius  圆角半径长度默认为15,处理成圆型
     * @return [type]           [description]
     */
    public function radius_img($imgpath = './t.png', $radius = 15)
    {
        $ext     = pathinfo($imgpath);
        $src_img = null;
        switch ($ext['extension']) {
        case 'jpg':
            $src_img = imagecreatefromjpeg($imgpath);
            break;
        case 'png':
            $src_img = imagecreatefrompng($imgpath);
            break;
        }
        $wh = getimagesize($imgpath);
        $w  = $wh[0];
        $h  = $wh[1];
        // $radius = $radius == 0 ? (min($w, $h) / 2) : $radius;
        $img = imagecreatetruecolor($w, $h);
        //这一句一定要有
        imagesavealpha($img, true);
        //拾取一个完全透明的颜色,最后一个参数127为全透明
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 0);
        imagefill($img, 0, 0, $bg);
        $r = $radius; //圆 角半径
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $rgbColor = imagecolorat($src_img, $x, $y);
                if (($x >= $radius && $x <= ($w - $radius)) || ($y >= $radius && $y <= ($h - $radius))) {
                    //不在四角的范围内,直接画
                    imagesetpixel($img, $x, $y, $rgbColor);
                } else {
                    //在四角的范围内选择画
                    //上左
                    $y_x = $r; //圆心X坐标
                    $y_y = $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //上右
                    $y_x = $w - $r; //圆心X坐标
                    $y_y = $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //下左
                    $y_x = $r; //圆心X坐标
                    $y_y = $h - $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                    //下右
                    $y_x = $w - $r; //圆心X坐标
                    $y_y = $h - $r; //圆心Y坐标
                    if (((($x - $y_x) * ($x - $y_x) + ($y - $y_y) * ($y - $y_y)) <= ($r * $r))) {
                        imagesetpixel($img, $x, $y, $rgbColor);
                    }
                }
            }
        }

        return $img;
    }

    // 合拼图片并保留各自透明度
    private function imagecopymerge_alpha($dest_img, $src_img, $pos_x, $pos_y, $src_x, $src_y, $src_w, $src_h, $opacity)
    {

        $w = imagesx($src_img);
        $h = imagesy($src_img);

        $tmp_img = imagecreatetruecolor($src_w, $src_h);

        imagecopy($tmp_img, $dest_img, 0, 0, $pos_x, $pos_y, $src_w, $src_h);
        imagecopy($tmp_img, $src_img, 0, 0, $src_x, $src_y, $src_w, $src_h);
        imagecopymerge($dest_img, $tmp_img, $pos_x, $pos_y, $src_x, $src_y, $src_w, $src_h, $opacity);

        return $dest_img;

    }

    /**
     * 创建目录
     * @param  String  $path
     * @return Boolean
     */
    private function create_dirs($path)
    {
        if(!is_dir($path)) {
            return mkdir($path, 0777, true);
        }

        return true;
    }

    /** hex颜色转rgb颜色
     *  @param  String $color hex颜色
     *  @return Array
     */
    private function hex2rgb($hexcolor)
    {
        $color = str_replace('#', '', $hexcolor);
        if (strlen($color) > 3) {
            $rgb = array(
                'r' => hexdec(substr($color, 0, 2)),
                'g' => hexdec(substr($color, 2, 2)),
                'b' => hexdec(substr($color, 4, 2))
            );
        } else {
            $r = substr($color, 0, 1) . substr($color, 0, 1);
            $g = substr($color, 1, 1) . substr($color, 1, 1);
            $b = substr($color, 2, 1) . substr($color, 2, 1);
            $rgb = array(
                'r' => hexdec($r),
                'g' => hexdec($g),
                'b' => hexdec($b)
            );
        }
        return $rgb;
    }

    /** 获取图片类型
     * @param  String $file 图片路径
     * @return int
     */
    private function get_file_ext($file)
    {
        $filename = basename($file);
        list($name, $ext)= explode('.', $filename);

        $ext_type = 0;

        switch(strtolower($ext)){
            case 'jpg':
            case 'jpeg':
                $ext_type = 2;
                break;
            case 'gif':
                $ext_type = 1;
                break;
            case 'png':
                $ext_type = 3;
                break;
        }

        return $ext_type;
    }
}
?>