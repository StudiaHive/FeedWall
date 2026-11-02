<?php
/******************************************************************************/
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                        LICENSED BY GNU/GPL v2                              //
/******************************************************************************/

if(!defined('VALID_CMS')) { die('ACCESS DENIED'); }

cmsCore::includeFile('components/feedwall/includes/defaults.php');

/**
 * Описание таблиц. Все таблицы InnoDB / utf8mb4, все NOT NULL поля имеют
 * значения по умолчанию (безопасно для STRICT_TRANS_TABLES MySQL 8),
 * даты пишутся из PHP (без нулевых дат).
 */
function feedwall_schema_tables(){

    $t = array();

    $t['feedwall_posts'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL DEFAULT '0',
      `target_type` varchar(10) NOT NULL DEFAULT 'feed',
      `target_id` int(11) NOT NULL DEFAULT '0',
      `type` varchar(10) NOT NULL DEFAULT 'post',
      `action_log_id` int(11) NOT NULL DEFAULT '0',
      `share_id` int(11) NOT NULL DEFAULT '0',
      `text` mediumtext NULL,
      `attach` mediumtext NULL,
      `poll_id` int(11) NOT NULL DEFAULT '0',
      `bg_id` int(11) NOT NULL DEFAULT '0',
      `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
      `is_pinned_feed` tinyint(1) NOT NULL DEFAULT '0',
      `is_anonymous` tinyint(1) NOT NULL DEFAULT '0',
      `is_private` tinyint(1) NOT NULL DEFAULT '0',
      `is_pub` tinyint(1) NOT NULL DEFAULT '1',
      `likes_count` int(11) NOT NULL DEFAULT '0',
      `reactions` varchar(255) NOT NULL DEFAULT '',
      `comments_count` int(11) NOT NULL DEFAULT '0',
      `shares_count` int(11) NOT NULL DEFAULT '0',
      `views_count` int(11) NOT NULL DEFAULT '0',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `date_edit` datetime NULL DEFAULT NULL,
      `guest_name` varchar(100) NOT NULL DEFAULT '',
      `ip` varchar(45) NOT NULL DEFAULT '',
      `source_url` varchar(500) NOT NULL DEFAULT '',
      PRIMARY KEY (`id`),
      KEY `target` (`target_type`,`target_id`,`is_pub`,`date_pub`),
      KEY `user_id` (`user_id`,`date_pub`),
      KEY `action_log_id` (`action_log_id`),
      KEY `share_id` (`share_id`),
      KEY `date_pub` (`is_pub`,`date_pub`),
      FULLTEXT KEY `text` (`text`)";

    $t['feedwall_comments'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `post_id` int(11) NOT NULL DEFAULT '0',
      `user_id` int(11) NOT NULL DEFAULT '0',
      `parent_id` int(11) NOT NULL DEFAULT '0',
      `answer_user_id` int(11) NOT NULL DEFAULT '0',
      `text` mediumtext NULL,
      `attach` text NULL,
      `likes_count` int(11) NOT NULL DEFAULT '0',
      `answers_count` int(11) NOT NULL DEFAULT '0',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `date_edit` datetime NULL DEFAULT NULL,
      `is_pub` tinyint(1) NOT NULL DEFAULT '1',
      PRIMARY KEY (`id`),
      KEY `post_id` (`post_id`,`parent_id`,`date_pub`),
      KEY `user_id` (`user_id`)";

    $t['feedwall_reactions'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `post_id` int(11) NOT NULL DEFAULT '0',
      `comment_id` int(11) NOT NULL DEFAULT '0',
      `user_id` int(11) NOT NULL DEFAULT '0',
      `reaction` varchar(10) NOT NULL DEFAULT 'like',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq` (`post_id`,`comment_id`,`user_id`),
      KEY `user_id` (`user_id`,`date_pub`)";

    $t['feedwall_polls'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL DEFAULT '0',
      `question` varchar(255) NOT NULL DEFAULT '',
      `answers` text NULL,
      `is_multi` tinyint(1) NOT NULL DEFAULT '0',
      `is_photo` tinyint(1) NOT NULL DEFAULT '0',
      `votes_count` int(11) NOT NULL DEFAULT '0',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)";

    $t['feedwall_poll_votes'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `poll_id` int(11) NOT NULL DEFAULT '0',
      `user_id` int(11) NOT NULL DEFAULT '0',
      `answer` int(11) NOT NULL DEFAULT '0',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq` (`poll_id`,`user_id`,`answer`)";

    $t['feedwall_tags'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `tag` varchar(100) NOT NULL DEFAULT '',
      `posts_count` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      UNIQUE KEY `tag` (`tag`)";

    $t['feedwall_post_tags'] = "
      `post_id` int(11) NOT NULL DEFAULT '0',
      `tag_id` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`post_id`,`tag_id`),
      KEY `tag_id` (`tag_id`)";

    $t['feedwall_hidden'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL DEFAULT '0',
      `post_id` int(11) NOT NULL DEFAULT '0',
      `author_id` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq` (`user_id`,`post_id`,`author_id`)";

    $t['feedwall_views'] = "
      `post_id` int(11) NOT NULL DEFAULT '0',
      `viewer` varchar(64) NOT NULL DEFAULT '',
      PRIMARY KEY (`post_id`,`viewer`)";

    $t['feedwall_media'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL DEFAULT '0',
      `type` varchar(10) NOT NULL DEFAULT 'photo',
      `file` varchar(255) NOT NULL DEFAULT '',
      `thumb` varchar(255) NOT NULL DEFAULT '',
      `title` varchar(255) NOT NULL DEFAULT '',
      `artist` varchar(255) NOT NULL DEFAULT '',
      `duration` int(11) NOT NULL DEFAULT '0',
      `size` int(11) NOT NULL DEFAULT '0',
      `width` int(11) NOT NULL DEFAULT '0',
      `height` int(11) NOT NULL DEFAULT '0',
      `plays_count` int(11) NOT NULL DEFAULT '0',
      `is_used` tinyint(1) NOT NULL DEFAULT '0',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `user_type` (`user_id`,`type`)";

    $t['feedwall_ads'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL DEFAULT '0',
      `title` varchar(255) NOT NULL DEFAULT '',
      `text` text NULL,
      `image` varchar(255) NOT NULL DEFAULT '',
      `url` varchar(500) NOT NULL DEFAULT '',
      `position` int(11) NOT NULL DEFAULT '3',
      `max_views` int(11) NOT NULL DEFAULT '0',
      `max_clicks` int(11) NOT NULL DEFAULT '0',
      `views_count` int(11) NOT NULL DEFAULT '0',
      `clicks_count` int(11) NOT NULL DEFAULT '0',
      `date_start` date NULL DEFAULT NULL,
      `date_end` date NULL DEFAULT NULL,
      `is_pub` tinyint(1) NOT NULL DEFAULT '1',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)";

    $t['feedwall_ads_log'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `ad_id` int(11) NOT NULL DEFAULT '0',
      `kind` varchar(5) NOT NULL DEFAULT 'view',
      `viewer` varchar(64) NOT NULL DEFAULT '',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `ad_kind` (`ad_id`,`kind`,`viewer`)";

    $t['feedwall_gif_cats'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `title` varchar(100) NOT NULL DEFAULT '',
      `ordering` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`)";

    $t['feedwall_gifs'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `cat_id` int(11) NOT NULL DEFAULT '0',
      `title` varchar(255) NOT NULL DEFAULT '',
      `url` varchar(500) NOT NULL DEFAULT '',
      `ordering` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `cat_id` (`cat_id`)";

    $t['feedwall_backgrounds'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `title` varchar(100) NOT NULL DEFAULT '',
      `css` varchar(500) NOT NULL DEFAULT '',
      `color` varchar(20) NOT NULL DEFAULT '#ffffff',
      `ordering` int(11) NOT NULL DEFAULT '0',
      `is_pub` tinyint(1) NOT NULL DEFAULT '1',
      PRIMARY KEY (`id`)";

    $t['feedwall_complaints'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `post_id` int(11) NOT NULL DEFAULT '0',
      `comment_id` int(11) NOT NULL DEFAULT '0',
      `user_id` int(11) NOT NULL DEFAULT '0',
      `reason` varchar(500) NOT NULL DEFAULT '',
      `ip` varchar(45) NOT NULL DEFAULT '',
      `is_closed` tinyint(1) NOT NULL DEFAULT '0',
      `date_pub` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)";

    $t['feedwall_rss'] = "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `title` varchar(255) NOT NULL DEFAULT '',
      `url` varchar(500) NOT NULL DEFAULT '',
      `user_id` int(11) NOT NULL DEFAULT '1',
      `items_limit` int(11) NOT NULL DEFAULT '5',
      `with_images` tinyint(1) NOT NULL DEFAULT '1',
      `is_auto` tinyint(1) NOT NULL DEFAULT '0',
      `date_last` datetime NULL DEFAULT NULL,
      PRIMARY KEY (`id`)";

    $t['feedwall_usercfg'] = "
      `user_id` int(11) NOT NULL DEFAULT '0',
      `who_write` varchar(10) NOT NULL DEFAULT 'all',
      `who_comment` varchar(10) NOT NULL DEFAULT 'all',
      `who_react` varchar(10) NOT NULL DEFAULT 'all',
      `allow_mentions` tinyint(1) NOT NULL DEFAULT '1',
      `in_feed` tinyint(1) NOT NULL DEFAULT '1',
      `notify_wall` tinyint(1) NOT NULL DEFAULT '1',
      `notify_mention` tinyint(1) NOT NULL DEFAULT '1',
      `notify_comment` tinyint(1) NOT NULL DEFAULT '1',
      `notify_reply` tinyint(1) NOT NULL DEFAULT '1',
      `notify_react` tinyint(1) NOT NULL DEFAULT '0',
      `notify_share` tinyint(1) NOT NULL DEFAULT '1',
      PRIMARY KEY (`user_id`)";

    $t['feedwall_meta'] = "
      `name` varchar(50) NOT NULL DEFAULT '',
      `value` varchar(255) NOT NULL DEFAULT '',
      PRIMARY KEY (`name`)";

    return $t;

}

/**
 * Колонки, добавленные в последующих версиях: table => array(column => DDL)
 * (заготовка для upgrade_component_feedwall)
 */
function feedwall_schema_columns(){
    return array(
        'feedwall_posts' => array(
            'source_url' => "varchar(500) NOT NULL DEFAULT ''"
        )
    );
}

function feedwall_schema_install(){

    $inDB = cmsDatabase::getInstance();

    foreach(feedwall_schema_tables() as $table => $ddl){

        if ($inDB->isTableExists('cms_'.$table)) { continue; }

        $sql = "CREATE TABLE `cms_{$table}` ({$ddl}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $inDB->query($sql, true);

        if ($inDB->error()) { return 'feedwall: '.$table.': '.$inDB->error(); }

    }

    foreach(feedwall_schema_columns() as $table => $cols){
        foreach($cols as $col => $ddl){
            if (!$inDB->isFieldExists('cms_'.$table, $col)){
                $inDB->query("ALTER TABLE `cms_{$table}` ADD `{$col}` {$ddl}", true);
            }
        }
    }

    // демо-данные справочников (только если пусто)
    if (!$inDB->rows_count('cms_feedwall_backgrounds', '1=1', 1)){
        $bgs = array(
            array('Синий',     'background:#1e6fd9', '#ffffff'),
            array('Закат',     'background:linear-gradient(135deg,#ff7e5f,#feb47b)', '#ffffff'),
            array('Мята',      'background:linear-gradient(135deg,#43cea2,#185a9d)', '#ffffff'),
            array('Фиолетовый','background:linear-gradient(135deg,#8e2de2,#4a00e0)', '#ffffff'),
            array('Тёмный',    'background:#222831', '#f5f5f5'),
            array('Песок',     'background:#f6e7c1', '#333333')
        );
        foreach($bgs as $n => $bg){
            $inDB->query("INSERT INTO cms_feedwall_backgrounds (title, css, color, ordering, is_pub)
                          VALUES ('".$inDB->escape_string($bg[0])."', '".$inDB->escape_string($bg[1])."', '{$bg[2]}', ".($n+1).", 1)");
        }
    }

    if (!$inDB->rows_count('cms_feedwall_gif_cats', '1=1', 1)){
        $inDB->query("INSERT INTO cms_feedwall_gif_cats (title, ordering) VALUES ('Общее', 1)");
    }

    if (!$inDB->rows_count('cms_feedwall_meta', "name='activity_last_id'", 1)){
        // события, созданные до установки, тоже попадут в ленту
        $inDB->query("INSERT INTO cms_feedwall_meta (name, value) VALUES ('activity_last_id', '0')");
    }

    feedwall_register_access();

    return true;

}

function feedwall_register_access(){

    $inDB = cmsDatabase::getInstance();

    foreach(feedwall_access_types() as $type => $a){

        cmsUser::registerGroupAccessType($type, $inDB->escape_string($a[0]), $a[1]);

        foreach($a[2] as $alias){
            $group = $inDB->get_fields('cms_user_groups', "alias = '{$alias}'", 'id, access');
            if (!$group) { continue; }
            $list = array_filter(array_map('trim', explode(',', $group['access'])));
            if (in_array($type, $list)) { continue; }
            $list[] = $type;
            $inDB->query("UPDATE cms_user_groups SET access = '".$inDB->escape_string(implode(', ', $list))."' WHERE id = '{$group['id']}'");
        }

    }

}

/**
 * Ставит плагин, модули и задачу CRON (вызывается из инсталлятора в админке)
 */
function feedwall_install_extensions(){

    $inDB   = cmsDatabase::getInstance();
    $inCore = cmsCore::getInstance();

    // ---- плагины (при обновлении синхронизируем список событий) ----
    $plugins = array(
        'p_feedwall' => array('feed-wall: стена профиля и клубов',
                              'Вкладка «Стена» в профиле, стена в клубах, ассеты, замена стандартной стены, очистка при удалении пользователя',
                              array('USER_PROFILE', 'GET_SINGLE_CLUB', 'DELETE_USER', 'PRINT_PAGE_HEAD')),
        'p_feedwall_likes' => array('feed-wall: вкладка «Понравилось»',
                              'Вкладка «Понравилось» в профиле пользователя',
                              array('USER_PROFILE'))
    );

    foreach($plugins as $name => $pl){

        $plugin_id = (int)$inDB->get_field('cms_plugins', "plugin = '{$name}'", 'id');

        if (!$plugin_id){
            $plugin_id = (int)$inDB->insert('cms_plugins', array(
                'plugin'      => $name,
                'title'       => $pl[0],
                'description' => $pl[1],
                'author'      => 'StudiaHive / InstantCMS community',
                'version'     => '1.0.0',
                'plugin_type' => 'plugin',
                'published'   => 1,
                'config'      => ''
            ));
        } else {
            $inDB->query("UPDATE cms_plugins SET version = '1.0.0' WHERE id = '{$plugin_id}'");
        }

        if ($plugin_id){
            $inDB->query("DELETE FROM cms_event_hooks WHERE plugin_id = '{$plugin_id}'");
            foreach($pl[2] as $event){
                $inDB->insert('cms_event_hooks', array('event'=>$event, 'plugin_id'=>$plugin_id));
            }
        }

    }

    // ---- модули ----
    $modules = array(
        'mod_feedwall_form' => array('feed-wall: что у вас нового?', 'Что у вас нового?', array('tpl'=>'mod_feedwall_form.tpl', 'target'=>'feed')),
        'mod_feedwall_list' => array('feed-wall: последние записи', 'Лента', array('tpl'=>'mod_feedwall_list.tpl', 'limit'=>5, 'source'=>'all', 'show_text'=>1, 'show_link'=>1)),
        'mod_feedwall_tags' => array('feed-wall: хэштеги', 'Популярные хэштеги', array('tpl'=>'mod_feedwall_tags.tpl', 'limit'=>30))
    );

    foreach($modules as $content => $m){

        if ($inDB->rows_count('cms_modules', "content = '{$content}' AND user = 0", 1)) { continue; }

        $inDB->insert('cms_modules', array(
            'position'              => 'sidebar',
            'name'                  => $m[0],
            'title'                 => $m[1],
            'titles'                => '',
            'is_external'           => 1,
            'content'               => $content,
            'ordering'              => 50,
            'showtitle'             => 1,
            'published'             => 1,
            'user'                  => 0,
            'config'                => $inDB->escape_string(cmsCore::arrayToYaml($m[2])),
            'original'              => 1,
            'css_prefix'            => '',
            'access_list'           => '',
            'hidden_menu_ids'       => '',
            'cache'                 => 0,
            'cachetime'             => 1,
            'cacheint'              => 'HOUR',
            'template'              => 'module.tpl',
            'is_strict_bind'        => 0,
            'is_strict_bind_hidden' => 0,
            'author'                => 'StudiaHive',
            'version'               => '1.0'
        ));

    }

    // ---- тип события для ленты активности сайта ----
    cmsCore::loadClass('actions');
    cmsActions::registerAction('feedwall', array(
        'name'        => 'add_feedwall_post',
        'title'       => 'feed-wall: новая запись',
        'message'     => 'добавляет запись в ленту %s|'
    ));

    // ---- CRON ----
    cmsCore::loadClass('cron');
    if (!cmsCron::getJob('feedwall_cron', false)){
        cmsCron::registerJob('feedwall_cron', array(
            'interval'     => 1,
            'component'    => 'feedwall',
            'model_method' => 'cronJob',
            'custom_file'  => '',
            'enabled'      => 1,
            'comment'      => 'feed-wall: синхронизация ленты, импорт RSS, очистка'
        ));
    }

    return true;

}

function feedwall_schema_remove(){

    $inDB = cmsDatabase::getInstance();

    foreach(array_keys(feedwall_schema_tables()) as $table){
        $inDB->query("DROP TABLE IF EXISTS `cms_{$table}`", true);
    }

    // права групп
    foreach(array_keys(feedwall_access_types()) as $type){
        cmsUser::deleteGroupAccessType($type);
    }
    $groups = $inDB->get_table('cms_user_groups', "access LIKE '%feedwall/%'", 'id, access');
    if ($groups){
        foreach($groups as $g){
            $list = array_filter(array_map('trim', explode(',', $g['access'])), function($a){ return strpos($a, 'feedwall/') !== 0; });
            $inDB->query("UPDATE cms_user_groups SET access = '".$inDB->escape_string(implode(', ', $list))."' WHERE id = '{$g['id']}'");
        }
    }

    // плагин
    foreach(array('p_feedwall', 'p_feedwall_likes') as $name){
        $plugin_id = (int)$inDB->get_field('cms_plugins', "plugin = '{$name}'", 'id');
        if ($plugin_id){
            $inDB->query("DELETE FROM cms_event_hooks WHERE plugin_id = '{$plugin_id}'");
            $inDB->query("DELETE FROM cms_plugins WHERE id = '{$plugin_id}'");
        }
    }

    // модули (removeComponent удаляет только один экземпляр каждого)
    $mods = $inDB->get_table('cms_modules', "content IN ('mod_feedwall_form','mod_feedwall_list','mod_feedwall_tags')", 'id');
    if ($mods){
        foreach($mods as $m){
            $inDB->query("DELETE FROM cms_modules_bind WHERE module_id = '{$m['id']}'");
            $inDB->query("DELETE FROM cms_modules WHERE id = '{$m['id']}'");
        }
    }

    cmsCore::loadClass('cron');
    cmsCron::removeJob('feedwall_cron');

    // события ленты активности
    $inDB->query("DELETE l FROM cms_actions_log l INNER JOIN cms_actions a ON a.id = l.action_id WHERE a.component = 'feedwall'");
    $inDB->query("DELETE FROM cms_actions WHERE component = 'feedwall'");

    // загруженные файлы
    $dir = PATH.'/upload/feedwall';
    if (is_dir($dir)){ feedwall_rrmdir($dir); }

    return true;

}

function feedwall_rrmdir($dir){
    foreach((@scandir($dir) ?: array()) as $f){
        if ($f === '.' || $f === '..' || $f === '') { continue; }
        $p = $dir.'/'.$f;
        if (is_dir($p)) { feedwall_rrmdir($p); } else { @unlink($p); }
    }
    @rmdir($dir);
}
