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

cmsCore::includeFile('components/feedwall/includes/defaults.php');
cmsCore::includeFile('components/feedwall/includes/helpers.php');

#[\AllowDynamicProperties]
class cms_model_feedwall {

    public $config = array();
    public $inDB;
    public $inUser;

    private $users      = array();
    private $usercfg    = array();
    private $club_cache = array();
    private $bg_cache   = null;

    public function __construct(){
        $this->inDB   = cmsDatabase::getInstance();
        if (!class_exists('cmsUser', false)) { cmsCore::loadClass('user'); } // CRON: ядро может не загрузить класс пользователя
        $this->inUser = cmsUser::getInstance();
        $cfg = cmsCore::getInstance()->loadComponentConfig('feedwall');
        $this->config = array_merge(feedwall_default_config(), is_array($cfg) ? $cfg : array());
    }

    public static function getDefaultConfig(){ return feedwall_default_config(); }

    // ====================================================================== //
    //                              ОБЩЕЕ                                     //
    // ====================================================================== //

    public function now(){ return date('Y-m-d H:i:s'); }

    public function e($str){ return $this->inDB->escape_string((string)$str); }

    public function ip(){ return mb_substr(preg_replace('/[^0-9a-fA-F:.]/', '', (string)@$_SERVER['REMOTE_ADDR']), 0, 45); }

    public function viewerKey(){
        if ($this->inUser->id) { return 'u'.$this->inUser->id; }
        return 'g'.md5($this->ip().'|'.(string)@$_SERVER['HTTP_USER_AGENT']);
    }

    public function can($perm){
        if (!$this->inUser->id) { return false; }
        return (bool)cmsUser::isUserCan('feedwall/'.$perm);
    }

    public function isModerator(){
        return $this->inUser->id && ($this->inUser->is_admin || cmsUser::isUserCan('feedwall/moderate'));
    }

    public function getMeta($name, $default = ''){
        $v = $this->inDB->get_field('cms_feedwall_meta', "name = '".$this->e($name)."'", 'value');
        return $v === false ? $default : $v;
    }

