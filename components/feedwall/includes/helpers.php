<?php
/******************************************************************************/
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                        LICENSED BY GNU/GPL v2                              //
/******************************************************************************/

if(!defined('VALID_CMS')) { die('ACCESS DENIED'); }

/**
 * Символы вне BMP (эмодзи) -> числовые HTML-сущности.
 * Соединение 1.x работает в utf8 (mb3), поэтому 4-байтные символы
 * сохраняем как &#NNNNN; — это работает при любой кодировке таблиц.
 */
function fw_mb4_encode($str){
    return preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', function($m){
        $c = mb_convert_encoding($m[0], 'UTF-32BE', 'UTF-8');
        $u = unpack('N', $c);
        return '&#'.$u[1].';';
    }, (string)$str);
}

/**
 * Нормализует пользовательский ввод: без тегов, без управляющих символов,
 * с ограничением длины. Возвращает НЕэкранированный текст.
 */
function fw_clean_text($str, $maxlen = 0){
    $str = (string)$str;
    if (!mb_check_encoding($str, 'UTF-8')) { $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8'); }
    $str = str_replace(array("\r\n", "\r"), "\n", $str);
    $str = strip_tags($str);
    $str = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $str);
    $str = preg_replace("/\n{3,}/", "\n\n", $str);
    $str = trim($str);
    if ($maxlen > 0 && mb_strlen($str) > $maxlen) { $str = mb_substr($str, 0, $maxlen); }
    return fw_mb4_encode($str);
}

function fw_esc($str){
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8', false);
}

function fw_smiles(){
    static $list = null;
    if ($list !== null) { return $list; }
    $list = array();
    foreach((glob(PATH.'/images/smilies/*.gif') ?: array()) as $f){
        $n = basename($f, '.gif');
        $list[$n] = '/images/smilies/'.$n.'.gif';
    }
    return $list;
}

function fw_hashtag_regex(){
    return '/(^|[\s>(,.!?;:])#([\p{L}\p{N}_]{2,50})/u';
}

function fw_extract_tags($text){
    if (!preg_match_all(fw_hashtag_regex(), (string)$text, $m)) { return array(); }
    $tags = array();
    foreach($m[2] as $t){ $tags[mb_strtolower($t)] = 1; }
    return array_slice(array_keys($tags), 0, 20);
}

function fw_extract_mentions($text){
    if (!preg_match_all('/(^|[\s>(,])@([a-zA-Z0-9_.\-]{2,40})/u', (string)$text, $m)) { return array(); }
    return array_slice(array_unique(array_map(function($l){ return rtrim($l, '.-'); }, $m[2])), 0, 20);
}

/**
 * Безопасный рендер текста: экранирование -> BB-коды [b][i][u][s][quote]
 * -> ссылки -> упоминания -> хэштеги -> смайлы -> переносы строк.
 */
function fw_render_text($text, $opt = array()){

    $text = fw_esc($text);
    if ($text === '') { return ''; }

    $text = preg_replace('/\[(b|i|u|s)\](.+?)\[\/\1\]/su', '<$1>$2</$1>', $text);
    $text = preg_replace('/\[quote\](.+?)\[\/quote\]/su', '<blockquote>$1</blockquote>', $text);

    // ссылки
    $text = preg_replace_callback('#(^|[\s>(])(https?://[^\s<>"\']{3,500})#u', function($m){
        $url = rtrim($m[2], '.,!?;:)');
        $tail = mb_substr($m[2], mb_strlen($url));
        $label = mb_strlen($url) > 60 ? mb_substr($url, 0, 57).'...' : $url;
        return $m[1].'<a href="'.$url.'" target="_blank" rel="nofollow noopener">'.$label.'</a>'.$tail;
    }, $text);

    // упоминания
    if (!empty($opt['mentions'])){
        $users = isset($opt['users']) ? $opt['users'] : array();
        $text = preg_replace_callback('/(^|[\s>(,])@([a-zA-Z0-9_.\-]{2,40})/u', function($m) use ($users){
            $login = rtrim($m[2], '.-'); $tail = substr($m[2], strlen($login));
            $key = mb_strtolower($login);
            if (!isset($users[$key])) { return $m[0]; }
            $u = $users[$key];
            return $m[1].'<a class="fw-mention fw-usercard" data-uid="'.$u['id'].'" href="'.cmsUser::getProfileURL($u['login']).'">@'.fw_esc($u['nickname']).'</a>'.$tail;
        }, $text);
    }

    // хэштеги
    if (!empty($opt['hashtags'])){
        $text = preg_replace_callback(fw_hashtag_regex(), function($m){
            return $m[1].'<a class="fw-hashtag" href="/feedwall/tag/'.rawurlencode(mb_strtolower($m[2])).'">#'.$m[2].'</a>';
        }, $text);
    }

    // смайлы :name:
    if (!empty($opt['smiles'])){
        $smiles = fw_smiles();
        $text = preg_replace_callback('/:([a-z]{1,12}):/', function($m) use ($smiles){
            return isset($smiles[$m[1]]) ? '<img class="fw-smile" src="'.$smiles[$m[1]].'" alt=":'.$m[1].':" />' : $m[0];
        }, $text);
    }

    return nl2br($text);

}

