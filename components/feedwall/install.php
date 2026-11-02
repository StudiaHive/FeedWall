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

// ========================================================================== //

function info_component_feedwall(){

    $_component['title']       = 'Лента и стена (feed-wall)';
    $_component['description'] = 'Новостная лента и стены пользователей/клубов: посты, фото, видео, аудио, опросы, GIF, реакции, комментарии, репосты, хэштеги, упоминания, реклама.';
    $_component['link']        = 'feedwall';
    $_component['author']      = 'StudiaHive / InstantCMS community';
    $_component['internal']    = '0';
    $_component['version']     = '1.0.0';
    $_component['system']      = '0';
    $_component['published']   = '1';

    cmsCore::includeFile('components/feedwall/includes/defaults.php');
    $_component['config']      = feedwall_default_config();

    $_component['modules']     = array(
        'mod_feedwall_form' => 'feed-wall: форма микроблога',
        'mod_feedwall_list' => 'feed-wall: последние записи',
        'mod_feedwall_tags' => 'feed-wall: облако хэштегов'
    );

    $_component['plugins']     = array(
        'p_feedwall'       => 'feed-wall: стена профиля и клубов',
        'p_feedwall_likes' => 'feed-wall: вкладка «Понравилось»'
    );

    return $_component;

}

// ========================================================================== //

function install_component_feedwall(){

    cmsCore::includeFile('components/feedwall/includes/schema.php');

    $error = feedwall_schema_install();
    if ($error !== true) { return $error; }

    feedwall_install_extensions();

    return true;

}

// ========================================================================== //

function upgrade_component_feedwall(){

    cmsCore::includeFile('components/feedwall/includes/schema.php');

    // создаёт недостающие таблицы/колонки, данные не трогает
    $error = feedwall_schema_install();
    if ($error !== true) { return $error; }

    feedwall_install_extensions();

    return true;

}

// ========================================================================== //

function remove_component_feedwall(){

    cmsCore::includeFile('components/feedwall/includes/schema.php');

    feedwall_schema_remove();

    return true;

}
