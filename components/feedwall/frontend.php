<?php
/******************************************************************************/
//                                                                            //
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                                                                            //
//                        LICENSED BY GNU/GPL v2                              //
//                                                                            //
/******************************************************************************/

if(!defined('VALID_CMS')) { die('ACCESS DENIED'); }

cmsCore::includeFile('components/feedwall/includes/render.php');

function feedwall(){

    $inCore = cmsCore::getInstance();
    $inPage = cmsPage::getInstance();
    $inUser = cmsUser::getInstance();

    $model = fw_model();
    $cfg   = $model->config;

    global $_LANG;

    $do   = $inCore->do;
    $page = max(1, cmsCore::request('page', 'int', 1));

    if ($do == 'ajax'){
        fw_ajax($model, preg_replace('/[^a-z_]/', '', (string)cmsCore::request('action', 'str', '')));
        return;
    }

    if ($do == 'go'){
        $ad = $model->getAd(cmsCore::request('id', 'int', 0));
        if (!$ad || !preg_match('#^https?://#i', $ad['url'])) { cmsCore::error404(); }
        $model->logAd($ad['id'], 'click');
        cmsCore::redirect($ad['url']);
    }

    fw_add_assets();

    $title = $inCore->getComponentTitle();
    if (!$title) { $title = $_LANG['FW_TITLE']; }
    $inPage->addPathway($title, '/feedwall');

//============================================================================//
//                                ЛЕНТА                                       //
//============================================================================//

    if ($do == 'view'){

        $tabs = array('all', 'friends', 'my', 'popular', 'discussed', 'viewed');
        $tab  = (string)cmsCore::request('tab', 'str', '');
        if (!in_array($tab, $tabs, true)){
            $tab = $inUser->id ? $cfg['main_sort'] : $cfg['main_sort_guest'];
            if (!in_array($tab, $tabs, true)) { $tab = 'all'; }
        }
        if (!$inUser->id && in_array($tab, array('friends', 'my'))) { $tab = 'all'; }

        $period = (string)cmsCore::request('period', 'str', '');
        if (!in_array($period, array('day', 'week', 'month'), true)) { $period = ''; }

        $model->syncActivity();

        $ctx = fw_ctx_for_tab($tab, $period);
        list($items, $has_more) = $model->getPosts($ctx, $page);

        $base = $tab == ($inUser->id ? $cfg['main_sort'] : $cfg['main_sort_guest']) && !cmsCore::request('tab', 'str', '') ? '/feedwall' : '/feedwall/'.$tab;
        $pagebar = '';
        if (!$cfg['infinite']){
            $pagebar = cmsPage::getPagebar($model->countPosts($ctx), $page, max(1, (int)$cfg['perpage']), '/feedwall/'.$tab.'/page-%page%'.($period ? '?period='.$period : ''));
        }

        $inPage->setTitle($cfg['seo_title'] !== '' ? $cfg['seo_title'] : $title);
        $inPage->setDescription($cfg['seo_desc'] !== '' ? $cfg['seo_desc'] : $title);
        if ($tab != 'all') { $inPage->addPathway($_LANG['FW_TAB_'.strtoupper($tab)]); }

        echo fw_tpl('com_feedwall_index', array(
            'title'    => $title,
            'tabs'     => fw_tabs_list($tab),
            'tab'      => $tab,
            'period'   => $period,
            'periods'  => in_array($tab, array('popular', 'discussed', 'viewed')),
            'form'     => fw_render_form('feed', 0),
            'posts'    => fw_render_posts($items, $page, true),
            'is_empty' => !$items,
            'has_more' => $has_more && $cfg['infinite'],
            'page'     => $page,
            'pagebar'  => $pagebar,
            'base'     => $base,
            'ctx_json' => fw_esc(json_encode(array('kind'=>'tab', 'tab'=>$tab, 'period'=>$period)))
        ));
        return;

    }

//============================================================================//
//                              ОДНА ЗАПИСЬ                                   //
//============================================================================//

    if ($do == 'post'){

        $post = $model->getPost(cmsCore::request('id', 'int', 0));
        if (!$post) { cmsCore::error404(); }
        if ($post['target_type'] == 'club'){
            $club = $model->getClub($post['target_id']);
            if (!$club || !$club['can_view']) { cmsCore::error404(); }
        }

        $post['comments'] = $model->config['off_comments'] ? array() : $model->getComments($post['id']);
        $post['is_full']  = true;

        $plain = fw_plain_cut($post['text'] !== '' ? $post['text'] : ($post['activity'] ? strip_tags($post['activity']['message']) : $_LANG['FW_POST']), 70);
        $author = $post['author'] ? $post['author']['nickname'] : ($post['guest_name'] !== '' ? $post['guest_name'] : $_LANG['FW_ANONYMOUS']);

        $t = $cfg['seo_title_view'] !== '' ? str_replace(array('{text}', '{author}'), array($plain, $author), $cfg['seo_title_view']) : $plain;
        $d = $cfg['seo_desc_view'] !== '' ? str_replace(array('{text}', '{author}'), array($plain, $author), $cfg['seo_desc_view']) : fw_plain_cut($post['text'], 200);
        $inPage->setTitle(html_entity_decode($t, ENT_QUOTES, 'UTF-8'));
        $inPage->setDescription(html_entity_decode($d !== '' ? $d : $t, ENT_QUOTES, 'UTF-8'));
        $inPage->addPathway($plain);

        // Open Graph
        $og_image = '';
        if ($post['photos']) { $og_image = HOST.$post['photos'][0]['file']; }
        elseif ($post['link'] && $post['link']['image']) { $og_image = $post['link']['image']; }
        elseif ($post['videos'] && $post['videos'][0]['thumb']) { $og_image = $post['videos'][0]['thumb']; }
        $inPage->addHead('<meta property="og:type" content="article" />');
        $inPage->addHead('<meta property="og:title" content="'.fw_esc($plain).'" />');
        $inPage->addHead('<meta property="og:url" content="'.fw_esc(HOST.$post['url']).'" />');
        if ($og_image) { $inPage->addHead('<meta property="og:image" content="'.fw_esc($og_image).'" />'); }

        $model->registerViews(array($post['id']));

        echo fw_tpl('com_feedwall_view', array(
            'title' => $title,
            'post_html' => fw_render_post($post),
            'post' => $post
        ));
        return;

    }

//============================================================================//
//                                 ХЭШТЕГ                                     //
//============================================================================//

    if ($do == 'tag'){

        $tag = mb_strtolower(trim(rawurldecode((string)@$_REQUEST['tag'])));
        $tag = fw_mb4_encode(preg_replace('/[^\p{L}\p{N}_]/u', '', $tag));
        if ($tag === '' || !$cfg['hashtags']) { cmsCore::error404(); }

        $ctx = array('type'=>'tag', 'tag'=>$tag);
        list($items, $has_more) = $model->getPosts($ctx, $page);

        $inPage->setTitle($cfg['seo_title_tag'] !== '' ? str_replace('{tag}', $tag, $cfg['seo_title_tag']) : '#'.$tag.' — '.$title);
        $inPage->setDescription($cfg['seo_desc_tag'] !== '' ? str_replace('{tag}', $tag, $cfg['seo_desc_tag']) : '#'.$tag);
        $inPage->addPathway('#'.$tag);

        echo fw_tpl('com_feedwall_list', array(
            'heading'  => '#'.$tag,
            'form'     => '',
            'posts'    => fw_render_posts($items, $page, false),
            'is_empty' => !$items,
            'has_more' => $has_more,
            'page'     => $page,
            'ctx_json' => fw_esc(json_encode(array('kind'=>'tag', 'tag'=>$tag))),
            'tags'     => $model->getTags(30)
        ));
        return;

    }

//============================================================================//
//                       СТЕНА ПОЛЬЗОВАТЕЛЯ / КЛУБА                           //
//============================================================================//

    if ($do == 'wall'){

        $type = cmsCore::request('target_type', 'str', 'user') == 'club' ? 'club' : 'user';
        $id   = cmsCore::request('target_id', 'int', 0);

        if ($type == 'user'){
            $owner = $model->getUser($id);
            if (!$owner || $owner['is_deleted']) { cmsCore::error404(); }
            $heading = sprintf($_LANG['FW_WALL_OF'], $owner['nickname']);
            $back = $owner['url'];
        } else {
            $club = $model->getClub($id);
            if (!$club || !$club['can_view']) { cmsCore::error404(); }
            $heading = sprintf($_LANG['FW_WALL_OF'], $club['title']);
            $back = $club['url'];
        }

        $html = fw_render_wall($type, $id, $page);

        if (cmsCore::isAjax()){ echo $html; return; }

        $inPage->setTitle(html_entity_decode($heading, ENT_QUOTES, 'UTF-8'));
        $inPage->addPathway($heading);
        echo fw_tpl('com_feedwall_page', array('heading'=>$heading, 'back'=>$back, 'body'=>$html));
        return;

    }

//============================================================================//
//                          ПОНРАВИЛОСЬ ПОЛЬЗОВАТЕЛЮ                          //
//============================================================================//

    if ($do == 'likes'){

        $owner = $model->getUser(cmsCore::request('user_id', 'int', 0));
        if (!$owner || $owner['is_deleted']) { cmsCore::error404(); }

        $ctx = array('type'=>'likes', 'user_id'=>$owner['id']);
        list($items, $has_more) = $model->getPosts($ctx, $page);

        $body = fw_tpl('com_feedwall_list', array(
            'heading'  => '',
            'form'     => '',
            'posts'    => fw_render_posts($items, $page, false),
            'is_empty' => !$items,
            'has_more' => $has_more,
            'page'     => $page,
            'ctx_json' => fw_esc(json_encode(array('kind'=>'likes', 'user_id'=>(int)$owner['id']))),
            'tags'     => array()
        ));

        if (cmsCore::isAjax()){ echo $body; return; }

        $heading = sprintf($_LANG['FW_LIKES_OF'], $owner['nickname']);
        $inPage->setTitle(html_entity_decode($heading, ENT_QUOTES, 'UTF-8'));
        $inPage->addPathway($heading);
        echo fw_tpl('com_feedwall_page', array('heading'=>$heading, 'back'=>$owner['url'], 'body'=>$body));
        return;

    }

//============================================================================//
//                       ФОТО И МУЗЫКА ПОЛЬЗОВАТЕЛЯ                           //
//============================================================================//

    if ($do == 'media'){

        $owner = $model->getUser(cmsCore::request('user_id', 'int', 0));
        if (!$owner || $owner['is_deleted']) { cmsCore::error404(); }
        $type = cmsCore::request('media_type', 'str', 'photos') == 'music' ? 'audio' : 'photo';

        $perpage = 30;
        list($list, $total) = $model->getUserMedia($owner['id'], $type, $page, $perpage);

        $heading = sprintf($type == 'audio' ? $_LANG['FW_MUSIC_OF'] : $_LANG['FW_PHOTOS_OF'], $owner['nickname']);
        $inPage->setTitle(html_entity_decode($heading, ENT_QUOTES, 'UTF-8'));
        $inPage->addPathway($heading);

        echo fw_tpl('com_feedwall_media', array(
            'heading' => $heading, 'owner' => $owner, 'type' => $type, 'list' => $list, 'total' => $total,
            'pagebar' => cmsPage::getPagebar($total, $page, $perpage, '/feedwall/'.($type == 'audio' ? 'music' : 'photos').'/'.$owner['id'].'/page-%page%')
        ));
        return;

    }

//============================================================================//
//                            НАСТРОЙКИ ПОЛЬЗОВАТЕЛЯ                          //
//============================================================================//

    if ($do == 'settings'){

        if (!$inUser->id) { cmsUser::goToLogin(); }

        if (cmsCore::inRequest('save')){
            if (!fw_check_token()) { cmsCore::error404(); }
            $data = array();
            foreach(array('who_write','who_comment','who_react') as $k){ $data[$k] = (string)cmsCore::request($k, 'str', 'all'); }
            foreach(array('allow_mentions','in_feed','notify_wall','notify_mention','notify_comment','notify_reply','notify_react','notify_share') as $k){
                $data[$k] = cmsCore::request($k, 'int', 0);
            }
            $model->saveUserCfg($inUser->id, $data);
            cmsCore::addSessionMessage($_LANG['FW_SETTINGS_SAVED'], 'success');
            cmsCore::redirect('/feedwall/settings');
        }

        $inPage->setTitle($_LANG['FW_SETTINGS']);
        $inPage->addPathway($_LANG['FW_SETTINGS']);

        echo fw_tpl('com_feedwall_settings', array(
            'ucfg'   => $model->getUserCfg($inUser->id),
            'hidden' => $model->getHidden($inUser->id),
            'ads'    => $model->getUserAds($inUser->id)
        ));
        return;

    }

//============================================================================//
//                       СТАТИСТИКА РЕКЛАМЫ ПОЛЬЗОВАТЕЛЯ                      //
//============================================================================//

    if ($do == 'ads'){

        if (!$inUser->id) { cmsUser::goToLogin(); }

        $ads = $model->getUserAds($inUser->id);
        if ($inUser->is_admin){ $ads = $model->inDB->get_table('cms_feedwall_ads', '1=1 ORDER BY id DESC'); }
        foreach($ads as $k => $ad){
            $ads[$k]['stats'] = $model->getAdStats($ad['id'], 30);
            $ads[$k]['ctr']   = $ad['views_count'] ? round(100 * $ad['clicks_count'] / $ad['views_count'], 2) : 0;
        }

        $inPage->setTitle($_LANG['FW_ADS_STATS']);
        $inPage->addPathway($_LANG['FW_ADS_STATS']);

        echo fw_tpl('com_feedwall_ads', array('ads' => $ads, 'ads_url' => $cfg['ads_url']));
        return;

    }

    cmsCore::error404();

}