    public function setMeta($name, $value){
        $this->inDB->query("INSERT INTO cms_feedwall_meta (name, value) VALUES ('".$this->e($name)."', '".$this->e($value)."')
                            ON DUPLICATE KEY UPDATE value = VALUES(value)");
    }

    public function addRating($user_id, $points){
        $user_id = (int)$user_id; $points = (int)$points;
        if ($user_id <= 0 || !$points) { return; }
        $this->inDB->query("UPDATE cms_users SET rating = rating + ({$points}) WHERE id = '{$user_id}' LIMIT 1");
    }

    public function notify($to_id, $kind, $html){
        $to_id = (int)$to_id;
        if ($to_id <= 0 || $to_id == $this->inUser->id) { return false; }
        $cfg = $this->getUserCfg($to_id);
        if (isset($cfg['notify_'.$kind]) && !$cfg['notify_'.$kind]) { return false; }
        return cmsUser::sendMessage(USER_UPDATER, $to_id, $html);
    }

    public function lang(){
        global $_LANG;
        cmsCore::loadLanguage('components/feedwall');
        return $_LANG;
    }

    public function userLink($login, $nickname){
        return '<a href="'.cmsUser::getProfileURL($login).'">'.fw_esc($nickname).'</a>';
    }

    // ====================================================================== //
    //                           ПОЛЬЗОВАТЕЛИ                                 //
    // ====================================================================== //

    private function userSql($where){
        return "SELECT u.id, u.login, u.nickname, u.is_deleted, u.group_id, u.status, p.imageurl
                FROM cms_users u LEFT JOIN cms_user_profiles p ON p.user_id = u.id WHERE {$where}";
    }

    public function loadUsers($ids){
        $ids  = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
        $need = array_diff($ids, array_keys($this->users));
        if ($need){
            $res = $this->inDB->query($this->userSql("u.id IN (".implode(',', $need).")"));
            while($u = $this->inDB->fetch_assoc($res)){ $this->users[$u['id']] = $this->prepareUser($u); }
        }
        $out = array();
        foreach($ids as $id){ if (isset($this->users[$id])) { $out[$id] = $this->users[$id]; } }
        return $out;
    }

    public function getUser($id){
        $u = $this->loadUsers(array($id));
        return isset($u[$id]) ? $u[$id] : false;
    }

    public function getUsersByLogins($logins){
        $out = array();
        if (!$logins) { return $out; }
        $in = array();
        foreach($logins as $l){ $in[] = "'".$this->e($l)."'"; }
        $res = $this->inDB->query($this->userSql("u.login IN (".implode(',', $in).") AND u.is_deleted = 0"));
        while($u = $this->inDB->fetch_assoc($res)){
            $u = $this->prepareUser($u);
            $this->users[$u['id']] = $u;
            $out[mb_strtolower($u['login'])] = $u;
        }
        return $out;
    }

    private function prepareUser($u){
        $u['avatar']      = cmsUser::getUserAvatarUrl($u['id'], 'small', $u['imageurl'], $u['is_deleted']);
        $u['url']         = cmsUser::getProfileURL($u['login']);
        $u['is_verified'] = $this->isVerified($u);
        return $u;
    }

    public function isVerified($u){
        $groups = array_filter(array_map('intval', explode(',', (string)$this->config['verified_groups'])));
        $users  = array_filter(array_map('intval', explode(',', (string)$this->config['verified_users'])));
        return in_array((int)$u['group_id'], $groups) || in_array((int)$u['id'], $users);
    }

    public function getUserCfg($user_id){
        $user_id = (int)$user_id;
        if (isset($this->usercfg[$user_id])) { return $this->usercfg[$user_id]; }
        $cfg = $user_id ? $this->inDB->get_fields('cms_feedwall_usercfg', "user_id = '{$user_id}'", '*', 'user_id') : false;
        if (!$cfg){
            $cfg = array('user_id'=>$user_id, 'who_write'=>'all', 'who_comment'=>'all', 'who_react'=>'all',
                         'allow_mentions'=>1, 'in_feed'=>1, 'notify_wall'=>1, 'notify_mention'=>1,
                         'notify_comment'=>1, 'notify_reply'=>1, 'notify_react'=>0, 'notify_share'=>1);
        }
        return $this->usercfg[$user_id] = $cfg;
    }

    public function saveUserCfg($user_id, $data){
        $user_id = (int)$user_id;
        $allowed = array('all', 'friends', 'nobody');
        $row = array('user_id' => $user_id);
        foreach(array('who_write', 'who_comment', 'who_react') as $k){
            $row[$k] = in_array(@$data[$k], $allowed, true) ? $data[$k] : 'all';
        }
        foreach(array('allow_mentions','in_feed','notify_wall','notify_mention','notify_comment','notify_reply','notify_react','notify_share') as $k){
            $row[$k] = empty($data[$k]) ? 0 : 1;
        }
        $upd = array();
        foreach(array_keys($row) as $k){ if ($k != 'user_id') { $upd[] = "{$k} = VALUES({$k})"; } }
        $this->inDB->query("INSERT INTO cms_feedwall_usercfg (".implode(',', array_keys($row)).") VALUES ('".implode("','", $row)."')
                            ON DUPLICATE KEY UPDATE ".implode(',', $upd));
        unset($this->usercfg[$user_id]);
        return true;
    }

    /** Правило приватности all|friends|nobody относительно владельца */
    public function checkPrivacy($owner_id, $rule){
        $me = $this->inUser->id;
        if ($this->inUser->is_admin || ($me && $me == $owner_id)) { return true; }
        if ($rule == 'all') { return true; }
        if ($rule == 'friends') { return $me && cmsUser::isFriend($owner_id); }
        return false;
    }

    public function myFriendIds(){
        if (!$this->inUser->id) { return array(); }
        return array_map('intval', array_keys((cmsUser::getFriends($this->inUser->id) ?: array())));
    }

    public function searchUsers($q, $limit = 8){
        $q = trim((string)$q);
        $list = array();
        if ($this->config['mention_source'] == 'friends' && $this->inUser->id){
            foreach((cmsUser::getFriends($this->inUser->id) ?: array()) as $f){
                if ($q === '' || mb_stripos($f['login'], $q) === 0 || mb_stripos($f['nickname'], $q) !== false){
                    $list[] = array('id'=>$f['id'], 'login'=>$f['login'], 'nickname'=>$f['nickname'], 'avatar'=>$f['avatar']);
                }
                if (count($list) >= $limit) { break; }
            }
            return $list;
        }
        if ($q === '') { return $list; }
        $eq = $this->e(addcslashes($q, '%_'));
        $res = $this->inDB->query($this->userSql("u.is_deleted = 0 AND u.is_locked = 0 AND (u.login LIKE '{$eq}%' OR u.nickname LIKE '%{$eq}%')")
                                  ." ORDER BY u.logdate DESC LIMIT ".(int)$limit);
        while($u = $this->inDB->fetch_assoc($res)){
            $u = $this->prepareUser($u);
            $list[] = array('id'=>$u['id'], 'login'=>$u['login'], 'nickname'=>$u['nickname'], 'avatar'=>$u['avatar']);
        }
        return $list;
    }

    // ====================================================================== //
    //                                КЛУБЫ                                   //
    // ====================================================================== //

    public function getClub($club_id){
        $club_id = (int)$club_id;
        if (array_key_exists($club_id, $this->club_cache)) { return $this->club_cache[$club_id]; }
        $club = $this->inDB->get_fields('cms_clubs', "id = '{$club_id}' AND published = 1", 'id, title, admin_id, clubtype, imageurl');
        if ($club){
            $club['role'] = '';
            if ($this->inUser->id){
                if ($club['admin_id'] == $this->inUser->id) { $club['role'] = 'admin'; }
                else { $club['role'] = (string)$this->inDB->get_field('cms_user_clubs', "club_id = '{$club_id}' AND user_id = '{$this->inUser->id}'", 'role'); }
            }
            $club['is_member']  = (bool)$club['role'];
            $club['is_manager'] = in_array($club['role'], array('admin', 'moderator'));
            $club['can_view']   = $club['clubtype'] != 'private' || $club['is_member'] || $this->inUser->is_admin;
            $club['url']        = '/clubs/'.$club['id'];
        }
        return $this->club_cache[$club_id] = $club;
    }

    // ====================================================================== //
    //                    СИНХРОНИЗАЦИЯ С ЛЕНТОЙ АКТИВНОСТИ                   //
    // ====================================================================== //

    /**
     * Переносит новые записи cms_actions_log в ленту (инкрементно, с троттлингом)
     */
    public function syncActivity($force = false){

        if (!$this->config['show_activity']) { return 0; }

        if (!$force && time() - (int)$this->getMeta('activity_sync_time', 0) < 30) { return 0; }
        $this->setMeta('activity_sync_time', time());

        $last_id = (int)$this->getMeta('activity_last_id', 0);

        $types = array_filter(array_map('trim', explode(',', (string)$this->config['activity_types'])));
        $type_sql = '';
        if ($types){
            $q = array(); foreach($types as $t){ $q[] = "'".$this->e($t)."'"; }
            $type_sql = ' AND a.name IN ('.implode(',', $q).')';
        }

        $res = $this->inDB->query("SELECT log.id, log.user_id, log.pubdate
                                   FROM cms_actions_log log
                                   INNER JOIN cms_actions a ON a.id = log.action_id AND a.is_visible = 1 AND a.component <> 'feedwall'
                                   WHERE log.id > {$last_id} {$type_sql}
                                   ORDER BY log.id ASC LIMIT 500");
        $n = 0; $max = $last_id;
        while($row = $this->inDB->fetch_assoc($res)){
            $date = ($row['pubdate'] && $row['pubdate'] > '1971-01-01') ? $row['pubdate'] : $this->now();
            $this->inDB->query("INSERT INTO cms_feedwall_posts (user_id, target_type, target_id, type, action_log_id, text, attach, date_pub, is_pub)
                                VALUES ('".(int)$row['user_id']."', 'feed', 0, 'activity', '{$row['id']}', '', '', '{$date}', 1)");
            $max = max($max, (int)$row['id']); $n++;
        }
        if ($n < 500){
            $top = (int)$this->inDB->get_field('cms_actions_log', '1=1 ORDER BY id DESC', 'id');
            if ($top > $max) { $max = $top; }
        }
        if ($max > $last_id) { $this->setMeta('activity_last_id', $max); }

        // события, удалённые из лога
        $this->inDB->query("DELETE p FROM cms_feedwall_posts p LEFT JOIN cms_actions_log l ON l.id = p.action_log_id
                            WHERE p.type = 'activity' AND l.id IS NULL");

        return $n;

    }

    // ====================================================================== //
    //                               ВЫБОРКИ                                  //
    // ====================================================================== //

    /**
     * $ctx: type feed|friends|my|wall|tag|likes|single|user, sort, period, target_type, target_id, tag, user_id, post_id
     */
    private function buildWhere($ctx){

        $me  = (int)$this->inUser->id;
        $w   = array('p.is_pub = 1');
        $type = isset($ctx['type']) ? $ctx['type'] : 'feed';

        switch($type){
            case 'single':
                $w[] = "p.id = '".(int)$ctx['post_id']."'";
                break;
            case 'wall':
                $w[] = "p.target_type = '".($ctx['target_type'] == 'club' ? 'club' : 'user')."' AND p.target_id = '".(int)$ctx['target_id']."'";
                break;
            case 'user':
                $w[] = "p.user_id = '".(int)$ctx['user_id']."' AND p.is_anonymous = 0";
                break;
            case 'tag':
                $tag_id = (int)$this->inDB->get_field('cms_feedwall_tags', "tag = '".$this->e($ctx['tag'])."'", 'id');
                $w[] = "p.id IN (SELECT post_id FROM cms_feedwall_post_tags WHERE tag_id = '{$tag_id}')";
                break;
            case 'likes':
                $w[] = "p.id IN (SELECT post_id FROM cms_feedwall_reactions WHERE user_id = '".(int)$ctx['user_id']."' AND comment_id = 0)";
                break;
            case 'friends':
                $ids = $this->myFriendIds(); $ids[] = $me;
                $w[] = "p.user_id IN (".implode(',', array_map('intval', $ids)).") AND p.is_anonymous = 0";
                break;
            case 'my':
                $w[] = "p.user_id = '{$me}'";
                break;
            default: // общая лента: записи на чужих стенах — если владелец не отключил
                $w[] = "(p.target_type <> 'user' OR p.target_id = p.user_id OR NOT EXISTS (SELECT 1 FROM cms_feedwall_usercfg ucf WHERE ucf.user_id = p.target_id AND ucf.in_feed = 0))";
        }

        if (!$this->config['show_activity'] && $type != 'single') { $w[] = "p.type <> 'activity'"; }

        // события: запись лога должна существовать и быть видимой
        $act = "l.id IS NOT NULL AND a.id IS NOT NULL";

        if (!$this->inUser->is_admin){

            $friends = $this->myFriendIds(); $friends[] = $me; $friends[] = 0;
            $fin = implode(',', array_map('intval', $friends));

            // приватные посты: автору, его друзьям, владельцу стены
            $w[] = $me ? "(p.is_private = 0 OR p.user_id IN ({$fin}) OR (p.target_type = 'user' AND p.target_id = '{$me}'))" : "p.is_private = 0";

            // закрытые клубы
            $w[] = "(p.target_type <> 'club' OR p.target_id IN (SELECT c.id FROM cms_clubs c WHERE c.clubtype <> 'private')"
                 .($me ? " OR p.target_id IN (SELECT uc.club_id FROM cms_user_clubs uc WHERE uc.user_id = '{$me}') OR p.target_id IN (SELECT c2.id FROM cms_clubs c2 WHERE c2.admin_id = '{$me}')" : '').")";

            if (!$me) { $act .= " AND l.is_users_only = 0"; }
            $act .= " AND (l.is_friends_only = 0 OR l.user_id IN ({$fin}))";

        }

        $w[] = "(p.type <> 'activity' OR ({$act}))";

        // скрытое мной
        if ($me && !in_array($type, array('single', 'wall', 'user', 'my'))){
            $w[] = "NOT EXISTS (SELECT 1 FROM cms_feedwall_hidden h WHERE h.user_id = '{$me}' AND (h.post_id = p.id OR (h.author_id > 0 AND h.author_id = p.user_id)))";
        }

        if (!empty($ctx['period']) && in_array($ctx['period'], array('day', 'week', 'month'))){
            $days = array('day'=>1, 'week'=>7, 'month'=>30);
            $w[] = "p.date_pub >= '".date('Y-m-d H:i:s', time() - 86400 * $days[$ctx['period']])."'";
        }

        return implode(' AND ', $w);

    }

    private function buildOrder($ctx){
        $sort = isset($ctx['sort']) ? $ctx['sort'] : 'new';
        $type = isset($ctx['type']) ? $ctx['type'] : 'feed';
        $pin  = '';
        if ($type == 'wall') { $pin = 'p.is_pinned DESC, '; }
        if ($type == 'feed' && $sort == 'new') { $pin = 'p.is_pinned_feed DESC, '; }
        switch($sort){
            case 'old':       return $pin.'p.date_pub ASC, p.id ASC';
            case 'popular':   return $pin.'p.likes_count DESC, p.date_pub DESC';
            case 'discussed': return $pin.'p.comments_count DESC, p.date_pub DESC';
            case 'viewed':    return $pin.'p.views_count DESC, p.date_pub DESC';
            default:          return $pin.'p.date_pub DESC, p.id DESC';
        }
    }

    private function fromSql(){
        return "FROM cms_feedwall_posts p
                LEFT JOIN cms_actions_log l ON l.id = p.action_log_id AND p.type = 'activity'
                LEFT JOIN cms_actions a ON a.id = l.action_id AND a.is_visible = 1";
    }

    private function selectSql($where){
        return "SELECT p.*, l.id AS l_id, l.object, l.object_url, l.object_id, l.target AS l_target, l.target_url AS l_target_url,
                       l.description AS l_description, a.name AS a_name, a.message AS a_message
                ".$this->fromSql()." WHERE {$where}";
    }

    public function countPosts($ctx){
        $res = $this->inDB->query("SELECT COUNT(*) AS c ".$this->fromSql()." WHERE ".$this->buildWhere($ctx));
        $r = $this->inDB->fetch_assoc($res);
        return (int)$r['c'];
    }

    /** Возвращает array(items, has_more) */
    public function getPosts($ctx, $page = 1, $perpage = 0){
        $perpage = $perpage ? (int)$perpage : max(1, (int)$this->config['perpage']);
        $page    = max(1, (int)$page);
        $res = $this->inDB->query($this->selectSql($this->buildWhere($ctx)).' ORDER BY '.$this->buildOrder($ctx).
                                  ' LIMIT '.(($page - 1) * $perpage).', '.($perpage + 1));
        $rows = array();
        while($r = $this->inDB->fetch_assoc($res)){ $rows[] = $r; }
        $has_more = count($rows) > $perpage;
        if ($has_more) { array_pop($rows); }
        return array($this->preparePosts($rows, $ctx), $has_more);
    }

    /** $raw — строка таблицы без проверки видимости */
    public function getPost($id, $raw = false){
        $id = (int)$id;
        if ($raw) { return $this->inDB->get_fields('cms_feedwall_posts', "id = '{$id}'", '*'); }
        list($items) = $this->getPosts(array('type'=>'single', 'post_id'=>$id, 'comm_limit'=>0), 1, 1);
        return $items ? $items[0] : false;
    }

    // ====================================================================== //
    //                           ПОДГОТОВКА ПОСТОВ                            //
    // ====================================================================== //

    public function getBackgrounds($only_pub = true){
        if ($only_pub && $this->bg_cache !== null) { return $this->bg_cache; }
        $list = array();
        $res = $this->inDB->query("SELECT * FROM cms_feedwall_backgrounds ".($only_pub ? 'WHERE is_pub = 1' : '')." ORDER BY ordering, id");
        while($b = $this->inDB->fetch_assoc($res)){
            $css = preg_replace('/[<>"\\\\]|expression|javascript:|@import|behavior/i', '', $b['css']);
            $b['style'] = fw_esc($css.';color:'.preg_replace('/[^#a-zA-Z0-9(),. ]/', '', $b['color']));
            $list[$b['id']] = $b;
        }
        if ($only_pub) { $this->bg_cache = $list; }
        return $list;
    }

    public function preparePosts($rows, $ctx = array()){

        if (!$rows) { return array(); }

        $me = (int)$this->inUser->id;

        $uids = array(); $share_ids = array(); $texts = ''; $all_ids = array();
        foreach($rows as $r){
            $all_ids[] = (int)$r['id'];
            $uids[] = $r['user_id'];
            if ($r['target_type'] == 'user') { $uids[] = $r['target_id']; }
            if ($r['share_id']) { $share_ids[] = (int)$r['share_id']; }
            $texts .= ' '.$r['text'];
        }

        $originals = array();
        if ($share_ids){
            $res = $this->inDB->query($this->selectSql("p.id IN (".implode(',', array_unique($share_ids)).") AND p.is_pub = 1"));
            while($o = $this->inDB->fetch_assoc($res)){ $originals[$o['id']] = $o; $uids[] = $o['user_id']; $texts .= ' '.$o['text']; }
        }

        $this->loadUsers($uids);
        $mention_users = $this->config['mentions'] ? $this->getUsersByLogins(fw_extract_mentions($texts)) : array();

        $my_react = array();
        if ($me){
            $res = $this->inDB->query("SELECT post_id, reaction FROM cms_feedwall_reactions WHERE user_id = '{$me}' AND comment_id = 0 AND post_id IN (".implode(',', $all_ids).")");
            while($x = $this->inDB->fetch_assoc($res)){ $my_react[$x['post_id']] = $x['reaction']; }
        }

        $items = array();
        foreach($rows as $r){
            $item = $this->prepareOne($r, $mention_users);
            if (!$item) { continue; }
            $item['my_reaction'] = isset($my_react[$r['id']]) ? $my_react[$r['id']] : '';
            if ($r['share_id']){
                $item['original'] = isset($originals[$r['share_id']]) ? $this->prepareOne($originals[$r['share_id']], $mention_users) : false;
            }
            $items[] = $item;
        }

        // превью комментариев
        $limit = isset($ctx['comm_limit']) ? (int)$ctx['comm_limit'] : (int)$this->config['comm_preview'];
        if (!$this->config['off_comments'] && $limit){
            foreach($items as $k => $item){
                if ($item['comments_count'] && $item['social']){
                    $items[$k]['comments'] = $this->getComments($item['id'], 0, $limit, 0, true);
                }
            }
        }

        return $items;

    }

    private function prepareOne($r, $mention_users){

        $me  = (int)$this->inUser->id;
        $cfg = $this->config;

        $item = $r;
        $item['author']   = ($r['user_id'] && !$r['is_anonymous']) ? $this->getUser($r['user_id']) : false;
        $item['is_guest'] = !$r['user_id'];
        $item['is_my']    = $me && $r['user_id'] == $me;
        $item['url']      = '/feedwall/post/'.$r['id'];
        $item['date']     = cmsCore::dateDiffNow($r['date_pub']);
        $item['date_full']= cmsCore::dateFormat($r['date_pub'], true, true);
        $item['comments'] = array();
        $item['original'] = false;
        $item['social']   = $r['type'] != 'activity' || $cfg['activity_social'];

        // куда написано
        $item['target'] = false;
        if ($r['target_type'] == 'user' && $r['target_id'] != $r['user_id']){
            $t = $this->getUser($r['target_id']);
            if ($t) { $item['target'] = array('title'=>$t['nickname'], 'url'=>$t['url'], 'kind'=>'user'); }
        }
        if ($r['target_type'] == 'club'){
            $c = $this->getClub($r['target_id']);
            if ($c) { $item['target'] = array('title'=>$c['title'], 'url'=>$c['url'], 'kind'=>'club'); }
        }

        // событие ленты активности
        $item['activity'] = false;
        if ($r['type'] == 'activity'){
            if (empty($r['l_id']) || empty($r['a_name'])) { return false; }
            $obj = $r['object'] ? ($r['object_url'] ? '<a href="'.fw_esc($r['object_url']).'">'.$r['object'].'</a>' : $r['object']) : '';
            $tgt = $r['l_target'] ? '<a href="'.fw_esc($r['l_target_url']).'">'.$r['l_target'].'</a>' : '';
            $msg = (string)$r['a_message'];
            $pos = mb_strpos($msg, '|');
            if ($pos !== false){ $msg = $r['l_target'] ? str_replace('|', '', $msg) : mb_substr($msg, 0, $pos); }
            $args = array($obj, $tgt); $i = 0;
            $msg = preg_replace_callback('/%s/', function() use (&$args, &$i){ $v = isset($args[$i]) ? $args[$i] : ''; $i++; return $v; }, $msg);
            $image = '';
            if ($cfg['activity_images'] && $r['object_id']){
                if ($r['a_name'] == 'add_article' && file_exists(PATH.'/images/photos/small/article'.(int)$r['object_id'].'.jpg')){
                    $image = '/images/photos/small/article'.(int)$r['object_id'].'.jpg';
                }
                if (in_array($r['a_name'], array('add_photo', 'add_photo_club'))){
                    $f = $this->inDB->get_field('cms_photo_files', "id = '".(int)$r['object_id']."'", 'file');
                    if ($f && file_exists(PATH.'/images/photos/medium/'.$f)) { $image = '/images/photos/medium/'.$f; }
                }
            }
            $item['activity'] = array('name'=>$r['a_name'], 'message'=>$msg, 'description'=>(string)$r['l_description'],
                                      'image'=>$image, 'url'=>$r['object_url']);
        }

        // текст
        $item['html'] = fw_render_text($r['text'], array('mentions'=>$cfg['mentions'], 'users'=>$mention_users,
                                                          'hashtags'=>$cfg['hashtags'], 'smiles'=>$cfg['smiles']));
        $item['bg'] = false;
        if ($r['bg_id']){
            $bgs = $this->getBackgrounds();
            if (isset($bgs[$r['bg_id']])) { $item['bg'] = $bgs[$r['bg_id']]; }
        }

        // вложения
        $att = $r['attach'] ? json_decode($r['attach'], true) : array();
        if (!is_array($att)) { $att = array(); }
        $item['photos'] = array(); $item['audios'] = array();
        $mids = array_merge(isset($att['photos']) ? (array)$att['photos'] : array(), isset($att['audios']) ? (array)$att['audios'] : array());
        if ($mids){
            $media = $this->getMediaByIds($mids);
            foreach((array)@$att['photos'] as $mid){ if (isset($media[$mid])) { $item['photos'][] = $media[$mid]; } }
            foreach((array)@$att['audios'] as $mid){ if (isset($media[$mid])) { $item['audios'][] = $media[$mid]; } }
        }
        $item['videos'] = isset($att['videos']) && is_array($att['videos']) ? $att['videos'] : array();
        $item['link']   = isset($att['link']) && is_array($att['link']) ? $att['link'] : false;
        $item['gif']    = isset($att['gif']) && is_array($att['gif']) ? $att['gif'] : false;
        $item['photos_count'] = count($item['photos']);
        $item['attach_ids'] = array('photos'=>array(), 'audios'=>array());
        foreach($item['photos'] as $m){ $item['attach_ids']['photos'][] = (int)$m['id']; }
        foreach($item['audios'] as $m){ $item['attach_ids']['audios'][] = (int)$m['id']; }

        $item['poll'] = $r['poll_id'] ? $this->getPoll($r['poll_id']) : false;

        // реакции
        $item['reactions_list'] = array();
        $rj = $r['reactions'] ? json_decode($r['reactions'], true) : array();
        if (is_array($rj)){ arsort($rj); foreach($rj as $k => $v){ if ($v > 0) { $item['reactions_list'][$k] = (int)$v; } } }

        // права
        $item['can_edit']    = $this->canEditPost($r);
        $item['can_delete']  = $this->canDeletePost($r);
        $item['can_pin']     = $this->canPin($r);
        $item['can_pin_feed']= $this->isModerator() && $r['type'] != 'activity';
        $item['can_react']   = $this->canReact($r);
        $item['can_comment'] = $this->canComment($r);
        $item['can_share']   = !$cfg['off_share'] && $this->can('share') && $r['type'] != 'share' && !$r['is_private'] && $item['social'];

        return $item;

    }

    // ====================================================================== //
    //                                 ПРАВА                                  //
    // ====================================================================== //

    private function inTimeLimit($date, $minutes){
        return !(int)$minutes || (time() - strtotime($date)) < (int)$minutes * 60;
    }

    public function canEditPost($p){
        if (!$this->inUser->id || $p['type'] == 'activity') { return false; }
        if ($this->isModerator()) { return true; }
        return $p['user_id'] == $this->inUser->id && $this->can('edit') && $this->inTimeLimit($p['date_pub'], $this->config['edit_time']);
    }

    public function canDeletePost($p){
        if (!$this->inUser->id) { return false; }
        if ($this->isModerator()) { return true; }
        if ($p['type'] == 'activity') { return false; }
        if ($p['user_id'] == $this->inUser->id && $this->can('delete')) { return true; }
        if ($p['target_type'] == 'user' && $p['target_id'] == $this->inUser->id) { return true; }
        if ($p['target_type'] == 'club'){ $c = $this->getClub($p['target_id']); if ($c && $c['is_manager']) { return true; } }
        return false;
    }

    public function canPin($p){
        if (!$this->inUser->id || $p['type'] == 'activity' || $p['target_type'] == 'feed') { return false; }
        if ($this->isModerator()) { return true; }
        if ($p['target_type'] == 'user' && $p['target_id'] == $this->inUser->id) { return true; }
        if ($p['target_type'] == 'club'){ $c = $this->getClub($p['target_id']); return $c && $c['is_manager']; }
        return false;
    }

    public function canReact($p){
        if ($this->config['off_likes'] || !$this->can('react')) { return false; }
        if ($p['type'] == 'activity' && !$this->config['activity_social']) { return false; }
        if (!$p['user_id']) { return true; }
        $cfg = $this->getUserCfg($p['user_id']);
        return $this->checkPrivacy($p['user_id'], $cfg['who_react']);
    }

    public function canComment($p){
        if ($this->config['off_comments'] || !$this->can('comment')) { return false; }
        if ($p['type'] == 'activity' && !$this->config['activity_social']) { return false; }
        if (!$p['user_id']) { return true; }
        $cfg = $this->getUserCfg($p['user_id']);
        return $this->checkPrivacy($p['user_id'], $cfg['who_comment']);
    }

    /** Может ли текущий пользователь писать в target */
    public function canWriteTo($target_type, $target_id){
        if (!$this->inUser->id){
            return $target_type == 'feed' && $this->config['guest_add'];
        }
        if (!$this->can('add')) { return false; }
        if ($target_type == 'feed') { return true; }
        if ($target_type == 'user'){
            if ($target_id == $this->inUser->id) { return true; }
            if (!$this->inDB->rows_count('cms_users', "id = '".(int)$target_id."' AND is_deleted = 0", 1)) { return false; }
            $cfg = $this->getUserCfg($target_id);
            return $this->checkPrivacy($target_id, $cfg['who_write']);
        }
        if ($target_type == 'club'){
            $c = $this->getClub($target_id);
            return $c && ($c['is_member'] || $this->inUser->is_admin);
        }
        return false;
    }

    public function postsToday($user_id){
        return (int)$this->inDB->rows_count('cms_feedwall_posts', "user_id = '".(int)$user_id."' AND type IN ('post','share') AND date_pub >= '".date('Y-m-d 00:00:00')."'");
    }

    // ====================================================================== //
    //                          СОЗДАНИЕ / ИЗМЕНЕНИЕ                          //
    // ====================================================================== //

    /**
     * $data: text (очищенный), target_type, target_id, photos[], audios[], videos[], link{}, gif, poll{}, bg_id,
     *        is_private, is_anonymous, guest_name, type, share_id, user_id, date_pub, source_url
     */
    public function addPost($data){

        $_LANG   = $this->lang();
        $user_id = isset($data['user_id']) ? (int)$data['user_id'] : (int)$this->inUser->id;
        $text    = (string)@$data['text'];

        $attach  = $this->buildAttach($data, $user_id);
        $poll_id = (!empty($data['poll']) && is_array($data['poll'])) ? $this->addPoll($data['poll'], $user_id) : 0;

        $bg_id = (int)@$data['bg_id'];
        if ($bg_id && ($attach || $poll_id || $text === '' || mb_strlen($text) > (int)$this->config['colored_max'])) { $bg_id = 0; }

        $row = array(
            'user_id'      => $user_id,
            'target_type'  => in_array(@$data['target_type'], array('feed','user','club'), true) ? $data['target_type'] : 'feed',
            'target_id'    => (int)@$data['target_id'],
            'type'         => in_array(@$data['type'], array('post','share','rss'), true) ? $data['type'] : 'post',
            'action_log_id'=> 0,
            'share_id'     => (int)@$data['share_id'],
            'text'         => $this->e($text),
            'attach'       => $this->e($attach ? json_encode($attach) : ''),
            'poll_id'      => $poll_id,
            'bg_id'        => $bg_id,
            'is_pinned'    => 0,
            'is_pinned_feed' => 0,
            'is_anonymous' => empty($data['is_anonymous']) ? 0 : 1,
            'is_private'   => empty($data['is_private']) ? 0 : 1,
            'is_pub'       => 1,
            'likes_count'  => 0, 'reactions' => '', 'comments_count' => 0, 'shares_count' => 0, 'views_count' => 0,
            'date_pub'     => !empty($data['date_pub']) ? $this->e($data['date_pub']) : $this->now(),
            'guest_name'   => $this->e(mb_substr((string)@$data['guest_name'], 0, 100)),
            'ip'           => $this->ip(),
            'source_url'   => $this->e(mb_substr((string)@$data['source_url'], 0, 500))
        );
        if ($row['target_type'] == 'feed') { $row['target_id'] = 0; }

        $id = $this->inDB->insert('cms_feedwall_posts', $row);
        if (!$id) { return false; }

        $this->markMediaUsed($attach);
        if ($this->config['hashtags']) { $this->saveTags($id, $text); }

        if ($row['share_id']){
            $this->inDB->query("UPDATE cms_feedwall_posts SET shares_count = shares_count + 1 WHERE id = '{$row['share_id']}'");
        }

        if ($user_id){

            $post_url = '/feedwall/post/'.$id;
            $u = $this->getUser($user_id);
            $author = $u ? $this->userLink($u['login'], $u['nickname']) : '';

            if ($row['target_type'] == 'user' && $row['target_id'] != $user_id){
                $this->notify($row['target_id'], 'wall', sprintf($_LANG['FW_NOTIFY_WALL'], $author, $post_url));
            }
            if ($this->config['mentions'] && !$row['is_anonymous']){
                $this->notifyMentions($text, sprintf($_LANG['FW_NOTIFY_MENTION_POST'], $author, $post_url));
            }
            if ($row['type'] == 'share' && $row['share_id']){
                $orig = $this->getPost($row['share_id'], true);
                if ($orig) { $this->notify($orig['user_id'], 'share', sprintf($_LANG['FW_NOTIFY_SHARE'], $author, '/feedwall/post/'.$orig['id'])); }
            }
            if ($row['type'] == 'post') { $this->addRating($user_id, $this->config['rating_post']); }

            // запись в стандартной ленте активности сайта
            if (!$row['is_anonymous'] && !$row['is_private'] && $row['type'] != 'rss' && cmsActions::getAction('add_feedwall_post')){
                $title = fw_plain_cut(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), 60);
                cmsActions::log('add_feedwall_post', array(
                    'object' => fw_esc($title !== '' ? $title : $_LANG['FW_POST']),
                    'object_url' => $post_url, 'object_id' => $id, 'user_id' => $user_id,
                    'target' => '', 'target_url' => '', 'target_id' => 0, 'description' => ''
                ));
            }

        }

        return $id;

    }

    public function updatePost($id, $data){

        $post = $this->getPost($id, true);
        if (!$post) { return false; }

        $text   = (string)@$data['text'];
        $attach = $this->buildAttach($data, $post['user_id']);
        $bg_id  = (int)@$data['bg_id'];
        if ($bg_id && ($attach || $post['poll_id'] || $text === '' || mb_strlen($text) > (int)$this->config['colored_max'])) { $bg_id = 0; }

        $old = $post['attach'] ? json_decode($post['attach'], true) : array();
        $removed = array_diff(array_merge((array)@$old['photos'], (array)@$old['audios']),
                              array_merge((array)@$attach['photos'], (array)@$attach['audios']));

        $this->inDB->query("UPDATE cms_feedwall_posts SET text = '".$this->e($text)."', attach = '".$this->e($attach ? json_encode($attach) : '')."',
                            bg_id = '{$bg_id}', is_private = '".(empty($data['is_private']) ? 0 : 1)."', date_edit = '".$this->now()."'
                            WHERE id = '".(int)$id."' LIMIT 1");

        if ($removed) { $this->deleteMedia($removed); }
        $this->markMediaUsed($attach);

        $this->inDB->query("DELETE FROM cms_feedwall_post_tags WHERE post_id = '".(int)$id."'");
        if ($this->config['hashtags']) { $this->saveTags($id, $text); }
        $this->recountTags();

        return true;

    }

    private function buildAttach($data, $user_id){

        $attach = array();

        foreach(array('photos'=>'photo', 'audios'=>'audio') as $key => $type){
            if (empty($data[$key]) || !is_array($data[$key])) { continue; }
            $max = $type == 'photo' ? max(1, (int)$this->config['photos_max']) : 10;
            $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $data[$key])))), 0, $max);
            if (!$ids) { continue; }
            // только свои файлы (администратор — любые)
            $own = $this->inUser->is_admin ? '' : " AND user_id = '".(int)$user_id."'";
            $ok = array();
            $res = $this->inDB->query("SELECT id FROM cms_feedwall_media WHERE id IN (".implode(',', $ids).") AND type = '{$type}'{$own}");
            while($m = $this->inDB->fetch_assoc($res)){ $ok[] = (int)$m['id']; }
            $ids = array_values(array_intersect($ids, $ok));
            if ($ids) { $attach[$key] = $ids; }
        }

        if (!empty($data['videos']) && is_array($data['videos'])){
            $videos = array();
            foreach(array_slice($data['videos'], 0, max(1, (int)$this->config['video_max'])) as $v){
                $url = is_array($v) ? (string)@$v['url'] : (string)$v;
                $pv = fw_parse_video($url);
                if (!$pv) { continue; }
                $pv['title'] = is_array($v) ? fw_clean_text(@$v['title'], 200) : '';
                $videos[] = $pv;
            }
            if ($videos) { $attach['videos'] = $videos; }
        }

        if (!empty($data['link']) && is_array($data['link']) && preg_match('#^https?://[^\s"\'<>]+$#i', (string)@$data['link']['url'])){
            $l = $data['link'];
            $attach['link'] = array(
                'url'   => mb_substr($l['url'], 0, 500),
                'title' => fw_clean_text(@$l['title'], 200),
                'desc'  => fw_clean_text(@$l['desc'], 300),
                'image' => preg_match('#^https?://[^\s"\'<>]+$#i', (string)@$l['image']) ? mb_substr($l['image'], 0, 500) : '',
                'site'  => fw_clean_text(@$l['site'], 100)
            );
        }

        $gif = !empty($data['gif']) ? (is_array($data['gif']) ? (string)@$data['gif']['url'] : (string)$data['gif']) : '';
        if ($gif && preg_match('#^(https?://|/(?!/))[^\s"\'<>]+$#i', $gif)){
            $attach['gif'] = array('url' => mb_substr($gif, 0, 500));
        }

        return $attach;

    }

