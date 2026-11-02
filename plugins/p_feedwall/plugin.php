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
class p_feedwall extends cmsPlugin {

    public function __construct(){

        $this->info['plugin']      = 'p_feedwall';
        $this->info['title']       = 'feed-wall: стена профиля и клубов';
        $this->info['description'] = 'Вкладка «Стена» в профиле, стена в клубах, замена стандартной стены, очистка при удалении пользователя';
        $this->info['author']      = 'StudiaHive / InstantCMS community';
        $this->info['version']     = '1.0.0';

        $this->events[] = 'USER_PROFILE';
        $this->events[] = 'GET_SINGLE_CLUB';
        $this->events[] = 'DELETE_USER';
        $this->events[] = 'PRINT_PAGE_HEAD';

        parent::__construct();

    }

    private function ready(){
        if (!$this->inCore->isComponentEnable('feedwall')) { return false; }
        if (!function_exists('fw_model')) { cmsCore::includeFile('components/feedwall/includes/render.php'); }
        return function_exists('fw_model');
    }

    public function execute($event = '', $item = array()){

        switch($event){

            case 'USER_PROFILE':
                if (!$this->ready() || empty($item['id'])) { return false; }
                $model = fw_model(); $_LANG = $model->lang();
                if (!$model->config['profile_tab']) { return false; }
                $this->info['tab'] = $_LANG['FW_PROFILE_WALL'];
                fw_add_assets();
                return '<div class="fw-profile-wall">'.fw_render_wall('user', (int)$item['id']).'</div>';

            case 'GET_SINGLE_CLUB':
                if (!$this->ready() || empty($item['id'])) { return false; }
                $model = fw_model(); $_LANG = $model->lang();
                if (!$model->config['club_wall']) { return false; }
                $club = $model->getClub((int)$item['id']);
                if (!$club || !$club['can_view']) { return false; }
                fw_add_assets();
                return '<div class="fw-club-wall"><h3 class="fw-club-wall-title">'.fw_esc($_LANG['FW_CLUB_WALL']).'</h3>'.fw_render_wall('club', (int)$item['id']).'</div>';

            case 'DELETE_USER':
                if ($this->ready() && $this->inDB->isTableExists('cms_feedwall_posts')) { fw_model()->deleteUserData((int)$item); }
                return $item;

            case 'PRINT_PAGE_HEAD':
                if (!$this->ready()) { return $item; }
                $model = fw_model();
                $com = $this->inCore->component;
                if ($model->config['replace_wall'] && in_array($com, array('users', 'clubs'), true)){
                    $item[] = '<style type="text/css">.usr_wall.usr_profile_block,.club_wall_addlink{display:none}</style>';
                    $item[] = '<script type="text/javascript">jQuery(function($){'.
                              'var w=$(".fw-profile-wall").first(),n=$(".usr_wall.usr_profile_block").first();'.
                              'if(w.length&&n.length){n.replaceWith(w);$("a[href=\'#upr_p_feedwall\']").parent().hide();}'.
                              'var c=$(".club_wall_addlink").closest(".wall");if($(".fw-club-wall").length){c.remove();}'.
                              '});</script>';
                }
                return $item;

        }

        return $item;

    }

}