//============================================================================//
//                              ВСПОМОГАТЕЛЬНОЕ                               //
//============================================================================//

function fw_ctx_for_tab($tab, $period = ''){
    switch($tab){
        case 'friends':   return array('type'=>'friends', 'sort'=>'new');
        case 'my':        return array('type'=>'my', 'sort'=>'new');
        case 'popular':   return array('type'=>'feed', 'sort'=>'popular', 'period'=>$period);
        case 'discussed': return array('type'=>'feed', 'sort'=>'discussed', 'period'=>$period);
        case 'viewed':    return array('type'=>'feed', 'sort'=>'viewed', 'period'=>$period);
        default:          return array('type'=>'feed', 'sort'=>'new');
    }
}

function fw_tabs_list($active){
    global $_LANG;
    $inUser = cmsUser::getInstance();
    $list = array();
    foreach(array('all', 'friends', 'my', 'popular', 'discussed', 'viewed') as $t){
        if (!$inUser->id && in_array($t, array('friends', 'my'))) { continue; }
        $list[] = array('key'=>$t, 'title'=>$_LANG['FW_TAB_'.strtoupper($t)], 'url'=>'/feedwall/'.$t, 'active'=>$t == $active);
    }
    return $list;
}

/** Контекст выборки по параметрам AJAX-запроса (только белый список) */
function fw_ctx_from_request(){
    $kind = (string)cmsCore::request('kind', 'str', 'tab');
    switch($kind){
        case 'wall':
            return array('type'=>'wall', 'target_type'=>cmsCore::request('target_type', 'str', 'user') == 'club' ? 'club' : 'user',
                         'target_id'=>cmsCore::request('target_id', 'int', 0));
        case 'tag':
            $tag = mb_strtolower(preg_replace('/[^\p{L}\p{N}_&#;]/u', '', (string)@$_POST['tag']));
            return array('type'=>'tag', 'tag'=>$tag);
        case 'likes':
            return array('type'=>'likes', 'user_id'=>cmsCore::request('user_id', 'int', 0));
        case 'user':
            return array('type'=>'user', 'user_id'=>cmsCore::request('user_id', 'int', 0));
        default:
            $tab = (string)cmsCore::request('tab', 'str', 'all');
            $period = (string)cmsCore::request('period', 'str', '');
            if (!in_array($period, array('day', 'week', 'month'), true)) { $period = ''; }
            if (!cmsUser::getInstance()->id && in_array($tab, array('friends', 'my'))) { $tab = 'all'; }
            return fw_ctx_for_tab($tab, $period);
    }
}