    public function deletePost($id){

        $id = (int)$id;
        $post = $this->getPost($id, true);
        if (!$post) { return false; }

        $att = $post['attach'] ? json_decode($post['attach'], true) : array();
        $mids = array_merge((array)@$att['photos'], (array)@$att['audios']);

        $cm = array();
        $res = $this->inDB->query("SELECT attach FROM cms_feedwall_comments WHERE post_id = '{$id}' AND attach <> ''");
        while($c = $this->inDB->fetch_assoc($res)){
            $a = json_decode($c['attach'], true);
            $cm = array_merge($cm, (array)@$a['photos'], isset($a['voice']) ? array($a['voice']) : array());
        }

        if ($post['poll_id']){
            $poll = $this->inDB->get_fields('cms_feedwall_polls', "id = '{$post['poll_id']}'", 'answers');
            foreach((array)json_decode((string)@$poll['answers'], true) as $a){ if (!empty($a['image'])) { $mids[] = (int)$a['image']; } }
            $this->inDB->query("DELETE FROM cms_feedwall_polls WHERE id = '{$post['poll_id']}'");
            $this->inDB->query("DELETE FROM cms_feedwall_poll_votes WHERE poll_id = '{$post['poll_id']}'");
        }

        foreach(array('comments', 'reactions', 'post_tags', 'hidden', 'views', 'complaints') as $t){
            $this->inDB->query("DELETE FROM cms_feedwall_{$t} WHERE post_id = '{$id}'");
        }
        $this->inDB->query("DELETE FROM cms_feedwall_posts WHERE id = '{$id}'");

        // файлы удаляем после удаления записи (проверка повторного использования)
        if ($mids) { $this->deleteMedia($mids); }
        if ($cm) { $this->deleteMedia($cm); }

        if ($post['share_id']){
            $this->inDB->query("UPDATE cms_feedwall_posts SET shares_count = GREATEST(shares_count - 1, 0) WHERE id = '{$post['share_id']}'");
        }

        cmsActions::removeObjectLog('add_feedwall_post', $id);
        if ($post['type'] == 'post') { $this->addRating($post['user_id'], -(int)$this->config['rating_post']); }

        $this->recountTags();

        return $post;

    }

