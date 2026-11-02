<?php
/******************************************************************************/
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                        LICENSED BY GNU/GPL v2                              //
/******************************************************************************/

if(!defined('VALID_CMS')) { die('ACCESS DENIED'); }

/**
 * Настройки компонента по умолчанию
 */
function feedwall_default_config(){

    return array(
        // лента
        'perpage'          => 15,
        'infinite'         => 1,
        'infinite_type'    => 'scroll',   // scroll | button
        'main_sort'        => 'all',      // all | friends | popular | discussed | viewed
        'main_sort_guest'  => 'all',
        'show_activity'    => 1,          // показывать события ленты активности сайта
        'activity_types'   => '',         // через запятую, пусто = все
        'activity_social'  => 1,          // лайки/комменты/репосты для событий
        'activity_images'  => 1,          // картинки фото/статей в событиях
        'views'            => 1,
        'cut_height'       => 300,
        'cut_length'       => 0,
        // микроблог
        'maxlength'        => 3000,
        'placeholder'      => '',
        'placeholder_user' => '',
        'autoclose'        => 0,
        'daily_limit'      => 0,
        'edit_time'        => 0,
        'colored_max'      => 200,
        'photos_max'       => 10,
        'photo_size'       => 1280,
        'video_max'        => 3,
        'audio_max_mb'     => 10,
        'voice_max_sec'    => 60,
        'link_preview'     => 1,
        'voice'            => 1,          // голосовые сообщения в комментариях
        'gifs'             => 1,
        'poll_photos'      => 1,          // фото в вариантах ответа опроса
        'post_to_wall'     => 1,
        'guest_add'        => 0,
        'captcha'          => 1,
        // социальное
        'reactions'        => 1,          // 1 = 6 реакций, 0 = только «нравится»
        'off_likes'        => 0,
        'off_comments'     => 0,
        'off_share'        => 0,
        'share_buttons'    => 1,
        'mentions'         => 1,
        'mention_source'   => 'users',    // users | friends
        'hashtags'         => 1,
        'smiles'           => 1,
        // комментарии
        'comm_maxlength'   => 1000,
        'comm_sort'        => 'asc',
        'comm_preview'     => 3,
        'comm_edit_time'   => 0,
        // GIF
        'gif_provider'     => 'local',    // local | tenor
        'gif_key'          => '',
        // жалобы
        'complaints'       => 1,
        'complaint_email'  => '',
        // реклама
        'ads_url'          => '',
        'ads_repeat'       => 0,
        // профиль
        'verified_groups'  => '',
        'verified_users'   => '',
        'profile_tab'      => 1,
        'likes_tab'        => 1,
        'club_wall'        => 1,
        'replace_wall'     => 0,
        // бонусы к рейтингу
        'rating_post'      => 0,
        'rating_comment'   => 0,
        'rating_react'     => 0,
        'rating_like'      => 0,
        'rating_vote'      => 0,
        // RSS импорт
        'rss_user_id'      => 1,
        // SEO
        'seo_title'        => '',
        'seo_desc'         => '',
        'seo_title_view'   => '',
        'seo_desc_view'    => '',
        'seo_title_tag'    => '',
        'seo_desc_tag'     => ''
    );

}

/**
 * Права групп пользователей (тип доступа => название, скрывать для гостей, выдать группам)
 */
function feedwall_access_types(){

    return array(
        'feedwall/add'          => array('feed-wall: добавлять записи', 0, array('registered','editors','moderators')),
        'feedwall/photo'        => array('feed-wall: прикреплять фото', 0, array('registered','editors','moderators')),
        'feedwall/video'        => array('feed-wall: прикреплять видео', 0, array('registered','editors','moderators')),
        'feedwall/audio'        => array('feed-wall: прикреплять аудио', 0, array('registered','editors','moderators')),
        'feedwall/poll'         => array('feed-wall: создавать опросы', 0, array('registered','editors','moderators')),
        'feedwall/gif'          => array('feed-wall: прикреплять GIF', 0, array('registered','editors','moderators')),
        'feedwall/colored'      => array('feed-wall: цветные посты', 0, array('registered','editors','moderators')),
        'feedwall/anonymous'    => array('feed-wall: анонимные посты', 1, array('moderators')),
        'feedwall/react'        => array('feed-wall: реакции и лайки', 1, array('registered','editors','moderators')),
        'feedwall/comment'      => array('feed-wall: комментировать', 0, array('registered','editors','moderators')),
        'feedwall/comment_media'=> array('feed-wall: фото/GIF/голос в комментариях', 1, array('registered','editors','moderators')),
        'feedwall/share'        => array('feed-wall: репосты', 1, array('registered','editors','moderators')),
        'feedwall/edit'         => array('feed-wall: редактировать свои записи и комментарии', 1, array('registered','editors','moderators')),
        'feedwall/delete'       => array('feed-wall: удалять свои записи и комментарии', 1, array('registered','editors','moderators')),
        'feedwall/moderate'     => array('feed-wall: модерация (редактирование/удаление/закрепление чужого)', 1, array('moderators'))
    );

}

function feedwall_reaction_types(){
    return array('like', 'love', 'haha', 'wow', 'sad', 'angry');
}
