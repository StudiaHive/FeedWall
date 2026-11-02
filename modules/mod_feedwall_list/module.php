<?php
/******************************************************************************/
//                                                                            //
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                                                                            //
//                        LICENSED BY GNU/GPL v2                              //
//                                                                            //
/******************************************************************************/

if (!function_exists('mod_feedwall_init')){
    function mod_feedwall_init(){
        if (!cmsCore::getInstance()->isComponentEnable('feedwall')) { return false; }
        if (!function_exists('fw_model')) { cmsCore::includeFile('components/feedwall/includes/render.php'); }
        if (!function_exists('fw_model')) { return false; }
        fw_model()->lang();
        return true;
    }
    
    function mod_feedwall_tpl($cfg, $def){
        $tpl = isset($cfg['tpl']) ? preg_replace('/\.tpl$/', '', basename((string)$cfg['tpl'])) : $def;
        return preg_match('/^[a-z0-9_]+$/i', $tpl) ? $tpl : $def;
    }
}

function mod_feedwall_list($mod, $cfg){

    if (!mod_feedwall_init()) { return false; }

    $model = fw_model();
    $inDB  = cmsDatabase::getInstance();

    $limit  = isset($cfg['limit']) ? max(1, min(50, (int)$cfg['limit'])) : 5;
    $source = isset($cfg['source']) && in_array($cfg['source'], array('all', 'popular', 'discussed', 'feed'), true) ? $cfg['source'] : 'all';
    $show_text = !isset($cfg['show_text']) || $cfg['show_text'];
    $show_link = !isset($cfg['show_link']) || $cfg['show_link'];

    $where = "p.is_pub = 1 AND p.is_private = 0 AND p.is_anonymous = 0 AND p.type IN ('post', 'rss')
              AND (p.target_type <> 'club' OR p.target_id IN (SELECT c.id FROM cms_clubs c WHERE c.clubtype <> 'private' AND c.published = 1))";
    $order = 'p.date_pub DESC';
    if ($source == 'feed') { $where .= " AND p.target_type = 'feed'"; }
    if ($source == 'popular' || $source == 'discussed'){
        $where .= " AND p.date_pub >= '".date('Y-m-d H:i:s', time() - 86400 * 7)."'";
        $order = ($source == 'popular' ? 'p.likes_count DESC, p.comments_count DESC' : 'p.comments_count DESC, p.likes_count DESC').', p.date_pub DESC';
    }

    $res = $inDB->query("SELECT p.id, p.text, p.date_pub, p.likes_count, p.comments_count, p.user_id, p.guest_name, p.attach,
                                u.login, u.nickname, up.imageurl
                         FROM cms_feedwall_posts p
                         LEFT JOIN cms_users u ON u.id = p.user_id
                         LEFT JOIN cms_user_profiles up ON up.user_id = p.user_id
                         WHERE {$where} AND (p.user_id = 0 OR u.is_deleted = 0)
                         ORDER BY {$order} LIMIT {$limit}");

    $items = array();
    while($p = $inDB->fetch_assoc($res)){
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode((string)$p['text'], ENT_QUOTES, 'UTF-8')));
        $cut  = mb_substr($text, 0, 140);
        if (mb_strlen($text) > 140) { $cut .= '…'; }
        $att = $p['attach'] ? (array)json_decode($p['attach'], true) : array();
        $items[] = array(
            'id'       => (int)$p['id'],
            'url'      => '/feedwall/post/'.(int)$p['id'],
            'text'     => $cut,
            'date'     => cmsCore::dateFormat($p['date_pub']),
            'likes'    => (int)$p['likes_count'],
            'comments' => (int)$p['comments_count'],
            'author'   => $p['user_id'] ? (string)$p['nickname'] : (string)$p['guest_name'],
            'author_url' => $p['user_id'] ? cmsUser::getProfileURL($p['login']) : '',
            'avatar'   => cmsUser::getUserAvatarUrl($p['user_id'], 'small', $p['imageurl'], 0),
            'has_media'=> !empty($att['photos']) || !empty($att['videos']) || !empty($att['audios']) || !empty($att['gif'])
        );
    }

    if (!$items) { return false; }

    fw_add_assets();

    echo fw_tpl(mod_feedwall_tpl($cfg, 'mod_feedwall_list'), array('items' => $items, 'show_text' => $show_text, 'show_link' => $show_link, 'mod' => $mod), 'modules');
    return true;

}
