<?php
if(!defined('VALID_CMS_ADMIN')) { die('ACCESS DENIED'); }
/******************************************************************************/
//                                                                            //
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                                                                            //
//                        LICENSED BY GNU/GPL v2                              //
//                                                                            //
/******************************************************************************/

cmsCore::loadClass('actions');
cmsCore::includeFile('components/feedwall/includes/render.php');

$inDB   = cmsDatabase::getInstance();
$inUser = cmsUser::getInstance();
$model  = fw_model();
$cfg    = $model->config;

$opt  = preg_replace('/[^a-z_]/', '', (string)cmsCore::request('opt', 'str', 'posts'));
$base = '?view=components&do=config&id='.(int)$id;

function fwa_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fwa_in($k, $d = ''){ return isset($_POST[$k]) && !is_array($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function fwa_ids(){ $ids = isset($_POST['item']) ? (array)$_POST['item'] : array(); return array_filter(array_map('intval', $ids)); }
function fwa_token_field(){ return '<input type="hidden" name="csrf_token" value="'.cmsUser::getCsrfToken().'" />'; }
function fwa_btn($base, $opt, $hidden, $label, $confirm = ''){
    $h = '<form method="post" action="'.$base.'&amp;opt='.$opt.'" style="display:inline"'.($confirm ? ' onsubmit="return confirm(\''.fwa_h(addslashes($confirm)).'\')"' : '').'>'.fwa_token_field();
    foreach($hidden as $k => $v){ $h .= '<input type="hidden" name="'.fwa_h($k).'" value="'.fwa_h($v).'" />'; }
    return $h.'<input type="submit" value="'.fwa_h($label).'" /></form>';
}
function fwa_date($d){ return $d ? fwa_h(date('d.m.Y H:i', strtotime($d))) : '&mdash;'; }
function fwa_upload_image($field, $sub, $max_w){
    if (empty($_FILES[$field]['name']) || !empty($_FILES[$field]['error'])) { return ''; }
    $tmp = $_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp)) { return ''; }
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP))) { return ''; }
    $dir = PATH.'/upload/feedwall/'.$sub;
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $ext = array(IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp');
    $name = md5(uniqid('', true).mt_rand()).'.'.$ext[$info[2]];
    if ($info[2] == IMAGETYPE_GIF || $max_w <= 0){
        if (!move_uploaded_file($tmp, $dir.'/'.$name)) { return ''; }
    } else {
        $name = preg_replace('/\.\w+$/', '.jpg', $name);
        if (!fw_image_resize($tmp, $dir.'/'.$name, $max_w)) { return ''; }
    }
    return '/upload/feedwall/'.$sub.'/'.$name;
}
function fwa_url($u){ $u = trim((string)$u); return ($u === '' || preg_match('#^(https?://|/)[^\s"\'<>]*$#i', $u)) ? mb_substr($u, 0, 500) : ''; }

//============================================================================//
//                               МЕНЮ                                         //
//============================================================================//

$menu = array(
    'posts'      => array('liststuff.gif', $_LANG['FWA_POSTS']),
    'comments'   => array('listcomments.gif', $_LANG['FWA_COMMENTS']),
    'complaints' => array('hide.gif', $_LANG['FWA_COMPLAINTS']),
    'post_as'    => array('newstuff.gif', $_LANG['FWA_POST_AS']),
    'ads'        => array('banners.gif', $_LANG['FWA_ADS']),
    'gifs'       => array('images.gif', $_LANG['FWA_GIFS']),
    'bgs'        => array('folders.gif', $_LANG['FWA_BGS']),
    'rss'        => array('rss.gif', $_LANG['FWA_RSS']),
    'tools'      => array('tools.gif', $_LANG['FWA_TOOLS']),
    'config'     => array('config.gif', $_LANG['AD_SETTINGS'])
);
$toolmenu = array();
foreach($menu as $k => $m){
    if (!file_exists(PATH.'/admin/images/toolmenu/'.$m[0])) { $m[0] = 'liststuff.gif'; }
    $toolmenu[] = array('icon' => $m[0], 'title' => $m[1], 'link' => $base.'&opt='.$k);
}
if ($opt == 'config'){
    $toolmenu[] = array('icon' => 'save.gif', 'title' => $_LANG['SAVE'], 'link' => 'javascript:document.optform.submit();');
}
cpToolMenu($toolmenu);

$msg = '';
$is_post = $_SERVER['REQUEST_METHOD'] == 'POST';

// ---------------------------------------------------------------- действия (только POST + CSRF)
$actions = array('saveconfig','post_delete','post_toggle','post_pin_feed','comment_delete','complaint_close','complaint_delete_content',
                 'do_post_as','ad_save','ad_delete','ad_toggle','gifcat_save','gifcat_delete','gif_save','gif_delete',
                 'bg_save','bg_delete','bg_toggle','rss_save','rss_delete','rss_import','tool_recount','tool_cleanup','tool_sync','tool_schema');