    public function setPinned($id, $field, $value){
        $field = $field == 'is_pinned_feed' ? 'is_pinned_feed' : 'is_pinned';
        $this->inDB->query("UPDATE cms_feedwall_posts SET {$field} = '".($value ? 1 : 0)."' WHERE id = '".(int)$id."' LIMIT 1");
    }

    public function hide($user_id, $post_id, $author_id){
        $this->inDB->query("INSERT IGNORE INTO cms_feedwall_hidden (user_id, post_id, author_id) VALUES ('".(int)$user_id."', '".(int)$post_id."', '".(int)$author_id."')");
    }

    public function unhide($user_id, $id){
        $this->inDB->query("DELETE FROM cms_feedwall_hidden WHERE user_id = '".(int)$user_id."' AND id = '".(int)$id."'");
    }

    public function getHidden($user_id){
        $list = array();
        $res = $this->inDB->query("SELECT * FROM cms_feedwall_hidden WHERE user_id = '".(int)$user_id."' ORDER BY id DESC LIMIT 200");
        while($h = $this->inDB->fetch_assoc($res)){
            $h['user'] = $h['author_id'] ? $this->getUser($h['author_id']) : false;
            $list[] = $h;
        }
        return $list;
    }

    // ====================================================================== //
    //                                 ХЭШТЕГИ                                //
    // ====================================================================== //

    private function saveTags($post_id, $text){
        foreach(fw_extract_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8')) as $tag){
            $tag = $this->e(fw_mb4_encode($tag));
            $this->inDB->query("INSERT INTO cms_feedwall_tags (tag, posts_count) VALUES ('{$tag}', 0) ON DUPLICATE KEY UPDATE tag = tag");
            $tag_id = (int)$this->inDB->get_field('cms_feedwall_tags', "tag = '{$tag}'", 'id');
            if ($tag_id){
                $this->inDB->query("INSERT IGNORE INTO cms_feedwall_post_tags (post_id, tag_id) VALUES ('".(int)$post_id."', '{$tag_id}')");
            }
        }
        $this->recountTags();
    }

    public function recountTags(){
        $this->inDB->query("UPDATE cms_feedwall_tags t SET posts_count = (SELECT COUNT(*) FROM cms_feedwall_post_tags pt WHERE pt.tag_id = t.id)");
        $this->inDB->query("DELETE FROM cms_feedwall_tags WHERE posts_count = 0");
    }

    public function getTags($limit = 30){
        $list = array(); $max = 1;
        $res = $this->inDB->query("SELECT tag, posts_count FROM cms_feedwall_tags WHERE posts_count > 0 ORDER BY posts_count DESC, tag LIMIT ".(int)$limit);
        while($t = $this->inDB->fetch_assoc($res)){ $list[] = $t; $max = max($max, (int)$t['posts_count']); }
        foreach($list as $k => $t){
            $list[$k]['size'] = 1 + (int)round(4 * $t['posts_count'] / $max);
            $list[$k]['url']  = '/feedwall/tag/'.rawurlencode($t['tag']);
        }
        usort($list, function($a, $b){ return strcmp($a['tag'], $b['tag']); });
        return $list;
    }

