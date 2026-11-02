<?php
/******************************************************************************/
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                        LICENSED BY GNU/GPL v2                              //
/******************************************************************************/

if(!defined('VALID_CMS')) { die('ACCESS DENIED'); }

/**
 * Общие функции вывода: шаблоны, токен AJAX, подключение JS/CSS.
 * Используются компонентом, плагином и модулями.
 */

function fw_model(){
    static $model = null;
    if ($model === null){
        cmsCore::loadModel('feedwall');
        $model = new cms_model_feedwall();
        $model->lang();
    }
    return $model;
}

function fw_token(){
    if (empty($_SESSION['feedwall_token']) || !is_string($_SESSION['feedwall_token'])){
        $_SESSION['feedwall_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['feedwall_token'];
}

function fw_check_token(){
    $sent = isset($_POST['fw_token']) ? (string)$_POST['fw_token'] : (isset($_SERVER['HTTP_X_FW_TOKEN']) ? (string)$_SERVER['HTTP_X_FW_TOKEN'] : '');
    return $sent !== '' && !empty($_SESSION['feedwall_token']) && hash_equals((string)$_SESSION['feedwall_token'], $sent);
}

function fw_reactions_map(){
    global $_LANG;
    $emoji = array('like'=>'&#128077;', 'love'=>'&#10084;&#65039;', 'haha'=>'&#128518;', 'wow'=>'&#128558;', 'sad'=>'&#128546;', 'angry'=>'&#128545;');
    $out = array();
    foreach(feedwall_reaction_types() as $r){
        $out[$r] = array('emoji' => $emoji[$r], 'title' => isset($_LANG['FW_REACT_'.strtoupper($r)]) ? $_LANG['FW_REACT_'.strtoupper($r)] : $r);
    }
    return $out;
}

function fw_viewer($model){
    $u = $model->inUser;
    $me = array('id'=>(int)$u->id, 'is_admin'=>(bool)$u->is_admin, 'is_mod'=>$model->isModerator(), 'login'=>'', 'nickname'=>'', 'avatar'=>'', 'url'=>'');
    if ($u->id){
        $x = $model->getUser($u->id);
        if ($x) { $me['login'] = $x['login']; $me['nickname'] = $x['nickname']; $me['avatar'] = $x['avatar']; $me['url'] = $x['url']; }
    }
    $perms = array();
    foreach(array('add','photo','video','audio','poll','gif','colored','anonymous','react','comment','comment_media','share','edit','delete') as $p){
        $perms[$p] = $model->can($p) || ($u->is_admin && $u->id);
    }
    $me['perms'] = $perms;
    return $me;
}

/** Рендер шаблона компонента в строку */
function fw_tpl($tpl, $vars = array(), $folder = 'components'){
    $model = fw_model();
    static $common = null;
    if ($common === null){
        $common = array(
            'fwcfg'      => $model->config,
            'fwme'       => fw_viewer($model),
            'fwreact'    => fw_reactions_map(),
            'fwtoken'    => fw_token(),
            'fwbgs'      => $model->getBackgrounds(),
            'fwsmiles'   => $model->config['smiles'] ? fw_smiles() : array()
        );
    }
    $t = cmsPage::initTemplate($folder, $tpl.'.tpl');
    static $plugins = false;
    if (!$plugins){
        $plugins = true;
        if (!isset($t->registered_plugins['modifier']['fwe'])) { $t->registerPlugin('modifier', 'fwe', 'fw_esc'); }
    }
    foreach($common as $k => $v){ $t->assign($k, $v); }
    foreach($vars as $k => $v){ $t->assign($k, $v); }
    return $t->fetch($tpl.'.tpl');
}

function fw_render_post($post){
    if (!isset($post['is_full'])) { $post['is_full'] = false; }
    if (!isset($post['my_reaction'])) { $post['my_reaction'] = ''; }
    $shown = 0;
    foreach((array)$post['comments'] as $c){ $shown += 1 + (isset($c['answers']) ? count($c['answers']) : 0); }
    $post['comments_shown'] = $shown;
    return fw_tpl('com_feedwall_post', array('post' => $post));
}

/** Список постов; рекламные блоки вставляются после N-го поста первой страницы */
function fw_render_posts($items, $page = 1, $with_ads = true){
    $model = fw_model();
    $ads = array();
    if ($with_ads && $page == 1){
        foreach($model->getActiveAds() as $ad){
            $pos = max(0, (int)$ad['position']);
            if (!isset($ads[$pos])) { $ads[$pos] = $ad; }
        }
        foreach($ads as $ad){ $model->logAd($ad['id'], 'view'); }
    }
    $html = '';
    if (isset($ads[0])) { $html .= fw_tpl('com_feedwall_ad', array('ad' => $ads[0])); }
    $n = 0;
    foreach($items as $post){
        $html .= fw_render_post($post);
        $n++;
        if (isset($ads[$n])) { $html .= fw_tpl('com_feedwall_ad', array('ad' => $ads[$n])); }
    }
    return $html;
}

function fw_render_form($target_type = 'feed', $target_id = 0, $opts = array()){
    $model = fw_model();
    if (!$model->canWriteTo($target_type, $target_id)) { return ''; }
    global $_LANG;
    $placeholder = $model->config['placeholder'] !== '' ? $model->config['placeholder'] : $_LANG['FW_PLACEHOLDER'];
    if ($target_type == 'user' && $target_id != $model->inUser->id){
        $placeholder = $model->config['placeholder_user'] !== '' ? $model->config['placeholder_user'] : $_LANG['FW_PLACEHOLDER_USER'];
    }
    $captcha = '';
    if (!$model->inUser->id && $model->config['captcha']){
        ob_start(); cmsPage::getCaptcha(); $captcha = ob_get_clean();
    }
    return fw_tpl('com_feedwall_form', array(
        'target_type' => $target_type, 'target_id' => (int)$target_id,
        'placeholder' => $placeholder, 'compact' => !empty($opts['compact']),
        'captcha' => $captcha, 'is_guest' => !$model->inUser->id,
        'form_id' => 'fwf'.substr(md5(uniqid('', true)), 0, 8)
    ));
}

/** Блок стены пользователя / клуба (форма + записи + подгрузка) */
function fw_render_wall($target_type, $target_id, $page = 1){
    $model = fw_model();
    $target_type = $target_type == 'club' ? 'club' : 'user';
    $ctx = array('type'=>'wall', 'target_type'=>$target_type, 'target_id'=>(int)$target_id);
    list($items, $has_more) = $model->getPosts($ctx, $page);
    return fw_tpl('com_feedwall_wall', array(
        'form'      => fw_render_form($target_type, $target_id),
        'posts'     => fw_render_posts($items, $page, false),
        'is_empty'  => !$items,
        'has_more'  => $has_more,
        'page'      => $page,
        'ctx_json'  => fw_esc(json_encode(array('kind'=>'wall', 'target_type'=>$target_type, 'target_id'=>(int)$target_id))),
        'target_type' => $target_type, 'target_id' => (int)$target_id
    ));
}

/** Теги для <head>: стили, скрипт и конфигурация JS */
function fw_head_tags(){
    global $_LANG;
    $model = fw_model();
    $cfg = $model->config;
    $ver = '100';
    $keys = array('FW_LOADING','FW_ERROR','FW_CONFIRM_DELETE','FW_CONFIRM_DELETE_COMMENT','FW_SHOW_MORE','FW_HIDE','FW_SAVE','FW_CANCEL',
                  'FW_REPLY','FW_NO_GIFS','FW_RECORDING','FW_STOP','FW_VOICE_UNSUPPORTED','FW_COMPLAIN_PROMPT','FW_COMPLAIN_SENT',
                  'FW_SHARE_DONE','FW_LINK_COPIED','FW_ANSWER','FW_POLL_MIN','FW_EMPTY_POST','FW_TOO_LONG','FW_UPLOADING',
                  'FW_VIDEO_BAD','FW_HIDDEN_DONE','FW_PINNED','FW_UNPINNED','FW_REACTORS','FW_VOTERS','FW_SHARE','FW_SHARE_TEXT',
                  'FW_SHARE_FEED','FW_SHARE_WALL','FW_SHARE_STATUS','FW_EDIT','FW_SEARCH_GIF','FW_NOTHING_FOUND','FW_MAX_FILES');
    $lang = array();
    foreach($keys as $k){ $lang[$k] = isset($_LANG[$k]) ? $_LANG[$k] : $k; }
    $js = array(
        'token'      => fw_token(),
        'user_id'    => (int)$model->inUser->id,
        'maxlength'  => (int)$cfg['maxlength'],
        'comm_maxlength' => (int)$cfg['comm_maxlength'],
        'colored_max'=> (int)$cfg['colored_max'],
        'photos_max' => (int)$cfg['photos_max'],
        'video_max'  => (int)$cfg['video_max'],
        'voice_max'  => (int)$cfg['voice_max_sec'],
        'infinite'   => (int)$cfg['infinite'],
        'infinite_type' => $cfg['infinite_type'] == 'button' ? 'button' : 'scroll',
        'cut_height' => (int)$cfg['cut_height'],
        'views'      => (int)$cfg['views'],
        'link_preview' => (int)$cfg['link_preview'],
        'mentions'   => (int)$cfg['mentions'],
        'autoclose'  => (int)$cfg['autoclose'],
        'reactions'  => (int)$cfg['reactions'],
        'smiles'     => $cfg['smiles'] ? fw_smiles() : array(),
        'lang'       => $lang
    );
    $json = json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    return array(
        '<link href="/components/feedwall/css/feedwall.css?v='.$ver.'" rel="stylesheet" type="text/css" />',
        '<script type="text/javascript">var FWCFG = '.$json.';</script>',
        '<script type="text/javascript" src="/components/feedwall/js/feedwall.js?v='.$ver.'"></script>'
    );
}

/** Подключает ресурсы на обычной (не AJAX) странице */
function fw_add_assets(){
    if (cmsCore::isAjax()) { return; }
    $inPage = cmsPage::getInstance();
    foreach(fw_head_tags() as $tag){ $inPage->addHead($tag); }
}