function fw_plain_cut($text, $len = 150){
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$text), ENT_QUOTES, 'UTF-8')));
    $text = preg_replace('/\[\/?(b|i|u|s|quote)\]/u', '', $text);
    if (mb_strlen($text) > $len) { $text = mb_substr($text, 0, $len).'…'; }
    return $text;
}

// ========================================================================== //
//                                 ВИДЕО                                      //
// ========================================================================== //

/**
 * Определяет видео по ссылке. Возвращает array(provider, id, url, embed, thumb) или false
 */
function fw_parse_video($url){

    $url = trim((string)$url);
    if (!preg_match('#^https?://#i', $url)) { return false; }

    if (preg_match('#(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([a-zA-Z0-9_\-]{11})#', $url, $m)){
        return array('provider'=>'youtube', 'id'=>$m[1], 'url'=>$url,
                     'embed'=>'https://www.youtube.com/embed/'.$m[1],
                     'thumb'=>'https://img.youtube.com/vi/'.$m[1].'/hqdefault.jpg');
    }
    if (preg_match('#rutube\.ru/(?:video|play/embed|shorts)/([a-f0-9]{32})#i', $url, $m)){
        return array('provider'=>'rutube', 'id'=>$m[1], 'url'=>$url,
                     'embed'=>'https://rutube.ru/play/embed/'.$m[1], 'thumb'=>'');
    }
    if (preg_match('#vimeo\.com/(?:video/)?([0-9]{4,12})#', $url, $m)){
        return array('provider'=>'vimeo', 'id'=>$m[1], 'url'=>$url,
                     'embed'=>'https://player.vimeo.com/video/'.$m[1], 'thumb'=>'');
    }
    if (preg_match('#(?:vk\.com|vkvideo\.ru)/video(-?[0-9]+)_([0-9]+)#', $url, $m)){
        return array('provider'=>'vk', 'id'=>$m[1].'_'.$m[2], 'url'=>$url,
                     'embed'=>'https://vk.com/video_ext.php?oid='.$m[1].'&id='.$m[2].'&hd=2', 'thumb'=>'');
    }
    if (preg_match('#(?:facebook\.com|fb\.watch)/#i', $url) && preg_match('#/videos?/|fb\.watch|watch/?\?v=#i', $url)){
        return array('provider'=>'facebook', 'id'=>md5($url), 'url'=>$url,
                     'embed'=>'https://www.facebook.com/plugins/video.php?href='.rawurlencode($url).'&show_text=0', 'thumb'=>'');
    }

    return false;

}

// ========================================================================== //
//                          ЗАГРУЗКА ВНЕШНИХ СТРАНИЦ                          //
// ========================================================================== //

/**
 * Защита от SSRF: только http(s), только публичные адреса
 */
function fw_is_public_url($url){

    $p = @parse_url($url);
    if (!$p || empty($p['host']) || !in_array(strtolower(@$p['scheme']), array('http','https'))) { return false; }
    if (isset($p['user']) || isset($p['pass'])) { return false; }

    $host = $p['host'];
    $ips  = filter_var($host, FILTER_VALIDATE_IP) ? array($host) : @gethostbynamel($host);
    if (!$ips) { return false; }

    foreach($ips as $ip){
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) { return false; }
    }

    return true;

}

function fw_http_get($url, $max_bytes = 524288, $timeout = 6, $redirects = 3){

    if (!function_exists('curl_init')) { return false; }

    for($i = 0; $i <= $redirects; $i++){

        if (!fw_is_public_url($url)) { return false; }

        $data = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; feedwall/1.0; +InstantCMS)',
            CURLOPT_HTTPHEADER     => array('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION  => function($ch, $chunk) use (&$data, $max_bytes){
                $data .= $chunk;
                return strlen($data) > $max_bytes ? 0 : strlen($chunk);
            }
        ));
        curl_exec($ch);
        $code     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $ctype    = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($code >= 300 && $code < 400 && $location){ $url = $location; continue; }
        if ($code != 200 || $data === '') { return false; }

        return array('body'=>$data, 'type'=>$ctype, 'url'=>$url);

    }

    return false;

}

/**
 * Превью ссылки (Open Graph / title / description)
 */
