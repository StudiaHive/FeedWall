<?php
/******************************************************************************/
//                                                                            //
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                                                                            //
//                        LICENSED BY GNU/GPL v2                              //
//                                                                            //
/******************************************************************************/

#[\AllowDynamicProperties]
class p_feedwall_likes extends cmsPlugin {

    public function __construct(){

        $this->info['plugin']      = 'p_feedwall_likes';
        $this->info['title']       = 'feed-wall: вкладка «Понравилось»';
        $this->info['description'] = 'Вкладка «Понравилось» в профиле пользователя';
        $this->info['author']      = 'StudiaHive / InstantCMS community';
        $this->info['version']     = '1.0.0';

        $this->events[] = 'USER_PROFILE';

        parent::__construct();

    }

    public function execute($event = '', $item = array()){

        if ($event != 'USER_PROFILE' || empty($item['id'])) { return false; }
        if (!$this->inCore->isComponentEnable('feedwall')) { return false; }
        if (!function_exists('fw_model')) { cmsCore::includeFile('components/feedwall/includes/render.php'); }

        $model = fw_model(); $_LANG = $model->lang();
        if (!$model->config['likes_tab']) { return false; }

        $this->info['tab'] = $_LANG['FW_PROFILE_LIKES'];

        list($items, $has_more) = $model->getPosts(array('type'=>'likes', 'user_id'=>(int)$item['id']), 1);
        if (!$items) { return '<p class="fw-empty">'.fw_esc($_LANG['FW_NO_LIKES']).'</p>'; }

        fw_add_assets();

        return fw_tpl('com_feedwall_list', array(
            'heading'  => '',
            'form'     => '',
            'posts'    => fw_render_posts($items, 1, false),
            'is_empty' => false,
            'has_more' => $has_more,
            'page'     => 1,
            'ctx_json' => fw_esc(json_encode(array('kind'=>'likes', 'user_id'=>(int)$item['id']))),
            'tags'     => array()
        ));

    }

}