function fw_json_error($msg){
    cmsCore::jsonOutput(array('error' => true, 'message' => $msg));
}

function fw_json_ok($data = array()){
    cmsCore::jsonOutput(array_merge(array('error' => false), $data));
}

/** Сырой строковый параметр запроса (без экранирования ядра) */
function fw_in($key, $default = ''){
    if (!isset($_REQUEST[$key]) || is_array($_REQUEST[$key])) { return $default; }
    return trim((string)$_REQUEST[$key]);
}

function fw_in_arr($key){
    return (isset($_REQUEST[$key]) && is_array($_REQUEST[$key])) ? $_REQUEST[$key] : array();
}

/** Пост, доступный текущему посетителю (с проверкой клуба) */
function fw_visible_post($model, $id){
    $post = $model->getPost((int)$id);
    if (!$post) { return false; }
    if ($post['target_type'] == 'club'){
        $club = $model->getClub($post['target_id']);
        if (!$club || !$club['can_view']) { return false; }
    }
    return $post;
}

/** Сбор вложений поста из запроса с учётом прав */
function fw_attach_from_request($model){
    $data = array();
    if ($model->can('photo'))  { $data['photos'] = array_map('intval', fw_in_arr('photos')); }
    if ($model->can('audio'))  { $data['audios'] = array_map('intval', fw_in_arr('audios')); }
    if ($model->can('video'))  {
        $v = array();
        foreach(fw_in_arr('videos') as $url){ if (is_string($url) && $url !== '') { $v[] = $url; } }
        $data['videos'] = $v;
    }
    if ($model->config['link_preview'] && isset($_POST['link']) && is_array($_POST['link'])){
        $l = array();
        foreach(array('url','title','desc','image','site') as $k){ $l[$k] = isset($_POST['link'][$k]) && is_string($_POST['link'][$k]) ? $_POST['link'][$k] : ''; }
        if ($l['url'] !== '') { $data['link'] = $l; }
    }
    if ($model->can('gif') && $model->config['gifs']) { $data['gif'] = fw_in('gif'); }
    if ($model->can('colored')) { $data['bg_id'] = (int)fw_in('bg_id', 0); }
    return $data;
}