if (in_array($opt, $actions, true)){

    if (!$is_post || !cmsUser::checkCsrfToken()) { cmsCore::error404(); }

    switch($opt){

    case 'saveconfig':
        $new = array();
        foreach(feedwall_default_config() as $k => $def){
            $v = isset($_POST[$k]) ? $_POST[$k] : (is_int($def) ? 0 : '');
            if (is_array($v)) { $v = ''; }
            $new[$k] = is_int($def) ? (int)$v : mb_substr(trim(strip_tags((string)$v)), 0, 1000);
        }
        foreach(array('infinite_type'=>array('scroll','button'), 'comm_sort'=>array('asc','desc'), 'gif_provider'=>array('local','tenor'),
                      'mention_source'=>array('users','friends')) as $k => $allowed){
            if (!in_array($new[$k], $allowed, true)) { $new[$k] = $allowed[0]; }
        }
        foreach(array('main_sort','main_sort_guest') as $k){
            if (!in_array($new[$k], array('all','friends','popular','discussed','viewed'), true)) { $new[$k] = 'all'; }
        }
        $new['ads_url'] = fwa_url($new['ads_url']);
        $new['perpage'] = max(1, min(100, $new['perpage']));
        $inCore->saveComponentConfig('feedwall', $new);
        cmsCore::addSessionMessage($_LANG['AD_CONFIG_SAVE_SUCCESS'], 'success');
        cmsCore::redirect($base.'&opt=config');

    case 'post_delete':
        foreach(fwa_ids() as $pid){ $model->deletePost($pid); }
        cmsCore::addSessionMessage($_LANG['FWA_DONE'], 'success');
        cmsCore::redirect($base.'&opt=posts');

    case 'post_toggle':
        foreach(fwa_ids() as $pid){ $inDB->query("UPDATE cms_feedwall_posts SET is_pub = 1 - is_pub WHERE id = '{$pid}'"); }
        cmsCore::redirect($base.'&opt=posts');

    case 'post_pin_feed':
        foreach(fwa_ids() as $pid){ $inDB->query("UPDATE cms_feedwall_posts SET is_pinned_feed = 1 - is_pinned_feed WHERE id = '{$pid}'"); }
        cmsCore::redirect($base.'&opt=posts');

    case 'comment_delete':
        foreach(fwa_ids() as $cid){ $model->deleteComment($cid); }
        cmsCore::addSessionMessage($_LANG['FWA_DONE'], 'success');
        cmsCore::redirect($base.'&opt=comments');

    case 'complaint_close':
        foreach(fwa_ids() as $cid){ $inDB->query("UPDATE cms_feedwall_complaints SET is_closed = 1 WHERE id = '{$cid}'"); }
        cmsCore::redirect($base.'&opt=complaints');

    case 'complaint_delete_content':
        foreach(fwa_ids() as $cid){
            $c = $inDB->get_fields('cms_feedwall_complaints', "id = '{$cid}'", '*');
            if (!$c) { continue; }
            if ($c['comment_id']) { $model->deleteComment($c['comment_id']); } else { $model->deletePost($c['post_id']); }
            $inDB->query("UPDATE cms_feedwall_complaints SET is_closed = 1 WHERE id = '{$cid}'");
        }
        cmsCore::addSessionMessage($_LANG['FWA_DONE'], 'success');
        cmsCore::redirect($base.'&opt=complaints');

    case 'do_post_as':
        $login = fwa_in('login');
        $uid = $login !== '' ? (int)$inDB->get_field('cms_users', "login = '".$inDB->escape_string($login)."' AND is_deleted = 0", 'id') : (int)$inUser->id;
        $text = fw_clean_text(isset($_POST['text']) ? (string)$_POST['text'] : '', 20000);
        if (!$uid || $text === '') { cmsCore::addSessionMessage($_LANG['FWA_POST_AS_ERR'], 'error'); cmsCore::redirect($base.'&opt=post_as'); }
        $tt = in_array(fwa_in('target_type'), array('feed','user','club'), true) ? fwa_in('target_type') : 'feed';
        $data = array('user_id' => $uid, 'text' => $text, 'target_type' => $tt, 'target_id' => (int)fwa_in('target_id', 0));
        $link = fwa_url(fwa_in('link_url'));
        if ($link && preg_match('#^https?://#i', $link)){
            $video = fw_parse_video($link);
            if ($video) { $data['videos'] = array($video['url']); }
            else { $lp = fw_link_preview($link); if ($lp) { $data['link'] = $lp; } }
        }
        $date = fwa_in('date_pub');
        if ($date !== '' && strtotime($date)) { $data['date_pub'] = date('Y-m-d H:i:s', strtotime($date)); }
        $pid = $model->addPost($data);
        if ($pid && (int)fwa_in('pin_feed', 0)) { $model->setPinned($pid, 'is_pinned_feed', 1); }
        cmsCore::addSessionMessage($pid ? $_LANG['FWA_POST_AS_OK'].' <a href="/feedwall/post/'.$pid.'" target="_blank">#'.$pid.'</a>' : $_LANG['FWA_POST_AS_ERR'], $pid ? 'success' : 'error');
        cmsCore::redirect($base.'&opt=posts');

    case 'ad_save':
        $aid = (int)fwa_in('ad_id', 0);
        $row = array(
            'title'      => $inDB->escape_string(fw_clean_text(fwa_in('title'), 255)),
            'text'       => $inDB->escape_string(fw_clean_text(isset($_POST['text']) ? (string)$_POST['text'] : '', 2000)),
            'url'        => $inDB->escape_string(fwa_url(fwa_in('url'))),
            'position'   => max(1, (int)fwa_in('position', 3)),
            'max_views'  => max(0, (int)fwa_in('max_views', 0)),
            'max_clicks' => max(0, (int)fwa_in('max_clicks', 0)),
            'is_pub'     => (int)fwa_in('is_pub', 0) ? 1 : 0,
            'user_id'    => (int)$inDB->get_field('cms_users', "login = '".$inDB->escape_string(fwa_in('owner'))."'", 'id')
        );
        $img = fwa_upload_image('image_file', 'ads', 800);
        $row['image'] = $inDB->escape_string($img ? $img : fwa_url(fwa_in('image')));
        foreach(array('date_start', 'date_end') as $k){ $d = fwa_in($k); $row[$k] = ($d !== '' && strtotime($d)) ? date('Y-m-d', strtotime($d)) : null; }
        if ($aid){
            $set = array();
            foreach($row as $k => $v){ $set[] = "`{$k}` = ".($v === null ? 'NULL' : "'{$v}'"); }
            $inDB->query("UPDATE cms_feedwall_ads SET ".implode(', ', $set)." WHERE id = '{$aid}'");
        } else {
            $cols = array(); $vals = array();
            foreach($row as $k => $v){ $cols[] = "`{$k}`"; $vals[] = $v === null ? 'NULL' : "'{$v}'"; }
            $inDB->query("INSERT INTO cms_feedwall_ads (".implode(',', $cols).", date_pub) VALUES (".implode(',', $vals).", '".date('Y-m-d H:i:s')."')");
        }
        cmsCore::addSessionMessage($_LANG['FWA_DONE'], 'success');
        cmsCore::redirect($base.'&opt=ads');

    case 'ad_delete':
        foreach(fwa_ids() as $aid){
            $inDB->query("DELETE FROM cms_feedwall_ads WHERE id = '{$aid}'");
            $inDB->query("DELETE FROM cms_feedwall_ads_log WHERE ad_id = '{$aid}'");
        }
        cmsCore::redirect($base.'&opt=ads');

    case 'ad_toggle':
        foreach(fwa_ids() as $aid){ $inDB->query("UPDATE cms_feedwall_ads SET is_pub = 1 - is_pub WHERE id = '{$aid}'"); }
        cmsCore::redirect($base.'&opt=ads');

    case 'gifcat_save':
        $title = fw_clean_text(fwa_in('title'), 100);
        if ($title !== ''){
            $inDB->query("INSERT INTO cms_feedwall_gif_cats (title, ordering) VALUES ('".$inDB->escape_string($title)."', '".(int)fwa_in('ordering', 0)."')");
        }
        cmsCore::redirect($base.'&opt=gifs');

    case 'gifcat_delete':
        foreach(fwa_ids() as $cid){
            $inDB->query("DELETE FROM cms_feedwall_gif_cats WHERE id = '{$cid}'");
            $inDB->query("UPDATE cms_feedwall_gifs SET cat_id = 0 WHERE cat_id = '{$cid}'");
        }
        cmsCore::redirect($base.'&opt=gifs');

    case 'gif_save':
        $url = fwa_upload_image('gif_file', 'gifs', 0);
        if (!$url) { $url = fwa_url(fwa_in('url')); }
        if ($url){
            $inDB->query("INSERT INTO cms_feedwall_gifs (cat_id, title, url, ordering) VALUES ('".(int)fwa_in('cat_id', 0)."', '".$inDB->escape_string(fw_clean_text(fwa_in('title'), 255))."', '".$inDB->escape_string($url)."', '".(int)fwa_in('ordering', 0)."')");
            cmsCore::addSessionMessage($_LANG['FWA_DONE'], 'success');
        } else {
            cmsCore::addSessionMessage($_LANG['FWA_GIF_ERR'], 'error');
        }
        cmsCore::redirect($base.'&opt=gifs');

    case 'gif_delete':
        foreach(fwa_ids() as $gid){
            $g = $inDB->get_fields('cms_feedwall_gifs', "id = '{$gid}'", 'url');
            if ($g && strpos($g['url'], '/upload/feedwall/gifs/') === 0 && strpos($g['url'], '..') === false) { @unlink(PATH.$g['url']); }
            $inDB->query("DELETE FROM cms_feedwall_gifs WHERE id = '{$gid}'");
        }
        cmsCore::redirect($base.'&opt=gifs');

    case 'bg_save':
        $bid = (int)fwa_in('bg_id', 0);
        $css = fwa_in('css');
        // только безопасные CSS-свойства фона
        if (!preg_match('/^background(-image|-color)?\s*:\s*[#(),.%\w\s-]+;?$/i', $css) || preg_match('/url\s*\(|expression|javascript/i', $css)) {
            cmsCore::addSessionMessage($_LANG['FWA_BG_ERR'], 'error'); cmsCore::redirect($base.'&opt=bgs');
        }
        $color = preg_match('/^#[0-9a-f]{3,8}$/i', fwa_in('color')) ? fwa_in('color') : '#ffffff';
        $title = $inDB->escape_string(fw_clean_text(fwa_in('title'), 100));
        $css = $inDB->escape_string(rtrim($css, ';'));
        $ord = (int)fwa_in('ordering', 0);
        if ($bid) { $inDB->query("UPDATE cms_feedwall_backgrounds SET title = '{$title}', css = '{$css}', color = '{$color}', ordering = '{$ord}' WHERE id = '{$bid}'"); }
        else { $inDB->query("INSERT INTO cms_feedwall_backgrounds (title, css, color, ordering, is_pub) VALUES ('{$title}', '{$css}', '{$color}', '{$ord}', 1)"); }
        cmsCore::redirect($base.'&opt=bgs');

    case 'bg_delete':
        foreach(fwa_ids() as $bid){
            $inDB->query("DELETE FROM cms_feedwall_backgrounds WHERE id = '{$bid}'");
            $inDB->query("UPDATE cms_feedwall_posts SET bg_id = 0 WHERE bg_id = '{$bid}'");
        }
        cmsCore::redirect($base.'&opt=bgs');

    case 'bg_toggle':
        foreach(fwa_ids() as $bid){ $inDB->query("UPDATE cms_feedwall_backgrounds SET is_pub = 1 - is_pub WHERE id = '{$bid}'"); }
        cmsCore::redirect($base.'&opt=bgs');

    case 'rss_save':
        $rid = (int)fwa_in('rss_id', 0);
        $url = fwa_url(fwa_in('url'));
        if (!preg_match('#^https?://#i', $url)) { cmsCore::addSessionMessage($_LANG['FWA_RSS_ERR'], 'error'); cmsCore::redirect($base.'&opt=rss'); }
        $uid = (int)$inDB->get_field('cms_users', "login = '".$inDB->escape_string(fwa_in('login'))."'", 'id');
        if (!$uid) { $uid = (int)$cfg['rss_user_id'] ? (int)$cfg['rss_user_id'] : (int)$inUser->id; }
        $row = "title = '".$inDB->escape_string(fw_clean_text(fwa_in('title'), 255))."', url = '".$inDB->escape_string($url)."', user_id = '{$uid}',
                items_limit = '".max(1, min(50, (int)fwa_in('items_limit', 5)))."', with_images = '".((int)fwa_in('with_images', 0) ? 1 : 0)."',
                is_auto = '".((int)fwa_in('is_auto', 0) ? 1 : 0)."'";
        if ($rid) { $inDB->query("UPDATE cms_feedwall_rss SET {$row} WHERE id = '{$rid}'"); }
        else { $inDB->query("INSERT INTO cms_feedwall_rss SET {$row}"); }
        cmsCore::redirect($base.'&opt=rss');

    case 'rss_delete':
        foreach(fwa_ids() as $rid){ $inDB->query("DELETE FROM cms_feedwall_rss WHERE id = '{$rid}'"); }
        cmsCore::redirect($base.'&opt=rss');

    case 'rss_import':
        $total = 0;
        foreach(fwa_ids() as $rid){
            $src = $inDB->get_fields('cms_feedwall_rss', "id = '{$rid}'", '*');
            if ($src) { $n = $model->importRssItems($src); $total += (int)$n; }
        }
        cmsCore::addSessionMessage(sprintf($_LANG['FWA_RSS_IMPORTED'], $total), 'success');
        cmsCore::redirect($base.'&opt=rss');

    case 'tool_recount':
        $model->recountAll();
        cmsCore::addSessionMessage($_LANG['FWA_DONE'], 'success');
        cmsCore::redirect($base.'&opt=tools');

    case 'tool_cleanup':
        $n = $model->cleanup();
        cmsCore::addSessionMessage($_LANG['FWA_DONE'].' ('.$n.')', 'success');
        cmsCore::redirect($base.'&opt=tools');

    case 'tool_sync':
        $n = $model->syncActivity(true);
        cmsCore::addSessionMessage($_LANG['FWA_DONE'].' (+'.$n.')', 'success');
        cmsCore::redirect($base.'&opt=tools');

    case 'tool_schema':
        cmsCore::includeFile('components/feedwall/includes/schema.php');
        $r = feedwall_schema_install();
        feedwall_install_extensions();
        cmsCore::addSessionMessage($r === true ? $_LANG['FWA_DONE'] : fwa_h($r), $r === true ? 'success' : 'error');
        cmsCore::redirect($base.'&opt=tools');

    }

}