    private function notifyMentions($text, $html){
        $users = $this->getUsersByLogins(fw_extract_mentions($text));
        foreach($users as $u){
            $cfg = $this->getUserCfg($u['id']);
            if (!$cfg['allow_mentions']) { continue; }
            $this->notify($u['id'], 'mention', $html);
        }
    }

    // ====================================================================== //
    //                               РЕАКЦИИ                                  //
    // ====================================================================== //

    /** Ставит / меняет / снимает реакцию. Возвращает текущую реакцию ('' — снята) */
    public function react($post_id, $comment_id, $reaction){

        $me = (int)$this->inUser->id;
        $post_id = (int)$post_id; $comment_id = (int)$comment_id;
        if ($comment_id || !$this->config['reactions']) { $reaction = 'like'; }
        if (!in_array($reaction, feedwall_reaction_types(), true)) { $reaction = 'like'; }

        $w = "post_id = '{$post_id}' AND comment_id = '{$comment_id}' AND user_id = '{$me}'";
        $cur = $this->inDB->get_field('cms_feedwall_reactions', $w, 'reaction');

        $points = $comment_id ? (int)$this->config['rating_like'] : (int)$this->config['rating_react'];
        $owner  = $comment_id ? (int)$this->inDB->get_field('cms_feedwall_comments', "id = '{$comment_id}'", 'user_id')
                              : (int)$this->inDB->get_field('cms_feedwall_posts', "id = '{$post_id}'", 'user_id');

        if ($cur === $reaction){
            $this->inDB->query("DELETE FROM cms_feedwall_reactions WHERE {$w}");
            $result = '';
            if ($owner != $me) { $this->addRating($owner, -$points); }
        } elseif ($cur){
            $this->inDB->query("UPDATE cms_feedwall_reactions SET reaction = '{$reaction}' WHERE {$w}");
            $result = $reaction;
        } else {
            $this->inDB->query("INSERT IGNORE INTO cms_feedwall_reactions (post_id, comment_id, user_id, reaction, date_pub)
                                VALUES ('{$post_id}', '{$comment_id}', '{$me}', '{$reaction}', '".$this->now()."')");
            $result = $reaction;
            if ($owner && $owner != $me){
                $this->addRating($owner, $points);
                $_LANG = $this->lang();
                $who = $this->userLink($this->inUser->login, $this->inUser->nickname);
                $this->notify($owner, 'react', sprintf($comment_id ? $_LANG['FW_NOTIFY_COMMENT_LIKE'] : $_LANG['FW_NOTIFY_REACT'], $who, '/feedwall/post/'.$post_id));
            }
        }

        $this->recountReactions($post_id, $comment_id);

        return $result;

    }

    public function recountReactions($post_id, $comment_id = 0){
        $post_id = (int)$post_id; $comment_id = (int)$comment_id;
        if ($comment_id){
            $n = (int)$this->inDB->rows_count('cms_feedwall_reactions', "comment_id = '{$comment_id}'");
            $this->inDB->query("UPDATE cms_feedwall_comments SET likes_count = '{$n}' WHERE id = '{$comment_id}'");
            return $n;
        }
        $sum = array(); $total = 0;
        $res = $this->inDB->query("SELECT reaction, COUNT(*) c FROM cms_feedwall_reactions WHERE post_id = '{$post_id}' AND comment_id = 0 GROUP BY reaction");
        while($r = $this->inDB->fetch_assoc($res)){ $sum[$r['reaction']] = (int)$r['c']; $total += (int)$r['c']; }
        $this->inDB->query("UPDATE cms_feedwall_posts SET likes_count = '{$total}', reactions = '".$this->e($sum ? json_encode($sum) : '')."' WHERE id = '{$post_id}'");
        return array('total'=>$total, 'list'=>$sum);
    }

    public function getReactors($post_id, $comment_id, $reaction = '', $limit = 100){
        $w = "post_id = '".(int)$post_id."' AND comment_id = '".(int)$comment_id."'";
        if ($reaction && in_array($reaction, feedwall_reaction_types(), true)) { $w .= " AND reaction = '{$reaction}'"; }
        $res = $this->inDB->query("SELECT user_id, reaction FROM cms_feedwall_reactions WHERE {$w} ORDER BY id DESC LIMIT ".(int)$limit);
        $rows = array(); $ids = array();
        while($r = $this->inDB->fetch_assoc($res)){ $rows[] = $r; $ids[] = $r['user_id']; }
        $users = $this->loadUsers($ids);
        $list = array();
        foreach($rows as $r){
            if (!isset($users[$r['user_id']])) { continue; }
            $u = $users[$r['user_id']]; $u['reaction'] = $r['reaction']; $list[] = $u;
        }
        return $list;
    }

    // ====================================================================== //
    //                              КОММЕНТАРИИ                               //
    // ====================================================================== //

    /** Двухуровневое дерево: корневые комментарии и ответы на них */
    public function getComments($post_id, $parent_id = 0, $limit = 0, $offset = 0, $preview = false){

        $post_id = (int)$post_id; $parent_id = (int)$parent_id;
        $sort = ($this->config['comm_sort'] == 'desc' && !$parent_id) ? 'DESC' : 'ASC';

        $sql = "SELECT * FROM cms_feedwall_comments WHERE post_id = '{$post_id}' AND parent_id = '{$parent_id}' AND is_pub = 1";
        if ($preview){
            // последние N в прямом порядке
            $sql = "SELECT * FROM ({$sql} ORDER BY id DESC LIMIT ".(int)$limit.") x ORDER BY id ".$sort;
        } else {
            $sql .= " ORDER BY id {$sort}".($limit ? " LIMIT ".(int)$offset.", ".(int)$limit : '');
        }

        $res = $this->inDB->query($sql);
        $rows = array(); $uids = array(); $ids = array(); $texts = '';
        while($c = $this->inDB->fetch_assoc($res)){
            $rows[] = $c; $uids[] = $c['user_id']; $uids[] = $c['answer_user_id']; $ids[] = (int)$c['id']; $texts .= ' '.$c['text'];
        }
        if (!$rows) { return array(); }

        $this->loadUsers($uids);
        $mention_users = $this->config['mentions'] ? $this->getUsersByLogins(fw_extract_mentions($texts)) : array();

        $liked = array();
        if ($this->inUser->id){
            $r2 = $this->inDB->query("SELECT comment_id FROM cms_feedwall_reactions WHERE user_id = '{$this->inUser->id}' AND comment_id IN (".implode(',', $ids).")");
            while($x = $this->inDB->fetch_assoc($r2)){ $liked[$x['comment_id']] = 1; }
        }

        $post = $this->getPost($post_id, true);
        $list = array();
        foreach($rows as $c){
            $list[] = $this->prepareComment($c, $post, $mention_users, isset($liked[$c['id']]));
        }

        if (!$parent_id){
            foreach($list as $k => $c){
                $list[$k]['answers'] = $c['answers_count'] ? $this->getComments($post_id, $c['id'], $preview ? 2 : 0, 0, $preview) : array();
            }
        }

        return $list;

    }

    private function prepareComment($c, $post, $mention_users, $is_liked){
        $c['user']        = $this->getUser($c['user_id']);
        $c['answer_user'] = $c['answer_user_id'] ? $this->getUser($c['answer_user_id']) : false;
        $c['html']        = fw_render_text($c['text'], array('mentions'=>$this->config['mentions'], 'users'=>$mention_users,
                                                             'hashtags'=>$this->config['hashtags'], 'smiles'=>$this->config['smiles']));
        $c['date']        = cmsCore::dateDiffNow($c['date_pub']);
        $att = $c['attach'] ? json_decode($c['attach'], true) : array();
        $c['photos'] = array(); $c['voice'] = false; $c['gif'] = false;
        $mids = array_merge((array)@$att['photos'], !empty($att['voice']) ? array($att['voice']) : array());
        if ($mids){
            $media = $this->getMediaByIds($mids);
            foreach((array)@$att['photos'] as $mid){ if (isset($media[$mid])) { $c['photos'][] = $media[$mid]; } }
            if (!empty($att['voice']) && isset($media[$att['voice']])) { $c['voice'] = $media[$att['voice']]; }
        }
        if (!empty($att['gif']['url'])) { $c['gif'] = $att['gif']; }
        $c['is_liked']   = $is_liked;
        $c['can_edit']   = $this->canEditComment($c);
        $c['can_delete'] = $this->canDeleteComment($c, $post);
        $c['can_like']   = !$this->config['off_likes'] && $this->can('react');
        $c['answers']    = array();
        return $c;
    }

    public function canEditComment($c){
        if (!$this->inUser->id) { return false; }
        if ($this->isModerator()) { return true; }
        return $c['user_id'] == $this->inUser->id && $this->inTimeLimit($c['date_pub'], $this->config['comm_edit_time']);
    }

    public function canDeleteComment($c, $post){
        if (!$this->inUser->id) { return false; }
        if ($this->isModerator() || $c['user_id'] == $this->inUser->id) { return true; }
        return $post && ($post['user_id'] == $this->inUser->id || ($post['target_type'] == 'user' && $post['target_id'] == $this->inUser->id));
    }

    public function getComment($id){
        return $this->inDB->get_fields('cms_feedwall_comments', "id = '".(int)$id."'", '*');
    }

    public function getCommentPrepared($id){
        $c = $this->getComment($id);
        if (!$c) { return false; }
        $mu = $this->config['mentions'] ? $this->getUsersByLogins(fw_extract_mentions($c['text'])) : array();
        $liked = $this->inUser->id && $this->inDB->rows_count('cms_feedwall_reactions', "comment_id = '{$c['id']}' AND user_id = '{$this->inUser->id}'", 1);
        return $this->prepareComment($c, $this->getPost($c['post_id'], true), $mu, (bool)$liked);
    }

    private function buildCommentAttach($data){
        $att = array();
        if (!empty($data['photos']) && is_array($data['photos'])){
            $ids = array_slice(array_values(array_filter(array_map('intval', $data['photos']))), 0, 4);
            if ($ids){
                $own = $this->inUser->is_admin ? '' : " AND user_id = '{$this->inUser->id}'";
                $ok = array();
                $res = $this->inDB->query("SELECT id FROM cms_feedwall_media WHERE id IN (".implode(',', $ids).") AND type = 'photo'{$own}");
                while($m = $this->inDB->fetch_assoc($res)){ $ok[] = (int)$m['id']; }
                $ids = array_values(array_intersect($ids, $ok));
                if ($ids) { $att['photos'] = $ids; }
            }
        }
        if (!empty($data['voice'])){
            $vid = (int)$data['voice'];
            if ($this->inDB->rows_count('cms_feedwall_media', "id = '{$vid}' AND type = 'voice' AND user_id = '{$this->inUser->id}'", 1)) { $att['voice'] = $vid; }
        }
        if (!empty($data['gif']) && preg_match('#^(https?://|/(?!/))[^\s"\'<>]+$#i', (string)$data['gif'])){
            $att['gif'] = array('url' => mb_substr($data['gif'], 0, 500));
        }
        return $att;
    }

    public function addComment($post, $data){

        $_LANG = $this->lang();
        $me = (int)$this->inUser->id;
        $parent_id = 0; $answer_user_id = 0; $parent = false;

        if (!empty($data['parent_id'])){
            $parent = $this->getComment($data['parent_id']);
            if ($parent && $parent['post_id'] == $post['id']){
                $parent_id = $parent['parent_id'] ? (int)$parent['parent_id'] : (int)$parent['id'];
                $answer_user_id = (int)$parent['user_id'];
            } else { $parent = false; }
        }

        $att = $this->buildCommentAttach($data);
        $id = $this->inDB->insert('cms_feedwall_comments', array(
            'post_id' => (int)$post['id'], 'user_id' => $me, 'parent_id' => $parent_id, 'answer_user_id' => $answer_user_id,
            'text' => $this->e($data['text']), 'attach' => $this->e($att ? json_encode($att) : ''),
            'likes_count' => 0, 'answers_count' => 0, 'date_pub' => $this->now(), 'is_pub' => 1
        ));
        if (!$id) { return false; }

        $this->markMediaUsed(array('photos'=>(array)@$att['photos'], 'audios'=>!empty($att['voice']) ? array($att['voice']) : array()));
        if ($parent_id) { $this->inDB->query("UPDATE cms_feedwall_comments SET answers_count = answers_count + 1 WHERE id = '{$parent_id}'"); }
        $this->recountComments($post['id']);
        $this->addRating($me, $this->config['rating_comment']);

        $who = $this->userLink($this->inUser->login, $this->inUser->nickname);
        $url = '/feedwall/post/'.$post['id'];
        $notified = array();
        if ($answer_user_id && $answer_user_id != $me){
            $this->notify($answer_user_id, 'reply', sprintf($_LANG['FW_NOTIFY_REPLY'], $who, $url));
            $notified[] = $answer_user_id;
        }
        if ($post['user_id'] && !in_array($post['user_id'], $notified)){
            $this->notify($post['user_id'], 'comment', sprintf($_LANG['FW_NOTIFY_COMMENT'], $who, $url));
        }
        if ($this->config['mentions']) { $this->notifyMentions($data['text'], sprintf($_LANG['FW_NOTIFY_MENTION_COMMENT'], $who, $url)); }

        return $id;

    }

    public function updateComment($id, $text){
        $this->inDB->query("UPDATE cms_feedwall_comments SET text = '".$this->e($text)."', date_edit = '".$this->now()."' WHERE id = '".(int)$id."' LIMIT 1");
    }

    public function deleteComment($id){
        $c = $this->getComment($id);
        if (!$c) { return false; }
        $ids = array((int)$c['id']);
        if (!$c['parent_id']){
            $res = $this->inDB->query("SELECT id FROM cms_feedwall_comments WHERE parent_id = '{$c['id']}'");
            while($a = $this->inDB->fetch_assoc($res)){ $ids[] = (int)$a['id']; }
        }
        $in = implode(',', $ids);
        $media = array();
        $res = $this->inDB->query("SELECT attach FROM cms_feedwall_comments WHERE id IN ({$in}) AND attach <> ''");
        while($r = $this->inDB->fetch_assoc($res)){
            $a = json_decode($r['attach'], true);
            $media = array_merge($media, (array)@$a['photos'], !empty($a['voice']) ? array($a['voice']) : array());
        }
        $this->inDB->query("DELETE FROM cms_feedwall_comments WHERE id IN ({$in})");
        $this->inDB->query("DELETE FROM cms_feedwall_reactions WHERE comment_id IN ({$in})");
        $this->inDB->query("DELETE FROM cms_feedwall_complaints WHERE comment_id IN ({$in})");
        if ($media) { $this->deleteMedia($media); }
        if ($c['parent_id']){
            $this->inDB->query("UPDATE cms_feedwall_comments SET answers_count = GREATEST(answers_count - 1, 0) WHERE id = '{$c['parent_id']}'");
        }
        $this->recountComments($c['post_id']);
        $this->addRating($c['user_id'], -(int)$this->config['rating_comment']);
        return $c;
    }

    public function recountComments($post_id){
        $n = (int)$this->inDB->rows_count('cms_feedwall_comments', "post_id = '".(int)$post_id."' AND is_pub = 1");
        $this->inDB->query("UPDATE cms_feedwall_posts SET comments_count = '{$n}' WHERE id = '".(int)$post_id."'");
        return $n;
    }

    // ====================================================================== //
    //                                 ОПРОСЫ                                 //
    // ====================================================================== //

    public function addPoll($poll, $user_id){
        $question = fw_clean_text(@$poll['question'], 255);
        $answers = array(); $is_photo = 0;
        foreach(array_slice((array)@$poll['answers'], 0, 10) as $a){
            $title = fw_clean_text(is_array($a) ? @$a['title'] : $a, 150);
            $image = is_array($a) ? (int)@$a['image'] : 0;
            if ($image && !$this->inDB->rows_count('cms_feedwall_media', "id = '{$image}' AND type = 'photo' AND user_id = '".(int)$user_id."'", 1)) { $image = 0; }
            if ($title === '' && !$image) { continue; }
            if ($image) { $is_photo = 1; $this->inDB->query("UPDATE cms_feedwall_media SET is_used = 1 WHERE id = '{$image}'"); }
            $answers[] = array('id' => count($answers) + 1, 'title' => $title, 'image' => $image, 'votes' => 0);
        }
        if ($question === '' || count($answers) < 2) { return 0; }
        return (int)$this->inDB->insert('cms_feedwall_polls', array(
            'user_id' => (int)$user_id, 'question' => $this->e($question), 'answers' => $this->e(json_encode($answers)),
            'is_multi' => empty($poll['is_multi']) ? 0 : 1, 'is_photo' => $is_photo, 'votes_count' => 0, 'date_pub' => $this->now()
        ));
    }

    public function getPoll($id){
        $poll = $this->inDB->get_fields('cms_feedwall_polls', "id = '".(int)$id."'", '*');
        if (!$poll) { return false; }
        $answers = json_decode($poll['answers'], true);
        if (!is_array($answers)) { $answers = array(); }
        $my = array();
        if ($this->inUser->id){
            $res = $this->inDB->query("SELECT answer FROM cms_feedwall_poll_votes WHERE poll_id = '{$poll['id']}' AND user_id = '{$this->inUser->id}'");
            while($v = $this->inDB->fetch_assoc($res)){ $my[] = (int)$v['answer']; }
        }
        $imgs = array();
        foreach($answers as $a){ if (!empty($a['image'])) { $imgs[] = (int)$a['image']; } }
        $media = $imgs ? $this->getMediaByIds($imgs) : array();
        $total = 0; foreach($answers as $a){ $total += (int)$a['votes']; }
        foreach($answers as $k => $a){
            $answers[$k]['percent'] = $total ? round(100 * $a['votes'] / $total) : 0;
            $answers[$k]['is_my']   = in_array((int)$a['id'], $my);
            $answers[$k]['photo']   = (!empty($a['image']) && isset($media[$a['image']])) ? $media[$a['image']] : false;
        }
        $poll['answers']      = $answers;
        $poll['my_votes']     = $my;
        $poll['votes_total']  = $total;
        $vc = $this->inDB->fetch_assoc($this->inDB->query("SELECT COUNT(DISTINCT user_id) c FROM cms_feedwall_poll_votes WHERE poll_id = '{$poll['id']}'"));
        $poll['voters_count'] = $vc ? (int)$vc['c'] : 0;
        $poll['can_vote']     = $this->inUser->id && !$my;
        $poll['show_results'] = !$poll['can_vote'];
        return $poll;
    }

    public function vote($poll_id, $answer_ids){
        $poll = $this->inDB->get_fields('cms_feedwall_polls', "id = '".(int)$poll_id."'", '*');
        if (!$poll || !$this->inUser->id) { return false; }
        if ($this->inDB->rows_count('cms_feedwall_poll_votes', "poll_id = '{$poll['id']}' AND user_id = '{$this->inUser->id}'", 1)) { return false; }
        $answers = json_decode($poll['answers'], true);
        $valid = array(); foreach($answers as $a){ $valid[] = (int)$a['id']; }
        $answer_ids = array_values(array_intersect(array_unique(array_map('intval', (array)$answer_ids)), $valid));
        if (!$answer_ids) { return false; }
        if (!$poll['is_multi']) { $answer_ids = array($answer_ids[0]); }
        foreach($answer_ids as $aid){
            $this->inDB->query("INSERT IGNORE INTO cms_feedwall_poll_votes (poll_id, user_id, answer, date_pub) VALUES ('{$poll['id']}', '{$this->inUser->id}', '{$aid}', '".$this->now()."')");
        }
        foreach($answers as $k => $a){
            $answers[$k]['votes'] = (int)$this->inDB->rows_count('cms_feedwall_poll_votes', "poll_id = '{$poll['id']}' AND answer = '".(int)$a['id']."'");
        }
        $this->inDB->query("UPDATE cms_feedwall_polls SET answers = '".$this->e(json_encode($answers))."', votes_count = votes_count + 1 WHERE id = '{$poll['id']}'");
        $this->addRating($this->inUser->id, $this->config['rating_vote']);
        return true;
    }

    public function getVoters($poll_id, $answer_id = 0){
        $w = "poll_id = '".(int)$poll_id."'".($answer_id ? " AND answer = '".(int)$answer_id."'" : '');
        $res = $this->inDB->query("SELECT DISTINCT user_id FROM cms_feedwall_poll_votes WHERE {$w} ORDER BY user_id LIMIT 200");
        $ids = array(); while($r = $this->inDB->fetch_assoc($res)){ $ids[] = $r['user_id']; }
        return array_values($this->loadUsers($ids));
    }

    // ====================================================================== //
    //                                 ФАЙЛЫ                                  //
    // ====================================================================== //

    private function uploadDir($type){
        $rel = '/upload/feedwall/'.$type.'/'.date('Y-m');
        if (!is_dir(PATH.$rel)) { @mkdir(PATH.$rel, 0755, true); }
        return $rel;
    }

    /**
     * Загрузка файла. $type: photo|audio|voice. Возвращает массив медиа или строку ошибки
     */
    public function uploadMedia($type, $file, $duration = 0){

        $_LANG = $this->lang();

        if (!is_array($file) || !isset($file['tmp_name']) || is_array($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])){
            return $_LANG['FW_ERR_UPLOAD'];
        }

        $tmp  = $file['tmp_name'];
        $size = (int)filesize($tmp);
        $ext  = mb_strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $name = md5(uniqid('', true).$this->inUser->id.mt_rand());
        $row  = array('user_id' => (int)$this->inUser->id, 'type' => $type, 'file' => '', 'thumb' => '', 'title' => '', 'artist' => '',
                      'duration' => 0, 'size' => $size, 'width' => 0, 'height' => 0, 'plays_count' => 0, 'is_used' => 0, 'date_pub' => $this->now());

        if ($type == 'photo'){
            if ($size > 20 * 1048576) { return $_LANG['FW_ERR_SIZE']; }
            $info = @getimagesize($tmp);
            if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP))) { return $_LANG['FW_ERR_IMAGE']; }
            $dir = $this->uploadDir('photo');
            $max = max(320, (int)$this->config['photo_size']);
            $wh = fw_image_resize($tmp, PATH.$dir.'/'.$name.'.jpg', $max, $max);
            if (!$wh) { return $_LANG['FW_ERR_IMAGE']; }
            fw_image_resize($tmp, PATH.$dir.'/'.$name.'_t.jpg', 400, 400, true);
            $row['file']  = $dir.'/'.$name.'.jpg';
            $row['thumb'] = $dir.'/'.$name.'_t.jpg';
            $row['width'] = $wh[0]; $row['height'] = $wh[1];
            $row['size']  = (int)filesize(PATH.$row['file']);
            $row['title'] = $this->e(fw_clean_text(pathinfo((string)$file['name'], PATHINFO_FILENAME), 200));
        }

        elseif ($type == 'audio' || $type == 'voice'){
            $allowed = $type == 'audio' ? array('mp3', 'ogg', 'oga', 'm4a') : array('webm', 'ogg', 'oga', 'mp3', 'm4a', 'wav');
            if (!in_array($ext, $allowed, true)) { return $_LANG['FW_ERR_TYPE']; }
            $limit = $type == 'audio' ? (int)$this->config['audio_max_mb'] * 1048576 : 5 * 1048576;
            if ($size <= 0 || $size > $limit) { return $_LANG['FW_ERR_SIZE']; }
            $fh = fopen($tmp, 'rb'); $head = (string)fread($fh, 16); fclose($fh);
            $magic = substr($head, 0, 3) == 'ID3' || (ord($head[0]) == 0xFF && (ord($head[1]) & 0xE0) == 0xE0) // mp3
                  || substr($head, 0, 4) == 'OggS' || substr($head, 4, 4) == 'ftyp'
                  || substr($head, 0, 4) == "\x1A\x45\xDF\xA3" || (substr($head, 0, 4) == 'RIFF' && substr($head, 8, 4) == 'WAVE');
            if (!$magic) { return $_LANG['FW_ERR_TYPE']; }
            if ($type == 'voice'){
                $duration = (int)$duration;
                if ($duration > (int)$this->config['voice_max_sec'] + 1) { return sprintf($_LANG['FW_ERR_VOICE_LONG'], (int)$this->config['voice_max_sec']); }
                $row['duration'] = max(0, $duration);
            }
            $dir = $this->uploadDir($type);
            $row['file'] = $dir.'/'.$name.'.'.$ext;
            if (!move_uploaded_file($tmp, PATH.$row['file'])) { return $_LANG['FW_ERR_UPLOAD']; }
            @chmod(PATH.$row['file'], 0644);
            if ($type == 'audio'){
                $id3 = $ext == 'mp3' ? fw_id3(PATH.$row['file']) : array('title'=>'', 'artist'=>'');
                $title = $id3['title'] !== '' ? $id3['title'] : fw_clean_text(pathinfo((string)$file['name'], PATHINFO_FILENAME), 200);
                $row['title']  = $this->e($title);
                $row['artist'] = $this->e($id3['artist']);
            }
        }

        else { return $_LANG['FW_ERR_TYPE']; }

        $row['id'] = $this->inDB->insert('cms_feedwall_media', $row);
        if (!$row['id']) { return $_LANG['FW_ERR_UPLOAD']; }

        $media = $this->getMediaByIds(array($row['id']));
        return $media[$row['id']];

    }

    public function getMediaByIds($ids){
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
        $out = array();
        if (!$ids) { return $out; }
        $res = $this->inDB->query("SELECT * FROM cms_feedwall_media WHERE id IN (".implode(',', $ids).")");
        while($m = $this->inDB->fetch_assoc($res)){
            if (!$m['thumb']) { $m['thumb'] = $m['file']; }
            $out[$m['id']] = $m;
        }
        return $out;
    }

    public function getUserMedia($user_id, $type, $page = 1, $perpage = 30){
        $w = "user_id = '".(int)$user_id."' AND type = '".($type == 'audio' ? 'audio' : 'photo')."' AND is_used = 1";
        $total = (int)$this->inDB->rows_count('cms_feedwall_media', $w);
        $list = array();
        $res = $this->inDB->query("SELECT * FROM cms_feedwall_media WHERE {$w} ORDER BY id DESC LIMIT ".((max(1, (int)$page) - 1) * $perpage).", ".(int)$perpage);
        while($m = $this->inDB->fetch_assoc($res)){ $list[] = $m; }
        return array($list, $total);
    }

    private function markMediaUsed($attach){
        $ids = array_merge((array)@$attach['photos'], (array)@$attach['audios']);
        $ids = array_filter(array_map('intval', $ids));
        if ($ids) { $this->inDB->query("UPDATE cms_feedwall_media SET is_used = 1 WHERE id IN (".implode(',', $ids).")"); }
    }

    /** Удаляет медиа, если на них больше никто не ссылается (или $force) */
    public function deleteMedia($ids, $force = false){
        $media = $this->getMediaByIds($ids);
        foreach($media as $m){
            if (!$force && $this->isMediaReferenced($m['id'])) { continue; }
            foreach(array($m['file'], $m['thumb']) as $f){
                if ($f && strpos($f, '/upload/feedwall/') === 0 && strpos($f, '..') === false && is_file(PATH.$f)) { @unlink(PATH.$f); }
            }
            $this->inDB->query("DELETE FROM cms_feedwall_media WHERE id = '{$m['id']}'");
        }
    }

    private function isMediaReferenced($id){
        $id = (int)$id;
        $re = $this->e('[\\[,:]'.$id.'[],}]');
        if ($this->inDB->rows_count('cms_feedwall_posts', "attach REGEXP '{$re}'", 1)) { return true; }
        if ($this->inDB->rows_count('cms_feedwall_comments', "attach REGEXP '{$re}'", 1)) { return true; }
        if ($this->inDB->rows_count('cms_feedwall_polls', "answers REGEXP '".$this->e('"image":'.$id.'[,}]')."'", 1)) { return true; }
        return false;
    }

    // ====================================================================== //
    //                                   GIF                                  //
    // ====================================================================== //

    public function getGifCats(){
        return $this->inDB->get_table('cms_feedwall_gif_cats', '1=1 ORDER BY ordering, id');
    }

    public function searchGifs($q, $cat_id = 0, $limit = 24){
        $q = trim((string)$q);
        $list = array();
        if ($this->config['gif_provider'] == 'tenor' && $this->config['gif_key']){
            $url = 'https://tenor.googleapis.com/v2/'.($q !== '' ? 'search?q='.rawurlencode($q).'&' : 'featured?')
                 .'key='.rawurlencode($this->config['gif_key']).'&limit='.(int)$limit.'&media_filter=tinygif,gif&contentfilter=medium';
            $res = fw_http_get($url, 2097152);
            $data = $res ? json_decode($res['body'], true) : false;
            if (!empty($data['results'])){
                foreach($data['results'] as $r){
                    if (empty($r['media_formats']['gif']['url'])) { continue; }
                    $list[] = array('url' => $r['media_formats']['gif']['url'],
                                    'preview' => !empty($r['media_formats']['tinygif']['url']) ? $r['media_formats']['tinygif']['url'] : $r['media_formats']['gif']['url'],
                                    'title' => (string)@$r['content_description']);
                }
                return $list;
            }
        }
        $w = '1=1';
        if ($cat_id) { $w .= " AND cat_id = '".(int)$cat_id."'"; }
        if ($q !== '') { $w .= " AND title LIKE '%".$this->e(addcslashes($q, '%_'))."%'"; }
        $res = $this->inDB->query("SELECT * FROM cms_feedwall_gifs WHERE {$w} ORDER BY ordering, id DESC LIMIT ".(int)$limit);
        while($g = $this->inDB->fetch_assoc($res)){ $list[] = array('url'=>$g['url'], 'preview'=>$g['url'], 'title'=>$g['title']); }
        return $list;
    }

    // ====================================================================== //
    //                         ПРОСМОТРЫ И РЕКЛАМА                            //
    // ====================================================================== //

    public function registerViews($ids){
        if (!$this->config['views']) { return 0; }
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)$ids)))), 0, 50);
        if (!$ids || $this->inUser->is_admin) { return 0; }
        $viewer = $this->viewerKey(); $me = (int)$this->inUser->id; $n = 0;
        foreach($ids as $id){
            if ($me && $this->inDB->rows_count('cms_feedwall_posts', "id = '{$id}' AND user_id = '{$me}'", 1)) { continue; }
            $this->inDB->query("INSERT IGNORE INTO cms_feedwall_views (post_id, viewer) SELECT id, '{$viewer}' FROM cms_feedwall_posts WHERE id = '{$id}'");
            if ($this->inDB->affected_rows() > 0){
                $this->inDB->query("UPDATE cms_feedwall_posts SET views_count = views_count + 1 WHERE id = '{$id}'");
                $n++;
            }
        }
        return $n;
    }

    public function getActiveAds(){
        $today = date('Y-m-d');
        $list = array();
        $res = $this->inDB->query("SELECT * FROM cms_feedwall_ads WHERE is_pub = 1
                                   AND (date_start IS NULL OR date_start <= '{$today}') AND (date_end IS NULL OR date_end >= '{$today}')
                                   AND (max_views = 0 OR views_count < max_views) AND (max_clicks = 0 OR clicks_count < max_clicks)
                                   ORDER BY RAND() LIMIT 10");
        while($a = $this->inDB->fetch_assoc($res)){ $a['go_url'] = '/feedwall/go/'.$a['id']; $list[] = $a; }
        return $list;
    }

    public function getAd($id){
        return $this->inDB->get_fields('cms_feedwall_ads', "id = '".(int)$id."'", '*');
    }

    public function logAd($ad_id, $kind){
        $ad_id = (int)$ad_id; $kind = $kind == 'click' ? 'click' : 'view';
        $viewer = $this->viewerKey();
        if (!$this->config['ads_repeat'] && $this->inDB->rows_count('cms_feedwall_ads_log', "ad_id = '{$ad_id}' AND kind = '{$kind}' AND viewer = '{$viewer}'", 1)) { return false; }
        $this->inDB->query("INSERT INTO cms_feedwall_ads_log (ad_id, kind, viewer, date_pub) VALUES ('{$ad_id}', '{$kind}', '{$viewer}', '".$this->now()."')");
        $this->inDB->query("UPDATE cms_feedwall_ads SET {$kind}s_count = {$kind}s_count + 1 WHERE id = '{$ad_id}'");
        return true;
    }

    public function getAdStats($ad_id, $days = 30){
        $from = date('Y-m-d', time() - 86400 * (int)$days);
        $stats = array();
        $res = $this->inDB->query("SELECT DATE(date_pub) d, kind, COUNT(*) c FROM cms_feedwall_ads_log WHERE ad_id = '".(int)$ad_id."' AND date_pub >= '{$from}'
                                   GROUP BY DATE(date_pub), kind ORDER BY d DESC");
        while($r = $this->inDB->fetch_assoc($res)){
            if (!isset($stats[$r['d']])) { $stats[$r['d']] = array('date'=>$r['d'], 'view'=>0, 'click'=>0); }
            $stats[$r['d']][$r['kind']] = (int)$r['c'];
        }
        return array_values($stats);
    }

    public function getUserAds($user_id){
        return $this->inDB->get_table('cms_feedwall_ads', "user_id = '".(int)$user_id."' ORDER BY id DESC");
    }

    // ====================================================================== //
    //                                 ЖАЛОБЫ                                 //
    // ====================================================================== //

    public function addComplaint($post_id, $comment_id, $reason){
        $_LANG = $this->lang();
        $post_id = (int)$post_id; $comment_id = (int)$comment_id;
        $me = (int)$this->inUser->id; $ip = $this->ip();
        $who = $me ? "user_id = '{$me}'" : "user_id = 0 AND ip = '{$ip}'";
        if ($this->inDB->rows_count('cms_feedwall_complaints', "post_id = '{$post_id}' AND comment_id = '{$comment_id}' AND {$who} AND is_closed = 0", 1)) { return false; }
        $reason = fw_clean_text($reason, 500);
        $this->inDB->query("INSERT INTO cms_feedwall_complaints (post_id, comment_id, user_id, reason, ip, is_closed, date_pub)
                            VALUES ('{$post_id}', '{$comment_id}', '{$me}', '".$this->e($reason)."', '{$ip}', 0, '".$this->now()."')");
        $url  = HOST.'/feedwall/post/'.$post_id;
        $text = sprintf($_LANG['FW_COMPLAINT_MAIL'], $url, $reason);
        if ($this->config['complaint_email'] && filter_var($this->config['complaint_email'], FILTER_VALIDATE_EMAIL)){
            cmsCore::mailText($this->config['complaint_email'], $_LANG['FW_COMPLAINT_SUBJ'], $text);
        }
        $res = $this->inDB->query("SELECT u.id FROM cms_users u INNER JOIN cms_user_groups g ON g.id = u.group_id WHERE g.is_admin = 1 AND u.is_deleted = 0 LIMIT 5");
        while($a = $this->inDB->fetch_assoc($res)){
            cmsUser::sendMessage(USER_UPDATER, $a['id'], nl2br(fw_esc($text)));
        }
        return true;
    }

    public function getComplaints($only_open = true){
        $list = array();
        $res = $this->inDB->query("SELECT c.*, u.login, u.nickname FROM cms_feedwall_complaints c LEFT JOIN cms_users u ON u.id = c.user_id
                                   ".($only_open ? 'WHERE c.is_closed = 0' : '')." ORDER BY c.id DESC LIMIT 200");
        while($c = $this->inDB->fetch_assoc($res)){ $list[] = $c; }
        return $list;
    }

    // ====================================================================== //
    //                                  RSS                                   //
    // ====================================================================== //

    public function fetchRss($url, $limit = 5, $with_images = true){
        $res = fw_http_get($url, 2097152, 10);
        if (!$res) { return false; }
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($res['body'], 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        if (!$xml) { return false; }
        $items = array();
        $nodes = isset($xml->channel->item) ? $xml->channel->item : (isset($xml->entry) ? $xml->entry : (isset($xml->item) ? $xml->item : array()));
        foreach($nodes as $n){
            $link = (string)$n->link;
            if (!$link && isset($n->link)){ foreach($n->link as $l){ if ((string)$l['href']) { $link = (string)$l['href']; if ((string)$l['rel'] == 'alternate') { break; } } } }
            $desc = (string)$n->description;
            if ($desc === '') { $desc = (string)$n->summary; }
            if ($desc === '') { $desc = (string)$n->content; }
            $image = '';
            if ($with_images){
                if (isset($n->enclosure) && preg_match('#^image/#', (string)$n->enclosure['type'])) { $image = (string)$n->enclosure['url']; }
                $media = $n->children('http://search.yahoo.com/mrss/');
                if (!$image && isset($media->content)) { $image = (string)$media->content->attributes()->url; }
                if (!$image && isset($media->thumbnail)) { $image = (string)$media->thumbnail->attributes()->url; }
                if (!$image && preg_match('/<img[^>]+src=["\']([^"\']+)/i', $desc, $m)) { $image = html_entity_decode($m[1]); }
            }
            if (!preg_match('#^https?://#i', $link)) { continue; }
            $items[] = array(
                'title' => fw_clean_text(html_entity_decode((string)$n->title, ENT_QUOTES, 'UTF-8'), 255),
                'desc'  => fw_clean_text(html_entity_decode(strip_tags($desc), ENT_QUOTES, 'UTF-8'), 500),
                'url'   => $link,
                'image' => preg_match('#^https?://[^\s"\'<>]+$#i', $image) ? $image : ''
            );
            if (count($items) >= $limit) { break; }
        }
        return $items;
    }

    public function importRssItems($source){
        $items = $this->fetchRss($source['url'], max(1, (int)$source['items_limit']), $source['with_images']);
        if ($items === false) { return false; }
        $n = 0;
        foreach(array_reverse($items) as $it){
            if ($this->inDB->rows_count('cms_feedwall_posts', "source_url = '".$this->e($it['url'])."'", 1)) { continue; }
            $host = parse_url($it['url'], PHP_URL_HOST);
            $text = fw_mb4_encode($it['title']);
            $id = $this->addPost(array(
                'user_id' => (int)$source['user_id'], 'type' => 'rss', 'target_type' => 'feed', 'target_id' => 0,
                'text' => $text, 'source_url' => $it['url'],
                'link' => array('url'=>$it['url'], 'title'=>$it['title'], 'desc'=>$it['desc'], 'image'=>$it['image'], 'site'=>(string)$host)
            ));
            if ($id) { $n++; }
        }
        $this->inDB->query("UPDATE cms_feedwall_rss SET date_last = '".$this->now()."' WHERE id = '".(int)$source['id']."'");
        return $n;
    }

    public function getRssSources(){
        return $this->inDB->get_table('cms_feedwall_rss', '1=1 ORDER BY id');
    }

    // ====================================================================== //
    //                         ОБСЛУЖИВАНИЕ (CRON)                            //
    // ====================================================================== //

    public function cronJob(){
        $this->setMeta('cron_time', time());
        $this->syncActivity(true);
        foreach($this->getRssSources() as $src){
            if ($src['is_auto'] && (!$src['date_last'] || strtotime($src['date_last']) < time() - 3600)){
                $this->importRssItems($src);
            }
        }
        $this->cleanup();
        return true;
    }

    public function cleanup(){
        $n = 0;
        // неиспользованные загрузки старше суток
        $res = $this->inDB->query("SELECT id FROM cms_feedwall_media WHERE is_used = 0 AND date_pub < '".date('Y-m-d H:i:s', time() - 86400)."' LIMIT 500");
        $ids = array(); while($m = $this->inDB->fetch_assoc($res)){ $ids[] = $m['id']; }
        if ($ids) { $this->deleteMedia($ids); $n += count($ids); }
        // осиротевшие записи
        $this->inDB->query("DELETE c FROM cms_feedwall_comments c LEFT JOIN cms_feedwall_posts p ON p.id = c.post_id WHERE p.id IS NULL");
        $this->inDB->query("DELETE r FROM cms_feedwall_reactions r LEFT JOIN cms_feedwall_posts p ON p.id = r.post_id WHERE p.id IS NULL");
        $this->inDB->query("DELETE v FROM cms_feedwall_views v LEFT JOIN cms_feedwall_posts p ON p.id = v.post_id WHERE p.id IS NULL");
        $this->inDB->query("DELETE t FROM cms_feedwall_post_tags t LEFT JOIN cms_feedwall_posts p ON p.id = t.post_id WHERE p.id IS NULL");
        $this->inDB->query("DELETE h FROM cms_feedwall_hidden h LEFT JOIN cms_users u ON u.id = h.user_id WHERE u.id IS NULL");
        $this->inDB->query("DELETE FROM cms_feedwall_ads_log WHERE date_pub < '".date('Y-m-d H:i:s', time() - 86400 * 180)."'");
        $this->recountTags();
        return $n;
    }

    public function recountAll(){
        $this->inDB->query("UPDATE cms_feedwall_posts p SET
                comments_count = (SELECT COUNT(*) FROM cms_feedwall_comments c WHERE c.post_id = p.id AND c.is_pub = 1),
                shares_count   = (SELECT COUNT(*) FROM (SELECT share_id FROM cms_feedwall_posts WHERE share_id > 0) s WHERE s.share_id = p.id),
                views_count    = (SELECT COUNT(*) FROM cms_feedwall_views v WHERE v.post_id = p.id)");
        $res = $this->inDB->query("SELECT DISTINCT post_id FROM cms_feedwall_reactions WHERE comment_id = 0");
        $this->inDB->query("UPDATE cms_feedwall_posts SET likes_count = 0, reactions = ''");
        while($r = $this->inDB->fetch_assoc($res)){ $this->recountReactions($r['post_id']); }
        $this->inDB->query("UPDATE cms_feedwall_comments c SET
                likes_count   = (SELECT COUNT(*) FROM cms_feedwall_reactions r WHERE r.comment_id = c.id),
                answers_count = (SELECT COUNT(*) FROM (SELECT parent_id FROM cms_feedwall_comments WHERE parent_id > 0) a WHERE a.parent_id = c.id)");
        $this->recountTags();
        return true;
    }

    /** Таблицы компонента и их наличие (диагностика) */
    public function checkTables(){
        $out = array();
        foreach(array_keys(feedwall_schema_tables()) as $t){
            $res = $this->inDB->query("SHOW TABLES LIKE 'cms_{$t}'");
            $ok = $this->inDB->num_rows($res) > 0;
            $out[] = array('table' => 'cms_'.$t, 'ok' => $ok, 'rows' => $ok ? (int)$this->inDB->rows_count('cms_'.$t, '1=1') : 0);
        }
        return $out;
    }

    /** Удаление всех данных пользователя (DELETE_USER) */
    public function deleteUserData($user_id){
        $user_id = (int)$user_id;
        if (!$user_id) { return; }
        $res = $this->inDB->query("SELECT id FROM cms_feedwall_posts WHERE user_id = '{$user_id}' OR (target_type = 'user' AND target_id = '{$user_id}')");
        $ids = array(); while($p = $this->inDB->fetch_assoc($res)){ $ids[] = $p['id']; }
        foreach($ids as $id){ $this->deletePost($id); }
        $res = $this->inDB->query("SELECT id FROM cms_feedwall_comments WHERE user_id = '{$user_id}' ORDER BY parent_id DESC");
        while($c = $this->inDB->fetch_assoc($res)){ $this->deleteComment($c['id']); }
        $res = $this->inDB->query("SELECT DISTINCT post_id, comment_id FROM cms_feedwall_reactions WHERE user_id = '{$user_id}'");
        $re = array(); while($r = $this->inDB->fetch_assoc($res)){ $re[] = $r; }
        $this->inDB->query("DELETE FROM cms_feedwall_reactions WHERE user_id = '{$user_id}'");
        foreach($re as $r){ $this->recountReactions($r['post_id'], $r['comment_id']); }
        $this->inDB->query("DELETE FROM cms_feedwall_hidden WHERE user_id = '{$user_id}' OR author_id = '{$user_id}'");
        $this->inDB->query("DELETE FROM cms_feedwall_usercfg WHERE user_id = '{$user_id}'");
        $this->inDB->query("DELETE FROM cms_feedwall_poll_votes WHERE user_id = '{$user_id}'");
        $res = $this->inDB->query("SELECT id FROM cms_feedwall_media WHERE user_id = '{$user_id}'");
        $mids = array(); while($m = $this->inDB->fetch_assoc($res)){ $mids[] = $m['id']; }
        if ($mids) { $this->deleteMedia($mids, true); }
    }

}
