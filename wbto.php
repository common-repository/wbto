<?php
/*
Plugin Name: 微博通同步新版
Plugin URI: http://xiaohudie.net/wbto-plugin.html 
Description: 自动把你的博客文章同步到微博通，支持标题+内容截断+抓取图像+更新不同步。
Version: 1.2
Author: 小蝴蝶
Author URI: http://xiaohudie.net
*/
function wbto_install() {
global $wpdb;
$table_name = $wpdb->prefix."wbto";
if($wpdb->get_var("show tables like '$table_name'") != $table_name) {
$sql = "CREATE TABLE " . $table_name . " (id mediumint(9) NOT NULL AUTO_INCREMENT, wbto_username VARCHAR(100) NOT NULL, wbto_password VARCHAR(100) NOT NULL, );";
}
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);
}
function send_to_wbto($post_ID) {
$posted = get_post($post_ID);
preg_match_all('/<img[^>]+src=[\'"](http[^\'"]+)[\'"].*>/isU',$posted->post_content, $image);//匹配图像格式
$p_sum = count($image[1]);
if ($p_sum > 0) {
$p = $image[1][0];
}
if (!$p) {
if (function_exists('has_post_thumbnail') && has_post_thumbnail($post_ID)) { //  如果支持特色图像(WordPress v2.9以上)并且存在特色图像,则抓特色图像
if ($image_url = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'post-thumbnail'))
$p = $image_url[0];
}
}
$t1=$posted->post_date;
$t2=$posted->post_modified;
$diff=strtotime($t2)-strtotime($t1);
if($diff>0){return;} //重要修改:加上了发布时间判断,如果文章更新,将不会同步到微博
$username = get_option('wbto_username');
$password = get_option('wbto_password');
$posted = get_post($post_ID);
$image_url = $p;//重要修改:抓图像,支持外链图片和本地上传的图片,如果有特色图像则抓特色图像
$excerpt=mb_strimwidth(strip_tags($posted->post_content),0,130,'...'); //截130字的内容,因为总共140字还要留一点在最后放链接
$fields = array();
$fields['source'] = 'wordpress';
$fields['content'] = urlencode('［'.$posted->post_title.'］'.mb_strimwidth(strip_tags($excerpt),0,130,'...').' '.$posted->guid); //微博格式,效果如图,即:［标题］摘要内容+链接
$ch = curl_init();
if ($image_url) { //图片和文字微博的接口不同,所以要加个判断,否则会同步失败
$fields['imgurl'] =$image_url;
curl_setopt($ch, CURLOPT_URL, "http://wbto.cn/api/upload.json"); //这是官方API里提供的图片同步的接口
} else {
curl_setopt($ch, CURLOPT_URL, "http://wbto.cn/api/update.json"); //这是普通文字微博接口
}
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER,TRUE);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
$result = curl_exec($ch);
curl_close($ch);
}
//以下代码原作者yige
function wbto_menu() {
add_options_page('微博通同步设置', '微博通同步', 8, __FILE__, 'wbto_options');
}//添加一个设置页面
function wbto_options() {//下面输出设置页面的HTML
echo '<div class="wrap">';
echo '<h2>微博通同步</h2>';
echo '<form method="post" action="options.php">';
echo wp_nonce_field('update-options');
echo '<table class="form-table">';
echo '<tr valign="top">';
echo '<th scope="row">用户名 <a href="http://www.wbto.cn/?app=wp">注册</a></th>';
echo '<td><input type="text" name="wbto_username" value="'.get_option('wbto_username').'" /></td>';
echo '</tr>';
echo '<tr valign="top">';
echo '<th scope="row">密码</th>';
echo '<td><input type="password" name="wbto_password" value="'.get_option('wbto_password').'" /></td>';
echo '</tr>';
echo '</table>';
echo '<input type="hidden" name="action" value="update" />';
echo '<input type="hidden" name="page_options" value="wbto_username,wbto_password" />';
echo '<p class="submit">';
echo '<input type="submit" name="submit" id="submit" class="button-primary" value="保存更改" />';
echo '</p>';
echo '</form>';
echo '</div>';

}
add_action('admin_menu', 'wbto_menu');//将插件设置页面挂在后台设置中
add_action('publish_post', 'send_to_wbto');//动作在发布文章时触发
?>