function fw_has_content($text, $data){
    if ($text !== '') { return true; }
    foreach(array('photos','audios','videos') as $k){ if (!empty($data[$k]) && array_filter((array)$data[$k])) { return true; } }
    return !empty($data['link']) || !empty($data['gif']) || !empty($data['poll']);
}

//============================================================================//
//                                  AJAX API                                  //
//============================================================================//

function fw_ajax($model, $action){

    global $_LANG;
    $inUser = cmsUser::getInstance();
    $cfg = $model->config;
    $me  = (int)$inUser->id;
    $is_post = $_SERVER['REQUEST_METHOD'] == 'POST';

    $readonly = array('more', 'comments', 'reactors', 'voters', 'gifs', 'mention', 'usercard', 'video_parse', 'edit_form');
    if (!in_array($action, $readonly, true)){
        if (!$is_post || !fw_check_token()) { fw_json_error($_LANG['FW_ERR_TOKEN']); }
    }

    switch($action){

    // ---------------------------------------------------------------- ленты
    case 'more':
        $ctx  = fw_ctx_from_request();
        if ($ctx['type'] == 'wall' && $ctx['target_type'] == 'club'){
            $club = $model->getClub($ctx['target_id']);
            if (!$club || !$club['can_view']) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        }
        $page = max(1, (int)fw_in('page', 1));
        list($items, $has_more) = $model->getPosts($ctx, $page);
        fw_json_ok(array('html' => fw_render_posts($items, $page, false), 'has_more' => (bool)$has_more, 'count' => count($items)));

    // ---------------------------------------------------------------- добавление записи
    case 'add':
        $tt  = in_array(fw_in('target_type'), array('feed','user','club'), true) ? fw_in('target_type') : 'feed';
        $tid = (int)fw_in('target_id', 0);
        if ($tt == 'feed') { $tid = 0; }
        if (!$model->canWriteTo($tt, $tid)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        if ($tt == 'user' && $tid != $me && !$cfg['post_to_wall'] && !$inUser->is_admin) { fw_json_error($_LANG['FW_ERR_ACCESS']); }

        $data = array('target_type' => $tt, 'target_id' => $tid);

        if (!$me){
            if ($cfg['captcha'] && !cmsPage::checkCaptchaCode()) { fw_json_error($_LANG['FW_ERR_CAPTCHA']); }
            $gn = fw_clean_text(fw_in('guest_name'), 50);
            if ($gn === '') { fw_json_error($_LANG['FW_ERR_GUEST_NAME']); }
            $data['guest_name'] = $gn;
        } elseif ($cfg['daily_limit'] && !$model->isModerator() && $model->postsToday($me) >= (int)$cfg['daily_limit']){
            fw_json_error(sprintf($_LANG['FW_ERR_DAILY'], (int)$cfg['daily_limit']));
        }

        $raw = fw_in('text');
        if ($cfg['maxlength'] && mb_strlen($raw) > (int)$cfg['maxlength']) { fw_json_error($_LANG['FW_TOO_LONG']); }
        $text = fw_clean_text($raw, $cfg['maxlength'] ? (int)$cfg['maxlength'] : 20000);

        if ($me){
            $data = array_merge($data, fw_attach_from_request($model));
            if ($model->can('poll') && fw_in('poll_question') !== ''){
                $answers = array(); $imgs = fw_in_arr('poll_images');
                foreach(fw_in_arr('poll_answers') as $i => $a){
                    if (!is_string($a)) { continue; }
                    $answers[] = array('title' => $a, 'image' => $cfg['poll_photos'] ? (int)@$imgs[$i] : 0);
                }
                $data['poll'] = array('question' => fw_in('poll_question'), 'answers' => $answers, 'is_multi' => (int)fw_in('poll_multi', 0));
                $real = 0; foreach($answers as $a){ if (trim($a['title']) !== '' || $a['image']) { $real++; } }
                if ($real < 2) { fw_json_error($_LANG['FW_POLL_MIN']); }
            }
            $data['is_private']   = (int)fw_in('is_private', 0) && $tt != 'club' ? 1 : 0;
            $data['is_anonymous'] = ($model->can('anonymous') && $tt == 'feed' && (int)fw_in('is_anonymous', 0)) ? 1 : 0;
        }

        if (!fw_has_content($text, $data)) { fw_json_error($_LANG['FW_EMPTY_POST']); }
        $data['text'] = $text;

        $id = $model->addPost($data);
        if (!$id) { fw_json_error($_LANG['FW_ERROR']); }
        $post = $model->getPost($id);
        fw_json_ok(array('id' => $id, 'html' => $post ? fw_render_post($post) : '', 'message' => $_LANG['FW_POST_ADDED']));

    // ---------------------------------------------------------------- редактирование
    case 'edit_form':
        $post = $model->getPost((int)fw_in('post_id', 0));
        if (!$post || !$model->canEditPost($post)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        fw_json_ok(array('html' => fw_tpl('com_feedwall_edit', array('post' => $post))));

    case 'edit_save':
        $post = $model->getPost((int)fw_in('post_id', 0));
        if (!$post || !$model->canEditPost($post)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $raw = fw_in('text');
        if ($cfg['maxlength'] && mb_strlen($raw) > (int)$cfg['maxlength']) { fw_json_error($_LANG['FW_TOO_LONG']); }
        $text = fw_clean_text($raw, $cfg['maxlength'] ? (int)$cfg['maxlength'] : 20000);
        $data = fw_attach_from_request($model);
        // ссылку и gif, которые нельзя менять в форме, переносим как есть
        if (!isset($data['link']) && $post['link'] && (int)fw_in('keep_link', 0)) { $data['link'] = $post['link']; }
        if (empty($data['gif']) && $post['gif'] && (int)fw_in('keep_gif', 0)) { $data['gif'] = $post['gif']['url']; }
        if (!isset($data['bg_id'])) { $data['bg_id'] = (int)$post['bg_id']; }
        // вложения, на которые у редактора нет прав, сохраняем как были
        foreach(array('photos', 'audios') as $k){ if (!isset($data[$k])) { $data[$k] = $post['attach_ids'][$k]; } }
        if (!isset($data['videos'])) { $data['videos'] = array(); foreach($post['videos'] as $v){ $data['videos'][] = $v; } }
        $data['is_private'] = $post['target_type'] != 'club' && (int)fw_in('is_private', 0) ? 1 : 0;
        if (!fw_has_content($text, $data) && !$post['poll_id'] && $post['type'] != 'share') { fw_json_error($_LANG['FW_EMPTY_POST']); }
        $data['text'] = $text;
        $model->updatePost($post['id'], $data);
        $post = $model->getPost($post['id']);
        fw_json_ok(array('html' => fw_render_post($post)));

    // ---------------------------------------------------------------- удаление / закрепление / скрытие
    case 'delete':
        $post = $model->getPost((int)fw_in('post_id', 0), true);
        if (!$post || !$model->canDeletePost($post)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $model->deletePost($post['id']);
        fw_json_ok(array('message' => $_LANG['FW_DELETED']));

    case 'pin':
        $post = $model->getPost((int)fw_in('post_id', 0), true);
        if (!$post || !$model->canPin($post)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $v = $post['is_pinned'] ? 0 : 1;
        $model->setPinned($post['id'], 'is_pinned', $v);
        fw_json_ok(array('pinned' => $v, 'message' => $v ? $_LANG['FW_PINNED'] : $_LANG['FW_UNPINNED']));

    case 'pin_feed':
        $post = $model->getPost((int)fw_in('post_id', 0), true);
        if (!$post || !$model->isModerator() || $post['target_type'] == 'club') { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $v = $post['is_pinned_feed'] ? 0 : 1;
        $model->setPinned($post['id'], 'is_pinned_feed', $v);
        fw_json_ok(array('pinned' => $v, 'message' => $v ? $_LANG['FW_PINNED'] : $_LANG['FW_UNPINNED']));

    case 'hide':
    case 'hide_author':
        if (!$me) { fw_json_error($_LANG['FW_ERR_AUTH']); }
        $post = $model->getPost((int)fw_in('post_id', 0), true);
        if (!$post) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        if ($action == 'hide') { $model->hide($me, $post['id'], 0); }
        else {
            if (!$post['user_id'] || $post['user_id'] == $me) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
            $model->hide($me, 0, $post['user_id']);
        }
        fw_json_ok(array('author_id' => (int)$post['user_id'], 'message' => $_LANG['FW_HIDDEN_DONE']));

    case 'unhide':
        if (!$me) { fw_json_error($_LANG['FW_ERR_AUTH']); }
        $model->unhide($me, (int)fw_in('id', 0));
        fw_json_ok();

    // ---------------------------------------------------------------- реакции
    case 'react':
        if (!$me) { fw_json_error($_LANG['FW_ERR_AUTH']); }
        $comment_id = (int)fw_in('comment_id', 0);
        if ($comment_id){
            $c = $model->getComment($comment_id);
            $post = $c ? fw_visible_post($model, $c['post_id']) : false;
            if (!$post || $cfg['off_likes'] || !$model->can('react')) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
            $r = $model->react($post['id'], $comment_id, 'like');
            $c = $model->getComment($comment_id);
            fw_json_ok(array('reaction' => $r, 'count' => (int)$c['likes_count']));
        }
        $post = fw_visible_post($model, (int)fw_in('post_id', 0));
        if (!$post || !$model->canReact($post)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $r = $model->react($post['id'], 0, preg_replace('/[^a-z]/', '', fw_in('reaction', 'like')));
        $post = $model->getPost($post['id']);
        fw_json_ok(array('reaction' => $r, 'count' => (int)$post['likes_count'], 'html' => fw_tpl('com_feedwall_bar', array('post' => $post))));

    case 'reactors':
        $comment_id = (int)fw_in('comment_id', 0);
        $post_id = (int)fw_in('post_id', 0);
        if ($comment_id){ $c = $model->getComment($comment_id); $post_id = $c ? (int)$c['post_id'] : 0; }
        $post = fw_visible_post($model, $post_id);
        if (!$post) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $users = $model->getReactors($post['id'], $comment_id, preg_replace('/[^a-z]/', '', fw_in('reaction')));
        fw_json_ok(array('html' => fw_tpl('com_feedwall_users', array('users' => $users, 'title' => $_LANG['FW_REACTORS'], 'show_reaction' => !$comment_id))));

    // ---------------------------------------------------------------- комментарии
    case 'comments':
        $post = fw_visible_post($model, (int)fw_in('post_id', 0));
        if (!$post) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $parent = (int)fw_in('parent_id', 0);
        $list = $model->getComments($post['id'], $parent);
        $html = '';
        foreach($list as $c){ $html .= fw_tpl('com_feedwall_comment', array('c' => $c, 'post' => $post)); }
        fw_json_ok(array('html' => $html, 'count' => (int)$post['comments_count']));

    case 'comment_add':
        if (!$me) { fw_json_error($_LANG['FW_ERR_AUTH']); }
        $post = fw_visible_post($model, (int)fw_in('post_id', 0));
        if (!$post || !$model->canComment($post)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $raw = fw_in('text');
        $max = (int)$cfg['comm_maxlength'] ? (int)$cfg['comm_maxlength'] : 5000;
        if (mb_strlen($raw) > $max) { fw_json_error($_LANG['FW_TOO_LONG']); }
        $data = array('text' => fw_clean_text($raw, $max), 'parent_id' => (int)fw_in('parent_id', 0));
        if ($model->can('comment_media')){
            $data['photos'] = array_map('intval', fw_in_arr('photos'));
            $data['voice']  = (int)fw_in('voice', 0);
            if ($cfg['gifs']) { $data['gif'] = fw_in('gif'); }
        }
        if ($data['text'] === '' && empty($data['photos']) && empty($data['voice']) && empty($data['gif'])) { fw_json_error($_LANG['FW_EMPTY_POST']); }
        $id = $model->addComment($post, $data);
        if (!$id) { fw_json_error($_LANG['FW_ERROR']); }
        $c = $model->getCommentPrepared($id);
        $post = $model->getPost($post['id']);
        fw_json_ok(array('id' => $id, 'parent_id' => (int)$c['parent_id'], 'count' => (int)$post['comments_count'],
                         'html' => fw_tpl('com_feedwall_comment', array('c' => $c, 'post' => $post))));

    case 'comment_edit':
        $c = $model->getComment((int)fw_in('comment_id', 0));
        if (!$c || !$model->canEditComment($c)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $max = (int)$cfg['comm_maxlength'] ? (int)$cfg['comm_maxlength'] : 5000;
        $text = fw_clean_text(fw_in('text'), $max);
        if ($text === '' && !$c['attach']) { fw_json_error($_LANG['FW_EMPTY_POST']); }
        $model->updateComment($c['id'], $text);
        $c = $model->getCommentPrepared($c['id']);
        fw_json_ok(array('html' => $c['html']));

    case 'comment_delete':
        $c = $model->getComment((int)fw_in('comment_id', 0));
        if (!$c) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $post = $model->getPost($c['post_id'], true);
        if (!$model->canDeleteComment($c, $post)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $model->deleteComment($c['id']);
        $post = $model->getPost($c['post_id'], true);
        fw_json_ok(array('count' => $post ? (int)$post['comments_count'] : 0));

    case 'comment_like':
        $_REQUEST['comment_id'] = (int)fw_in('comment_id', 0);
        return fw_ajax($model, 'react');

    // ---------------------------------------------------------------- репост
    case 'share':
        if (!$me || !$model->can('share') || $cfg['off_share']) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $post = fw_visible_post($model, (int)fw_in('post_id', 0));
        if (!$post || $post['is_private']) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $orig_id = ($post['type'] == 'share' && $post['share_id']) ? (int)$post['share_id'] : (int)$post['id'];
        $mode = fw_in('mode', 'feed');
        $text = fw_clean_text(fw_in('text'), 1000);
        if ($mode == 'status'){
            $plain = fw_plain_cut($text !== '' ? $text : html_entity_decode($post['text'], ENT_QUOTES, 'UTF-8'), 120);
            $status = mb_substr(trim($plain.' '.HOST.'/feedwall/post/'.$orig_id), 0, 140);
            $model->inDB->query("UPDATE cms_users SET status = '".$model->e(fw_esc($status))."', status_date = NOW() WHERE id = '{$me}' LIMIT 1");
            $model->inDB->query("UPDATE cms_feedwall_posts SET shares_count = shares_count + 1 WHERE id = '{$orig_id}'");
            fw_json_ok(array('message' => $_LANG['FW_SHARE_DONE']));
        }
        $tt = $mode == 'wall' ? 'user' : 'feed';
        if ($model->config['daily_limit'] && !$model->isModerator() && $model->postsToday($me) >= (int)$cfg['daily_limit']){
            fw_json_error(sprintf($_LANG['FW_ERR_DAILY'], (int)$cfg['daily_limit']));
        }
        $id = $model->addPost(array('text' => $text, 'target_type' => $tt, 'target_id' => $tt == 'user' ? $me : 0, 'type' => 'share', 'share_id' => $orig_id));
        if (!$id) { fw_json_error($_LANG['FW_ERROR']); }
        $orig = $model->getPost($orig_id, true);
        fw_json_ok(array('id' => $id, 'message' => $_LANG['FW_SHARE_DONE'], 'shares' => $orig ? (int)$orig['shares_count'] : 0));

    // ---------------------------------------------------------------- опросы
    case 'vote':
        if (!$me) { fw_json_error($_LANG['FW_ERR_AUTH']); }
        $poll_id = (int)fw_in('poll_id', 0);
        $pid = (int)$model->inDB->get_field('cms_feedwall_posts', "poll_id = '{$poll_id}'", 'id');
        $post = $pid ? fw_visible_post($model, $pid) : false;
        if (!$post) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        if (!$model->vote($poll_id, array_map('intval', fw_in_arr('answers')))) { fw_json_error($_LANG['FW_POLL_ERR']); }
        fw_json_ok(array('html' => fw_tpl('com_feedwall_poll', array('poll' => $model->getPoll($poll_id), 'post' => $post))));

    case 'voters':
        $poll_id = (int)fw_in('poll_id', 0);
        $pid = (int)$model->inDB->get_field('cms_feedwall_posts', "poll_id = '{$poll_id}'", 'id');
        $post = $pid ? fw_visible_post($model, $pid) : false;
        if (!$post) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $users = $model->getVoters($poll_id, (int)fw_in('answer_id', 0));
        fw_json_ok(array('html' => fw_tpl('com_feedwall_users', array('users' => $users, 'title' => $_LANG['FW_VOTERS'], 'show_reaction' => false))));

    // ---------------------------------------------------------------- жалобы
    case 'complain':
        if (!$cfg['complaints']) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $comment_id = (int)fw_in('comment_id', 0);
        $post_id = (int)fw_in('post_id', 0);
        if ($comment_id){ $c = $model->getComment($comment_id); $post_id = $c ? (int)$c['post_id'] : 0; }
        if (!fw_visible_post($model, $post_id)) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $reason = fw_in('reason');
        if (trim($reason) === '') { fw_json_error($_LANG['FW_COMPLAIN_PROMPT']); }
        $model->addComplaint($post_id, $comment_id, $reason);
        fw_json_ok(array('message' => $_LANG['FW_COMPLAIN_SENT']));

    // ---------------------------------------------------------------- загрузка файлов
    case 'upload':
        if (!$me) { fw_json_error($_LANG['FW_ERR_AUTH']); }
        $type = fw_in('type', 'photo');
        if (!in_array($type, array('photo', 'audio', 'voice'), true)) { fw_json_error($_LANG['FW_ERR_TYPE']); }
        $ok = ($type == 'photo' && ($model->can('photo') || $model->can('comment_media') || $model->can('poll')))
           || ($type == 'audio' && $model->can('audio'))
           || ($type == 'voice' && $model->can('comment_media') && $cfg['voice']);
        if (!$ok) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $m = $model->uploadMedia($type, isset($_FILES['file']) ? $_FILES['file'] : null, (int)fw_in('duration', 0));
        if (!is_array($m)) { fw_json_error($m); }
        fw_json_ok(array('media' => array('id' => (int)$m['id'], 'type' => $m['type'], 'file' => $m['file'], 'thumb' => $m['thumb'],
                                           'title' => html_entity_decode($m['title'], ENT_QUOTES, 'UTF-8'), 'artist' => html_entity_decode($m['artist'], ENT_QUOTES, 'UTF-8'),
                                           'duration' => (int)$m['duration'])));

    // ---------------------------------------------------------------- превью ссылок / видео / gif
    case 'link_preview':
        if (!$me || !$cfg['link_preview']) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $url = fw_in('url');
        $v = fw_parse_video($url);
        if ($v && $model->can('video')) { fw_json_ok(array('video' => $v)); }
        $l = fw_link_preview($url);
        if (!$l) { fw_json_error($_LANG['FW_NOTHING_FOUND']); }
        foreach(array('title','desc','site') as $k){ $l[$k] = html_entity_decode($l[$k], ENT_QUOTES, 'UTF-8'); }
        fw_json_ok(array('link' => $l));

    case 'video_parse':
        $v = fw_parse_video(fw_in('url'));
        if (!$v) { fw_json_error($_LANG['FW_VIDEO_BAD']); }
        fw_json_ok(array('video' => $v));

    case 'gifs':
        if (!$cfg['gifs']) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        fw_json_ok(array('list' => $model->searchGifs(fw_in('q'), (int)fw_in('cat', 0)),
                         'cats' => (int)fw_in('with_cats', 0) ? $model->getGifCats() : array()));

    // ---------------------------------------------------------------- упоминания и карточка
    case 'mention':
        if (!$me || !$cfg['mentions']) { fw_json_ok(array('list' => array())); }
        $q = preg_replace('/[^\p{L}\p{N}_\-\.]/u', '', fw_in('q'));
        $list = array();
        foreach($model->searchUsers($q, 8) as $u){
            $list[] = array('id' => (int)$u['id'], 'login' => $u['login'], 'nickname' => html_entity_decode($u['nickname'], ENT_QUOTES, 'UTF-8'), 'avatar' => $u['avatar']);
        }
        fw_json_ok(array('list' => $list));

    case 'usercard':
        $u = $model->getUser((int)fw_in('user_id', 0));
        if (!$u || $u['is_deleted']) { fw_json_error($_LANG['FW_ERR_ACCESS']); }
        $u['posts_count']   = (int)$model->inDB->rows_count('cms_feedwall_posts', "user_id = '{$u['id']}' AND is_anonymous = 0 AND type IN ('post','share')");
        $u['friends_count'] = (int)cmsUser::getFriendsCount($u['id']);
        $u['status_text']   = (string)$model->inDB->get_field('cms_users', "id = '{$u['id']}'", 'status');
        $u['is_friend']     = $me && $me != $u['id'] ? (bool)cmsUser::isFriend($u['id']) : false;
        fw_json_ok(array('html' => fw_tpl('com_feedwall_usercard', array('u' => $u))));

    // ---------------------------------------------------------------- счётчики
    case 'views':
        fw_json_ok(array('n' => $model->registerViews(array_map('intval', fw_in_arr('ids')))));

    case 'audio_play':
        $id = (int)fw_in('id', 0);
        $model->inDB->query("UPDATE cms_feedwall_media SET plays_count = plays_count + 1 WHERE id = '{$id}' AND type IN ('audio','voice')");
        fw_json_ok();

    }

    fw_json_error('unknown action');

}