//============================================================================//
//                               ЭКРАНЫ                                       //
//============================================================================//

$tok  = fwa_token_field();
$page = max(1, (int)cmsCore::request('page', 'int', 1));
$perpage = 30;

echo '<style>
.fwa-table{width:100%;border-collapse:collapse;margin:10px 0}
.fwa-table th,.fwa-table td{border:1px solid #ddd;padding:5px 7px;vertical-align:top;font-size:12px}
.fwa-table th{background:#f2f4f7;text-align:left}
.fwa-table tr.off td{opacity:.55}
.fwa-box{background:#fafbfc;border:1px solid #e3e6ea;padding:10px 14px;margin:10px 0}
.fwa-box h3{margin:0 0 8px;font-size:14px}
.fwa-form td{padding:4px 8px 4px 0;vertical-align:top}
.fwa-form input[type=text],.fwa-form textarea,.fwa-form select{width:360px;box-sizing:border-box}
.fwa-ok{color:#2e7d32;font-weight:bold}.fwa-bad{color:#c62828;font-weight:bold}
.fwa-pager a,.fwa-pager b{margin-right:6px}
.fwa-bg{display:inline-block;width:60px;height:30px;border-radius:4px;vertical-align:middle}
.fwa-tabs a{display:inline-block;padding:4px 10px;margin-right:4px;background:#eef1f4;border-radius:3px;text-decoration:none}
.fwa-tabs a.on{background:#3b6ea5;color:#fff}
</style>';

function fwa_pager($base, $opt, $total, $page, $perpage, $extra = ''){
    $pages = (int)ceil($total / $perpage);
    if ($pages < 2) { return ''; }
    $h = '<div class="fwa-pager">';
    for($i = 1; $i <= $pages; $i++){
        $h .= $i == $page ? '<b>'.$i.'</b>' : '<a href="'.$base.'&amp;opt='.$opt.$extra.'&amp;page='.$i.'">'.$i.'</a>';
    }
    return $h.'</div>';
}

function fwa_bulk($base, $opts){
    $h = '<div style="margin:6px 0">';
    foreach($opts as $o => $l){
        $h .= '<input type="submit" value="'.fwa_h($l[0]).'" onclick="this.form.action=\''.$base.'&amp;opt='.$o.'\';'.($l[1] ? 'return confirm(\''.fwa_h(addslashes($l[1])).'\')' : 'return true').'" /> ';
    }
    return $h.'</div>';
}

$checkall = '<input type="checkbox" onclick="var c=this.checked;jQuery(this).closest(\'table\').find(\'input[name^=item]\').prop(\'checked\',c)" />';

switch($opt){

// ---------------------------------------------------------------- записи
case 'posts':
    cpAddPathway($_LANG['FWA_POSTS']);
    $q    = trim((string)cmsCore::request('q', 'str', ''));
    $type = cmsCore::request('type', array('', 'post', 'share', 'rss', 'activity'), '');
    $where = '1=1';
    if ($type) { $where .= " AND p.type = '{$type}'"; }
    if ($q !== '') {
        $qe = $inDB->escape_string(str_replace(array('%','_'), array('\%','\_'), $q));
        $where .= is_numeric($q) ? " AND p.id = '".(int)$q."'" : " AND (p.text LIKE '%{$qe}%' OR u.login LIKE '%{$qe}%' OR u.nickname LIKE '%{$qe}%')";
    }
    $total = (int)$inDB->get_field('cms_feedwall_posts p LEFT JOIN cms_users u ON u.id = p.user_id', $where, 'COUNT(p.id)');
    $from = ($page - 1) * $perpage;
    $res = $inDB->query("SELECT p.*, u.login, u.nickname FROM cms_feedwall_posts p LEFT JOIN cms_users u ON u.id = p.user_id
                         WHERE {$where} ORDER BY p.id DESC LIMIT {$from}, {$perpage}");
    echo '<form method="get" action="index.php" class="fwa-box"><input type="hidden" name="view" value="components" /><input type="hidden" name="do" value="config" />
          <input type="hidden" name="id" value="'.(int)$id.'" /><input type="hidden" name="opt" value="posts" />
          '.$_LANG['FWA_SEARCH'].': <input type="text" name="q" value="'.fwa_h($q).'" /> <select name="type"><option value="">'.$_LANG['FWA_ALL_TYPES'].'</option>';
    foreach(array('post','share','rss','activity') as $t){ echo '<option value="'.$t.'"'.($type == $t ? ' selected' : '').'>'.$_LANG['FWA_TYPE_'.strtoupper($t)].'</option>'; }
    echo '</select> <input type="submit" value="'.$_LANG['FWA_FIND'].'" /> &nbsp; '.$_LANG['FWA_TOTAL'].': <b>'.$total.'</b></form>';
    echo '<form method="post" action="'.$base.'&amp;opt=post_delete">'.$tok;
    echo '<table class="fwa-table"><tr><th width="20">'.$checkall.'</th><th width="40">ID</th><th width="110">'.$_LANG['FWA_DATE'].'</th><th width="130">'.$_LANG['FWA_AUTHOR'].'</th><th width="90">'.$_LANG['FWA_WHERE'].'</th><th>'.$_LANG['FWA_TEXT'].'</th><th width="110">'.$_LANG['FWA_STATS'].'</th></tr>';
    $n = 0;
    while($p = $inDB->fetch_assoc($res)){
        $n++;
        $author = $p['user_id'] ? '<a href="/users/'.fwa_h($p['login']).'" target="_blank">'.fwa_h($p['nickname']).'</a>' : fwa_h($p['guest_name']).' ('.$_LANG['FWA_GUEST'].')';
        if ($p['is_anonymous']) { $author .= ' <i>'.$_LANG['FWA_ANON'].'</i>'; }
        $where_s = $p['target_type'] == 'feed' ? $_LANG['FWA_FEED'] : ($p['target_type'] == 'user' ? $_LANG['FWA_WALL_USER'].' #'.(int)$p['target_id'] : $_LANG['FWA_WALL_CLUB'].' #'.(int)$p['target_id']);
        $text = $p['type'] == 'activity' ? '<i>'.$_LANG['FWA_TYPE_ACTIVITY'].' #'.(int)$p['action_log_id'].'</i>' : fwa_h(mb_substr(html_entity_decode($p['text'], ENT_QUOTES, 'UTF-8'), 0, 200));
        $flags = array();
        if (!$p['is_pub']) { $flags[] = $_LANG['FWA_HIDDEN']; }
        if ($p['is_pinned_feed']) { $flags[] = $_LANG['FWA_PINNED']; }
        if ($p['is_private']) { $flags[] = $_LANG['FWA_PRIVATE']; }
        if ($p['attach']) { $flags[] = $_LANG['FWA_ATTACH']; }
        if ($p['poll_id']) { $flags[] = $_LANG['FWA_POLL']; }
        echo '<tr'.($p['is_pub'] ? '' : ' class="off"').'><td><input type="checkbox" name="item[]" value="'.(int)$p['id'].'" /></td>
              <td><a href="/feedwall/post/'.(int)$p['id'].'" target="_blank">'.(int)$p['id'].'</a></td><td>'.fwa_date($p['date_pub']).'</td>
              <td>'.$author.'</td><td>'.$where_s.'<br/><small>'.$_LANG['FWA_TYPE_'.strtoupper($p['type'])].'</small></td>
              <td>'.$text.($flags ? '<br/><small>['.implode(', ', $flags).']</small>' : '').'</td>
              <td><small>&#9829; '.(int)$p['likes_count'].' &middot; &#128172; '.(int)$p['comments_count'].'<br/>&#8635; '.(int)$p['shares_count'].' &middot; &#128065; '.(int)$p['views_count'].'</small></td></tr>';
    }
    if (!$n) { echo '<tr><td colspan="7">'.$_LANG['FWA_EMPTY'].'</td></tr>'; }
    echo '</table>';
    echo fwa_bulk($base, array('post_delete' => array($_LANG['FWA_DELETE_SEL'], $_LANG['FWA_CONFIRM']), 'post_toggle' => array($_LANG['FWA_TOGGLE_PUB'], ''), 'post_pin_feed' => array($_LANG['FWA_TOGGLE_PIN'], '')));
    echo '</form>'.fwa_pager($base, 'posts', $total, $page, $perpage, '&amp;q='.urlencode($q).'&amp;type='.$type);
    break;

// ---------------------------------------------------------------- комментарии
case 'comments':
    cpAddPathway($_LANG['FWA_COMMENTS']);
    $total = (int)$inDB->rows_count('cms_feedwall_comments', '1=1');
    $from = ($page - 1) * $perpage;
    $res = $inDB->query("SELECT c.*, u.login, u.nickname FROM cms_feedwall_comments c LEFT JOIN cms_users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT {$from}, {$perpage}");
    echo '<form method="post" action="'.$base.'&amp;opt=comment_delete">'.$tok;
    echo '<table class="fwa-table"><tr><th width="20">'.$checkall.'</th><th width="40">ID</th><th width="110">'.$_LANG['FWA_DATE'].'</th><th width="130">'.$_LANG['FWA_AUTHOR'].'</th><th width="70">'.$_LANG['FWA_POST'].'</th><th>'.$_LANG['FWA_TEXT'].'</th></tr>';
    $n = 0;
    while($c = $inDB->fetch_assoc($res)){
        $n++;
        echo '<tr><td><input type="checkbox" name="item[]" value="'.(int)$c['id'].'" /></td><td>'.(int)$c['id'].($c['parent_id'] ? '<br/><small>&#8627; '.(int)$c['parent_id'].'</small>' : '').'</td>
              <td>'.fwa_date($c['date_pub']).'</td><td><a href="/users/'.fwa_h($c['login']).'" target="_blank">'.fwa_h($c['nickname']).'</a></td>
              <td><a href="/feedwall/post/'.(int)$c['post_id'].'#fw-comment-'.(int)$c['id'].'" target="_blank">#'.(int)$c['post_id'].'</a></td>
              <td>'.fwa_h(mb_substr(html_entity_decode($c['text'], ENT_QUOTES, 'UTF-8'), 0, 300)).($c['attach'] ? ' <small>['.$_LANG['FWA_ATTACH'].']</small>' : '').'</td></tr>';
    }
    if (!$n) { echo '<tr><td colspan="6">'.$_LANG['FWA_EMPTY'].'</td></tr>'; }
    echo '</table>'.fwa_bulk($base, array('comment_delete' => array($_LANG['FWA_DELETE_SEL'], $_LANG['FWA_CONFIRM']))).'</form>';
    echo fwa_pager($base, 'comments', $total, $page, $perpage);
    break;

// ---------------------------------------------------------------- жалобы
case 'complaints':
    cpAddPathway($_LANG['FWA_COMPLAINTS']);
    $all = (int)cmsCore::request('all', 'int', 0);
    echo '<div class="fwa-tabs"><a href="'.$base.'&amp;opt=complaints"'.($all ? '' : ' class="on"').'>'.$_LANG['FWA_OPEN'].'</a><a href="'.$base.'&amp;opt=complaints&amp;all=1"'.($all ? ' class="on"' : '').'>'.$_LANG['FWA_ALL'].'</a></div>';
    echo '<form method="post" action="'.$base.'&amp;opt=complaint_close">'.$tok;
    echo '<table class="fwa-table"><tr><th width="20">'.$checkall.'</th><th width="110">'.$_LANG['FWA_DATE'].'</th><th width="130">'.$_LANG['FWA_FROM'].'</th><th width="120">'.$_LANG['FWA_OBJECT'].'</th><th>'.$_LANG['FWA_REASON'].'</th><th width="70">'.$_LANG['FWA_STATUS'].'</th></tr>';
    $list = $model->getComplaints(!$all);
    foreach($list as $c){
        $obj = '<a href="/feedwall/post/'.(int)$c['post_id'].($c['comment_id'] ? '#fw-comment-'.(int)$c['comment_id'] : '').'" target="_blank">'.($c['comment_id'] ? $_LANG['FWA_COMMENT'].' #'.(int)$c['comment_id'] : $_LANG['FWA_POST'].' #'.(int)$c['post_id']).'</a>';
        echo '<tr'.($c['is_closed'] ? ' class="off"' : '').'><td><input type="checkbox" name="item[]" value="'.(int)$c['id'].'" /></td><td>'.fwa_date($c['date_pub']).'</td>
              <td>'.($c['user_id'] ? fwa_h($c['nickname']) : $_LANG['FWA_GUEST']).'<br/><small>'.fwa_h($c['ip']).'</small></td><td>'.$obj.'</td>
              <td>'.fwa_h(html_entity_decode($c['reason'], ENT_QUOTES, 'UTF-8')).'</td><td>'.($c['is_closed'] ? $_LANG['FWA_CLOSED'] : '<span class="fwa-bad">'.$_LANG['FWA_OPEN'].'</span>').'</td></tr>';
    }
    if (!$list) { echo '<tr><td colspan="6">'.$_LANG['FWA_EMPTY'].'</td></tr>'; }
    echo '</table>'.fwa_bulk($base, array('complaint_close' => array($_LANG['FWA_CLOSE_SEL'], ''), 'complaint_delete_content' => array($_LANG['FWA_DELETE_CONTENT'], $_LANG['FWA_CONFIRM']))).'</form>';
    break;

// ---------------------------------------------------------------- запись от имени
case 'post_as':
    cpAddPathway($_LANG['FWA_POST_AS']);
    echo '<div class="fwa-box"><p>'.$_LANG['FWA_POST_AS_HINT'].'</p><form method="post" action="'.$base.'&amp;opt=do_post_as">'.$tok.'<table class="fwa-form">
          <tr><td>'.$_LANG['FWA_LOGIN'].'</td><td><input type="text" name="login" value="'.fwa_h($inUser->login).'" /></td></tr>
          <tr><td>'.$_LANG['FWA_WHERE'].'</td><td><select name="target_type" style="width:170px"><option value="feed">'.$_LANG['FWA_FEED'].'</option><option value="user">'.$_LANG['FWA_WALL_USER'].'</option><option value="club">'.$_LANG['FWA_WALL_CLUB'].'</option></select>
              ID: <input type="text" name="target_id" value="0" style="width:80px" /></td></tr>
          <tr><td>'.$_LANG['FWA_TEXT'].'</td><td><textarea name="text" rows="6"></textarea></td></tr>
          <tr><td>'.$_LANG['FWA_LINK_OR_VIDEO'].'</td><td><input type="text" name="link_url" placeholder="https://" /></td></tr>
          <tr><td>'.$_LANG['FWA_DATE'].'</td><td><input type="text" name="date_pub" placeholder="'.date('Y-m-d H:i').'" style="width:170px" /></td></tr>
          <tr><td></td><td><label><input type="checkbox" name="pin_feed" value="1" /> '.$_LANG['FWA_PIN_FEED'].'</label></td></tr>
          <tr><td></td><td><input type="submit" value="'.$_LANG['FWA_PUBLISH'].'" /></td></tr></table></form></div>';
    break;

// ---------------------------------------------------------------- реклама
case 'ads':
    cpAddPathway($_LANG['FWA_ADS']);
    $edit_id = (int)cmsCore::request('edit', 'int', 0);
    $stat_id = (int)cmsCore::request('stat', 'int', 0);
    $ad = $edit_id ? $inDB->get_fields('cms_feedwall_ads', "id = '{$edit_id}'", '*') : false;
    if ($stat_id){
        $a = $inDB->get_fields('cms_feedwall_ads', "id = '{$stat_id}'", '*');
        echo '<div class="fwa-box"><h3>'.$_LANG['FWA_AD_STATS'].': '.fwa_h($a ? $a['title'] : '').'</h3><table class="fwa-table" style="width:auto"><tr><th>'.$_LANG['FWA_DATE'].'</th><th>'.$_LANG['FWA_VIEWS'].'</th><th>'.$_LANG['FWA_CLICKS'].'</th><th>CTR</th></tr>';
        $st = $model->getAdStats($stat_id, 30);
        foreach($st as $s){
            $v = (int)@$s['view']; $c = (int)@$s['click'];
            echo '<tr><td>'.fwa_h($s['date']).'</td><td>'.$v.'</td><td>'.$c.'</td><td>'.($v ? round($c * 100 / $v, 2) : 0).'%</td></tr>';
        }
        if (!$st) { echo '<tr><td colspan="4">'.$_LANG['FWA_EMPTY'].'</td></tr>'; }
        echo '</table></div>';
    }
    echo '<p>'.$_LANG['FWA_ADS_HINT'].'</p>';
    echo '<form method="post" action="'.$base.'&amp;opt=ad_delete">'.$tok.'<table class="fwa-table"><tr><th width="20">'.$checkall.'</th><th>'.$_LANG['FWA_TITLE'].'</th><th width="70">'.$_LANG['FWA_POSITION'].'</th><th width="120">'.$_LANG['FWA_PERIOD'].'</th><th width="110">'.$_LANG['FWA_VIEWS'].'</th><th width="110">'.$_LANG['FWA_CLICKS'].'</th><th width="60">CTR</th><th width="120"></th></tr>';
    $ads = $inDB->get_table('cms_feedwall_ads', '1=1 ORDER BY id DESC');
    foreach(($ads ?: array()) as $a){
        echo '<tr'.($a['is_pub'] ? '' : ' class="off"').'><td><input type="checkbox" name="item[]" value="'.(int)$a['id'].'" /></td><td>'.fwa_h(html_entity_decode($a['title'], ENT_QUOTES, 'UTF-8')).'<br/><small>'.fwa_h($a['url']).'</small></td>
              <td>'.(int)$a['position'].'</td><td><small>'.($a['date_start'] ? fwa_h($a['date_start']) : '&hellip;').' &ndash; '.($a['date_end'] ? fwa_h($a['date_end']) : '&hellip;').'</small></td>
              <td>'.(int)$a['views_count'].($a['max_views'] ? ' / '.(int)$a['max_views'] : '').'</td><td>'.(int)$a['clicks_count'].($a['max_clicks'] ? ' / '.(int)$a['max_clicks'] : '').'</td>
              <td>'.($a['views_count'] ? round($a['clicks_count'] * 100 / $a['views_count'], 2) : 0).'%</td>
              <td><a href="'.$base.'&amp;opt=ads&amp;edit='.(int)$a['id'].'">'.$_LANG['FWA_EDIT'].'</a> | <a href="'.$base.'&amp;opt=ads&amp;stat='.(int)$a['id'].'">'.$_LANG['FWA_STATS'].'</a></td></tr>';
    }
    if (!$ads) { echo '<tr><td colspan="8">'.$_LANG['FWA_EMPTY'].'</td></tr>'; }
    echo '</table>'.fwa_bulk($base, array('ad_delete' => array($_LANG['FWA_DELETE_SEL'], $_LANG['FWA_CONFIRM']), 'ad_toggle' => array($_LANG['FWA_TOGGLE_PUB'], ''))).'</form>';
    $v = function($k, $d = '') use ($ad){ return fwa_h($ad ? html_entity_decode((string)$ad[$k], ENT_QUOTES, 'UTF-8') : $d); };
    $owner = $ad && $ad['user_id'] ? $inDB->get_field('cms_users', "id = '".(int)$ad['user_id']."'", 'login') : '';
    echo '<div class="fwa-box"><h3>'.($ad ? $_LANG['FWA_EDIT'].' #'.(int)$ad['id'] : $_LANG['FWA_ADD_AD']).'</h3>
          <form method="post" enctype="multipart/form-data" action="'.$base.'&amp;opt=ad_save">'.$tok.'<input type="hidden" name="ad_id" value="'.($ad ? (int)$ad['id'] : 0).'" /><table class="fwa-form">
          <tr><td>'.$_LANG['FWA_TITLE'].'</td><td><input type="text" name="title" value="'.$v('title').'" /></td></tr>
          <tr><td>'.$_LANG['FWA_TEXT'].'</td><td><textarea name="text" rows="3">'.$v('text').'</textarea></td></tr>
          <tr><td>URL</td><td><input type="text" name="url" value="'.$v('url').'" placeholder="https://" /></td></tr>
          <tr><td>'.$_LANG['FWA_IMAGE'].'</td><td><input type="text" name="image" value="'.$v('image').'" placeholder="https:// / /upload/..." /><br/><input type="file" name="image_file" accept="image/*" /></td></tr>
          <tr><td>'.$_LANG['FWA_POSITION'].'</td><td><input type="text" name="position" value="'.$v('position', '3').'" style="width:60px" /> <small>'.$_LANG['FWA_POSITION_HINT'].'</small></td></tr>
          <tr><td>'.$_LANG['FWA_LIMITS'].'</td><td>'.$_LANG['FWA_VIEWS'].' <input type="text" name="max_views" value="'.$v('max_views', '0').'" style="width:70px" /> '.$_LANG['FWA_CLICKS'].' <input type="text" name="max_clicks" value="'.$v('max_clicks', '0').'" style="width:70px" /> <small>0 = &infin;</small></td></tr>
          <tr><td>'.$_LANG['FWA_PERIOD'].'</td><td><input type="text" name="date_start" value="'.$v('date_start').'" placeholder="YYYY-MM-DD" style="width:110px" /> &ndash; <input type="text" name="date_end" value="'.$v('date_end').'" placeholder="YYYY-MM-DD" style="width:110px" /></td></tr>
          <tr><td>'.$_LANG['FWA_OWNER'].'</td><td><input type="text" name="owner" value="'.fwa_h($owner).'" style="width:170px" /> <small>'.$_LANG['FWA_OWNER_HINT'].'</small></td></tr>
          <tr><td></td><td><label><input type="checkbox" name="is_pub" value="1"'.(!$ad || $ad['is_pub'] ? ' checked' : '').' /> '.$_LANG['FWA_PUBLISHED'].'</label></td></tr>
          <tr><td></td><td><input type="submit" value="'.$_LANG['SAVE'].'" /></td></tr></table></form></div>';
    break;

// ---------------------------------------------------------------- GIF
case 'gifs':
    cpAddPathway($_LANG['FWA_GIFS']);
    $cats = $model->getGifCats();
    echo '<p>'.$_LANG['FWA_GIFS_HINT'].'</p><div class="fwa-box"><h3>'.$_LANG['FWA_GIF_CATS'].'</h3><form method="post" action="'.$base.'&amp;opt=gifcat_delete">'.$tok.'<table class="fwa-table" style="width:auto"><tr><th>'.$checkall.'</th><th>ID</th><th>'.$_LANG['FWA_TITLE'].'</th><th>'.$_LANG['FWA_ORDER'].'</th><th>GIF</th></tr>';
    $catmap = array();
    foreach(($cats ?: array()) as $c){
        $catmap[$c['id']] = $c['title'];
        echo '<tr><td><input type="checkbox" name="item[]" value="'.(int)$c['id'].'" /></td><td>'.(int)$c['id'].'</td><td>'.fwa_h(html_entity_decode($c['title'], ENT_QUOTES, 'UTF-8')).'</td><td>'.(int)$c['ordering'].'</td><td>'.(int)$inDB->rows_count('cms_feedwall_gifs', "cat_id = '".(int)$c['id']."'").'</td></tr>';
    }
    echo '</table>'.fwa_bulk($base, array('gifcat_delete' => array($_LANG['FWA_DELETE_SEL'], $_LANG['FWA_CONFIRM']))).'</form>
          <form method="post" action="'.$base.'&amp;opt=gifcat_save">'.$tok.$_LANG['FWA_TITLE'].': <input type="text" name="title" /> '.$_LANG['FWA_ORDER'].': <input type="text" name="ordering" value="0" style="width:40px" /> <input type="submit" value="'.$_LANG['FWA_ADD'].'" /></form></div>';
    echo '<div class="fwa-box"><h3>'.$_LANG['FWA_ADD_GIF'].'</h3><form method="post" enctype="multipart/form-data" action="'.$base.'&amp;opt=gif_save">'.$tok.'<table class="fwa-form">
          <tr><td>'.$_LANG['FWA_CATEGORY'].'</td><td><select name="cat_id"><option value="0">&mdash;</option>';
    foreach($catmap as $cid => $ct){ echo '<option value="'.(int)$cid.'">'.fwa_h(html_entity_decode($ct, ENT_QUOTES, 'UTF-8')).'</option>'; }
    echo '</select></td></tr><tr><td>'.$_LANG['FWA_TITLE'].' / '.$_LANG['FWA_KEYWORDS'].'</td><td><input type="text" name="title" /></td></tr>
          <tr><td>URL</td><td><input type="text" name="url" placeholder="https://...gif" /><br/>'.$_LANG['FWA_OR_FILE'].' <input type="file" name="gif_file" accept="image/gif,image/*" /></td></tr>
          <tr><td>'.$_LANG['FWA_ORDER'].'</td><td><input type="text" name="ordering" value="0" style="width:60px" /></td></tr>
          <tr><td></td><td><input type="submit" value="'.$_LANG['FWA_ADD'].'" /></td></tr></table></form></div>';
    $gifs = $inDB->get_table('cms_feedwall_gifs', '1=1 ORDER BY cat_id, ordering, id');
    echo '<form method="post" action="'.$base.'&amp;opt=gif_delete">'.$tok.'<div>';
    foreach(($gifs ?: array()) as $g){
        echo '<label style="display:inline-block;margin:4px;text-align:center;width:110px;vertical-align:top"><img src="'.fwa_h($g['url']).'" style="max-width:100px;max-height:80px" alt="" /><br/><input type="checkbox" name="item[]" value="'.(int)$g['id'].'" /> <small>'.fwa_h(html_entity_decode($g['title'], ENT_QUOTES, 'UTF-8')).'<br/>'.fwa_h(isset($catmap[$g['cat_id']]) ? html_entity_decode($catmap[$g['cat_id']], ENT_QUOTES, 'UTF-8') : '').'</small></label>';
    }
    if (!$gifs) { echo '<p>'.$_LANG['FWA_EMPTY'].'</p>'; }
    echo '</div>'.fwa_bulk($base, array('gif_delete' => array($_LANG['FWA_DELETE_SEL'], $_LANG['FWA_CONFIRM']))).'</form>';
    break;

// ---------------------------------------------------------------- фоны
case 'bgs':
    cpAddPathway($_LANG['FWA_BGS']);
    $edit_id = (int)cmsCore::request('edit', 'int', 0);
    $bg = $edit_id ? $inDB->get_fields('cms_feedwall_backgrounds', "id = '{$edit_id}'", '*') : false;
    $bgs = $model->getBackgrounds(false);
    echo '<form method="post" action="'.$base.'&amp;opt=bg_delete">'.$tok.'<table class="fwa-table" style="width:auto"><tr><th>'.$checkall.'</th><th>ID</th><th>'.$_LANG['FWA_PREVIEW'].'</th><th>'.$_LANG['FWA_TITLE'].'</th><th>CSS</th><th>'.$_LANG['FWA_ORDER'].'</th><th></th></tr>';
    foreach(($bgs ?: array()) as $b){
        echo '<tr'.($b['is_pub'] ? '' : ' class="off"').'><td><input type="checkbox" name="item[]" value="'.(int)$b['id'].'" /></td><td>'.(int)$b['id'].'</td><td><span class="fwa-bg" style="'.fwa_h($b['css']).'"></span></td>
              <td>'.fwa_h(html_entity_decode($b['title'], ENT_QUOTES, 'UTF-8')).'</td><td><small>'.fwa_h($b['css']).'</small></td><td>'.(int)$b['ordering'].'</td><td><a href="'.$base.'&amp;opt=bgs&amp;edit='.(int)$b['id'].'">'.$_LANG['FWA_EDIT'].'</a></td></tr>';
    }
    echo '</table>'.fwa_bulk($base, array('bg_delete' => array($_LANG['FWA_DELETE_SEL'], $_LANG['FWA_CONFIRM']), 'bg_toggle' => array($_LANG['FWA_TOGGLE_PUB'], ''))).'</form>';
    echo '<div class="fwa-box"><h3>'.($bg ? $_LANG['FWA_EDIT'].' #'.(int)$bg['id'] : $_LANG['FWA_ADD_BG']).'</h3><form method="post" action="'.$base.'&amp;opt=bg_save">'.$tok.'<input type="hidden" name="bg_id" value="'.($bg ? (int)$bg['id'] : 0).'" /><table class="fwa-form">
          <tr><td>'.$_LANG['FWA_TITLE'].'</td><td><input type="text" name="title" value="'.fwa_h($bg ? html_entity_decode($bg['title'], ENT_QUOTES, 'UTF-8') : '').'" /></td></tr>
          <tr><td>CSS</td><td><input type="text" name="css" value="'.fwa_h($bg ? $bg['css'] : 'background: linear-gradient(135deg, #ff9a9e, #fad0c4)').'" /><br/><small>'.$_LANG['FWA_BG_HINT'].'</small></td></tr>
          <tr><td>'.$_LANG['FWA_TEXT_COLOR'].'</td><td><input type="text" name="color" value="'.fwa_h($bg ? $bg['color'] : '#ffffff').'" style="width:100px" /></td></tr>
          <tr><td>'.$_LANG['FWA_ORDER'].'</td><td><input type="text" name="ordering" value="'.($bg ? (int)$bg['ordering'] : 0).'" style="width:60px" /></td></tr>
          <tr><td></td><td><input type="submit" value="'.$_LANG['SAVE'].'" /></td></tr></table></form></div>';
    break;

// ---------------------------------------------------------------- RSS
case 'rss':
    cpAddPathway($_LANG['FWA_RSS']);
    $edit_id = (int)cmsCore::request('edit', 'int', 0);
    $prev_id = (int)cmsCore::request('preview', 'int', 0);
    $src = $edit_id ? $inDB->get_fields('cms_feedwall_rss', "id = '{$edit_id}'", '*') : false;
    if ($prev_id){
        $p = $inDB->get_fields('cms_feedwall_rss', "id = '{$prev_id}'", '*');
        $items = $p ? $model->fetchRss($p['url'], (int)$p['items_limit'], (bool)$p['with_images']) : false;
        echo '<div class="fwa-box"><h3>'.$_LANG['FWA_RSS_PREVIEW'].': '.fwa_h($p ? $p['url'] : '').'</h3>';
        if ($items === false) { echo '<p class="fwa-bad">'.$_LANG['FWA_RSS_FAIL'].'</p>'; }
        foreach(($items ?: array()) as $it){
            echo '<p>'.($it['image'] ? '<img src="'.fwa_h($it['image']).'" style="max-width:80px;max-height:60px;float:left;margin-right:8px" alt="" />' : '').'<a href="'.fwa_h($it['url']).'" target="_blank" rel="noopener"><b>'.fwa_h(html_entity_decode($it['title'], ENT_QUOTES, 'UTF-8')).'</b></a><br/><small>'.fwa_h(mb_substr(html_entity_decode($it['desc'], ENT_QUOTES, 'UTF-8'), 0, 200)).'</small></p><div style="clear:both"></div>';
        }
        echo '</div>';
    }
    echo '<p>'.$_LANG['FWA_RSS_HINT'].'</p><form method="post" action="'.$base.'&amp;opt=rss_delete">'.$tok.'<table class="fwa-table"><tr><th width="20">'.$checkall.'</th><th>'.$_LANG['FWA_TITLE'].'</th><th>URL</th><th width="120">'.$_LANG['FWA_AUTHOR'].'</th><th width="60">'.$_LANG['FWA_LIMIT'].'</th><th width="60">'.$_LANG['FWA_AUTO'].'</th><th width="110">'.$_LANG['FWA_LAST'].'</th><th width="140"></th></tr>';
    $list = $model->getRssSources();
    foreach(($list ?: array()) as $r){
        $login = $inDB->get_field('cms_users', "id = '".(int)$r['user_id']."'", 'login');
        echo '<tr><td><input type="checkbox" name="item[]" value="'.(int)$r['id'].'" /></td><td>'.fwa_h(html_entity_decode($r['title'], ENT_QUOTES, 'UTF-8')).'</td><td><small>'.fwa_h($r['url']).'</small></td><td>'.fwa_h($login).'</td>
              <td>'.(int)$r['items_limit'].'</td><td>'.($r['is_auto'] ? '&#10004;' : '&mdash;').'</td><td>'.fwa_date($r['date_last']).'</td>
              <td><a href="'.$base.'&amp;opt=rss&amp;preview='.(int)$r['id'].'">'.$_LANG['FWA_PREVIEW'].'</a> | <a href="'.$base.'&amp;opt=rss&amp;edit='.(int)$r['id'].'">'.$_LANG['FWA_EDIT'].'</a></td></tr>';
    }
    if (!$list) { echo '<tr><td colspan="8">'.$_LANG['FWA_EMPTY'].'</td></tr>'; }
    echo '</table>'.fwa_bulk($base, array('rss_import' => array($_LANG['FWA_RSS_IMPORT'], ''), 'rss_delete' => array($_LANG['FWA_DELETE_SEL'], $_LANG['FWA_CONFIRM']))).'</form>';
    $login = $src ? $inDB->get_field('cms_users', "id = '".(int)$src['user_id']."'", 'login') : $inUser->login;
    echo '<div class="fwa-box"><h3>'.($src ? $_LANG['FWA_EDIT'].' #'.(int)$src['id'] : $_LANG['FWA_ADD_RSS']).'</h3><form method="post" action="'.$base.'&amp;opt=rss_save">'.$tok.'<input type="hidden" name="rss_id" value="'.($src ? (int)$src['id'] : 0).'" /><table class="fwa-form">
          <tr><td>'.$_LANG['FWA_TITLE'].'</td><td><input type="text" name="title" value="'.fwa_h($src ? html_entity_decode($src['title'], ENT_QUOTES, 'UTF-8') : '').'" /></td></tr>
          <tr><td>URL</td><td><input type="text" name="url" value="'.fwa_h($src ? $src['url'] : '').'" placeholder="https://example.com/rss" /></td></tr>
          <tr><td>'.$_LANG['FWA_LOGIN'].'</td><td><input type="text" name="login" value="'.fwa_h($login).'" style="width:170px" /></td></tr>
          <tr><td>'.$_LANG['FWA_LIMIT'].'</td><td><input type="text" name="items_limit" value="'.($src ? (int)$src['items_limit'] : 5).'" style="width:60px" /></td></tr>
          <tr><td></td><td><label><input type="checkbox" name="with_images" value="1"'.(!$src || $src['with_images'] ? ' checked' : '').' /> '.$_LANG['FWA_WITH_IMAGES'].'</label><br/>
              <label><input type="checkbox" name="is_auto" value="1"'.($src && $src['is_auto'] ? ' checked' : '').' /> '.$_LANG['FWA_RSS_AUTO'].'</label></td></tr>
          <tr><td></td><td><input type="submit" value="'.$_LANG['SAVE'].'" /></td></tr></table></form></div>';
    break;

// ---------------------------------------------------------------- инструменты
case 'tools':
    cpAddPathway($_LANG['FWA_TOOLS']);
    cmsCore::includeFile('components/feedwall/includes/schema.php');
    $rows = $model->checkTables();
    echo '<div class="fwa-box"><h3>'.$_LANG['FWA_DIAG'].'</h3><table class="fwa-table" style="width:auto"><tr><th>'.$_LANG['FWA_TABLE'].'</th><th>'.$_LANG['FWA_STATUS'].'</th><th>'.$_LANG['FWA_ROWS'].'</th></tr>';
    $bad = 0;
    foreach($rows as $r){
        if (!$r['ok']) { $bad++; }
        echo '<tr><td>'.fwa_h($r['table']).'</td><td>'.($r['ok'] ? '<span class="fwa-ok">OK</span>' : '<span class="fwa-bad">'.$_LANG['FWA_MISSING'].'</span>').'</td><td>'.(int)$r['rows'].'</td></tr>';
    }
    echo '</table>';
    $env = array(
        'PHP' => PHP_VERSION, 'MySQL' => $inDB->get_field('(SELECT VERSION() v) t', '1=1', 'v'),
        'GD' => function_exists('imagecreatetruecolor') ? 'OK' : '&mdash;', 'WebP' => function_exists('imagecreatefromwebp') ? 'OK' : '&mdash;',
        'cURL' => function_exists('curl_init') ? 'OK' : '&mdash;', 'mbstring' => function_exists('mb_strlen') ? 'OK' : '&mdash;',
        'SimpleXML' => function_exists('simplexml_load_string') ? 'OK' : '&mdash;',
        '/upload/feedwall' => is_writable(PATH.'/upload/feedwall') ? 'OK' : '<span class="fwa-bad">'.$_LANG['FWA_NOT_WRITABLE'].'</span>'
    );
    echo '<table class="fwa-table" style="width:auto">';
    foreach($env as $k => $v){ echo '<tr><th>'.fwa_h($k).'</th><td>'.$v.'</td></tr>'; }
    echo '</table>';
    $last_cron = (int)$model->getMeta('cron_time', 0);
    echo '<p>'.$_LANG['FWA_CRON_LAST'].': '.($last_cron ? date('d.m.Y H:i', $last_cron) : '&mdash;').'</p></div>';
    echo '<div class="fwa-box"><h3>'.$_LANG['FWA_MAINT'].'</h3>';
    echo '<p>'.fwa_btn($base, 'tool_recount', array(), $_LANG['FWA_RECOUNT']).' '.$_LANG['FWA_RECOUNT_HINT'].'</p>';
    echo '<p>'.fwa_btn($base, 'tool_cleanup', array(), $_LANG['FWA_CLEANUP']).' '.$_LANG['FWA_CLEANUP_HINT'].'</p>';
    echo '<p>'.fwa_btn($base, 'tool_sync', array(), $_LANG['FWA_SYNC']).' '.$_LANG['FWA_SYNC_HINT'].'</p>';
    echo '<p>'.fwa_btn($base, 'tool_schema', array(), $_LANG['FWA_SCHEMA']).' '.$_LANG['FWA_SCHEMA_HINT'].($bad ? ' <span class="fwa-bad">('.$bad.')</span>' : '').'</p></div>';
    break;

// ---------------------------------------------------------------- настройки
case 'config':
    cpAddPathway($_LANG['AD_SETTINGS']);
    $groups = array(
        'FWA_G_FEED'     => array('perpage','infinite','infinite_type','main_sort','main_sort_guest','show_activity','activity_types','activity_social','activity_images','views','cut_height','cut_length'),
        'FWA_G_FORM'     => array('maxlength','placeholder','placeholder_user','autoclose','daily_limit','edit_time','colored_max','post_to_wall','guest_add','captcha'),
        'FWA_G_MEDIA'    => array('photos_max','photo_size','video_max','audio_max_mb','voice','voice_max_sec','link_preview','gifs','gif_provider','gif_key','poll_photos'),
        'FWA_G_SOCIAL'   => array('reactions','off_likes','off_comments','off_share','share_buttons','mentions','mention_source','hashtags','smiles'),
        'FWA_G_COMMENTS' => array('comm_maxlength','comm_sort','comm_preview','comm_edit_time'),
        'FWA_G_MOD'      => array('complaints','complaint_email','ads_url','ads_repeat','verified_groups','verified_users'),
        'FWA_G_INTEG'    => array('profile_tab','likes_tab','club_wall','replace_wall','rss_user_id'),
        'FWA_G_RATING'   => array('rating_post','rating_comment','rating_react','rating_like','rating_vote'),
        'FWA_G_SEO'      => array('seo_title','seo_desc','seo_title_view','seo_desc_view','seo_title_tag','seo_desc_tag')
    );
    $selects = array(
        'infinite_type'   => array('scroll' => 'FWA_O_SCROLL', 'button' => 'FWA_O_BUTTON'),
        'main_sort'       => array('all' => 'FW_TAB_ALL', 'friends' => 'FW_TAB_FRIENDS', 'popular' => 'FW_TAB_POPULAR', 'discussed' => 'FW_TAB_DISCUSSED', 'viewed' => 'FW_TAB_VIEWED'),
        'comm_sort'       => array('asc' => 'FWA_O_ASC', 'desc' => 'FWA_O_DESC'),
        'gif_provider'    => array('local' => 'FWA_O_LOCAL', 'tenor' => 'FWA_O_TENOR'),
        'mention_source'  => array('users' => 'FWA_O_USERS', 'friends' => 'FWA_O_FRIENDS')
    );
    $selects['main_sort_guest'] = $selects['main_sort'];
    $defaults = feedwall_default_config();
    $all_keys = array(); foreach($groups as $g) { $all_keys = array_merge($all_keys, $g); }
    $other = array_diff(array_keys($defaults), $all_keys);
    if ($other) { $groups['FWA_G_OTHER'] = array_values($other); }
    $lng = function($k) use ($_LANG){ return isset($_LANG[$k]) ? $_LANG[$k] : $k; };
    echo '<form method="post" name="optform" action="'.$base.'&amp;opt=saveconfig">'.$tok;
    echo '<div class="fwa-tabs" style="margin-bottom:8px">';
    $i = 0; foreach($groups as $g => $keys){ echo '<a href="#" class="'.($i++ ? '' : 'on').'" onclick="jQuery(\'.fwa-tabs a\').removeClass(\'on\');jQuery(this).addClass(\'on\');jQuery(\'.fwa-grp\').hide();jQuery(\'#'.$g.'\').show();return false;">'.$lng($g).'</a>'; }
    echo '</div>';
    $i = 0;
    foreach($groups as $g => $keys){
        echo '<div class="fwa-grp fwa-box" id="'.$g.'"'.($i++ ? ' style="display:none"' : '').'><table class="fwa-form">';
        foreach($keys as $k){
            $def = $defaults[$k]; $val = isset($cfg[$k]) ? $cfg[$k] : $def;
            $label = $lng('FWA_C_'.strtoupper($k)); $hint = isset($_LANG['FWA_H_'.strtoupper($k)]) ? '<br/><small style="color:#777">'.$_LANG['FWA_H_'.strtoupper($k)].'</small>' : '';
            echo '<tr><td width="320"><b>'.$label.'</b>'.$hint.'</td><td>';
            if (isset($selects[$k])){
                echo '<select name="'.$k.'">';
                foreach($selects[$k] as $sv => $sl){ echo '<option value="'.$sv.'"'.($val == $sv ? ' selected' : '').'>'.$lng($sl).'</option>'; }
                echo '</select>';
            } elseif (is_int($def) && in_array($def, array(0, 1), true) && !preg_match('/^(rating_|daily|edit_time|comm_edit|cut_|rss_user)/', $k)){
                echo '<label><input type="radio" name="'.$k.'" value="1"'.($val ? ' checked' : '').' /> '.$_LANG['YES'].'</label> <label><input type="radio" name="'.$k.'" value="0"'.(!$val ? ' checked' : '').' /> '.$_LANG['NO'].'</label>';
            } elseif (is_int($def)){
                echo '<input type="text" name="'.$k.'" value="'.(int)$val.'" style="width:90px" />';
            } elseif (strpos($k, 'seo_desc') === 0 || in_array($k, array('activity_types','verified_users'))){
                echo '<textarea name="'.$k.'" rows="2">'.fwa_h($val).'</textarea>';
            } else {
                echo '<input type="text" name="'.$k.'" value="'.fwa_h($val).'" />';
            }
            echo '</td></tr>';
        }
        echo '</table></div>';
    }
    echo '<p><input type="submit" value="'.$_LANG['SAVE'].'" /> <a href="/admin/index.php?view=usergroups">'.$_LANG['FWA_ACCESS_LINK'].'</a></p></form>';
    break;

}