function fw_link_preview($url){

    $res = fw_http_get($url);
    if (!$res || stripos($res['type'], 'html') === false) { return false; }

    $html = $res['body'];

    if (preg_match('/<meta[^>]+charset=["\']?([a-z0-9\-]+)/i', $html, $m) && strtolower($m[1]) != 'utf-8'){
        $conv = @mb_convert_encoding($html, 'UTF-8', $m[1]);
        if ($conv) { $html = $conv; }
    }

    $meta = function($names) use ($html){
        foreach($names as $n){
            if (preg_match('/<meta[^>]+(?:property|name)=["\']'.preg_quote($n, '/').'["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)){ return $m[1]; }
            if (preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']'.preg_quote($n, '/').'["\']/i', $html, $m)){ return $m[1]; }
        }
        return '';
    };

    $title = $meta(array('og:title', 'twitter:title'));
    if (!$title && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) { $title = $m[1]; }
    $desc  = $meta(array('og:description', 'description', 'twitter:description'));
    $image = $meta(array('og:image', 'twitter:image'));
    $site  = $meta(array('og:site_name'));

    if ($image && !preg_match('#^https?://#i', $image)){
        $p = parse_url($res['url']);
        $image = $p['scheme'].'://'.$p['host'].(substr($image, 0, 1) == '/' ? '' : '/').$image;
    }
    if ($image && !preg_match('#^https?://#i', $image)) { $image = ''; }

    $dec = function($s, $len){ return mb_substr(trim(html_entity_decode(strip_tags($s), ENT_QUOTES, 'UTF-8')), 0, $len); };

    $host = parse_url($url, PHP_URL_HOST);

    return array(
        'url'   => mb_substr($url, 0, 500),
        'title' => fw_mb4_encode($dec($title, 200)),
        'desc'  => fw_mb4_encode($dec($desc, 300)),
        'image' => mb_substr($image, 0, 500),
        'site'  => fw_mb4_encode($dec($site ? $site : $host, 100))
    );

}

/**
 * Минимальный разбор ID3 (v2.3/2.4 TIT2/TPE1 и v1) для mp3
 */
function fw_id3($file){

    $res = array('title'=>'', 'artist'=>'');
    $fh = @fopen($file, 'rb');
    if (!$fh) { return $res; }

    $head = fread($fh, 10);
    if (strlen($head) == 10 && substr($head, 0, 3) == 'ID3'){
        $ver  = ord($head[3]);
        $size = (ord($head[6]) << 21) | (ord($head[7]) << 14) | (ord($head[8]) << 7) | ord($head[9]);
        $tag  = fread($fh, min($size, 262144));
        $pos  = 0;
        while($pos + 10 < strlen($tag)){
            $id = substr($tag, $pos, 4);
            if (!preg_match('/^[A-Z0-9]{4}$/', $id)) { break; }
            $b  = substr($tag, $pos + 4, 4);
            $fs = $ver >= 4 ? ((ord($b[0]) << 21) | (ord($b[1]) << 14) | (ord($b[2]) << 7) | ord($b[3])) : unpack('N', $b)[1];
            if ($fs <= 0) { break; }
            $data = substr($tag, $pos + 10, $fs);
            if ($id == 'TIT2' || $id == 'TPE1'){
                $enc = ord($data[0]); $val = substr($data, 1);
                if ($enc == 1 || $enc == 2){ $val = @mb_convert_encoding($val, 'UTF-8', $enc == 1 ? 'UTF-16' : 'UTF-16BE'); }
                elseif ($enc == 0) { $val = @mb_convert_encoding($val, 'UTF-8', 'Windows-1251'); }
                $val = trim(str_replace("\0", '', (string)$val));
                if ($id == 'TIT2') { $res['title'] = $val; } else { $res['artist'] = $val; }
            }
            $pos += 10 + $fs;
        }
    }

    if (!$res['title']){
        fseek($fh, -128, SEEK_END);
        $v1 = fread($fh, 128);
        if (strlen($v1) == 128 && substr($v1, 0, 3) == 'TAG'){
            $res['title']  = trim(@mb_convert_encoding(rtrim(substr($v1, 3, 30), "\0 "), 'UTF-8', 'Windows-1251'));
            $res['artist'] = trim(@mb_convert_encoding(rtrim(substr($v1, 33, 30), "\0 "), 'UTF-8', 'Windows-1251'));
        }
    }

    fclose($fh);

    $res['title']  = fw_clean_text(mb_substr($res['title'], 0, 200));
    $res['artist'] = fw_clean_text(mb_substr($res['artist'], 0, 200));

    return $res;

}

/**
 * Ресайз изображения GD с сохранением пропорций; возвращает array(w,h) или false
 */
function fw_image_resize($src, $dst, $max_w, $max_h = 0, $crop = false){

    $info = @getimagesize($src);
    if (!$info) { return false; }
    list($w, $h, $type) = $info;
    if ($w < 1 || $h < 1 || $w * $h > 50000000) { return false; }

    switch($type){
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src); break;
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
        default: $img = false;
    }
    if (!$img) { return false; }

    if (!$max_h) { $max_h = $max_w; }

    $sx = 0; $sy = 0; $sw = $w; $sh = $h;
    if ($crop){
        $side = min($w, $h); $sx = (int)(($w - $side) / 2); $sy = (int)(($h - $side) / 2); $sw = $sh = $side;
        $nw = $nh = min($max_w, $side);
    } else {
        $k = min(1, $max_w / $w, $max_h / $h);
        $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
    }

    $out = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($out, 255, 255, 255);
    imagefill($out, 0, 0, $white);
    imagecopyresampled($out, $img, 0, 0, $sx, $sy, $nw, $nh, $sw, $sh);
    $ok = imagejpeg($out, $dst, 86);
    imagedestroy($img); imagedestroy($out);

    return $ok ? array($nw, $nh) : false;

}
