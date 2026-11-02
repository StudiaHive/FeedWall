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

function mod_feedwall_form($mod, $cfg){

    if (!mod_feedwall_init()) { return false; }

    $model = fw_model();
    $inUser = cmsUser::getInstance();
    // на страницах самого компонента форма уже есть
    if (cmsCore::getInstance()->component == 'feedwall') { return false; }

    $target = isset($cfg['target']) && $cfg['target'] == 'my_wall' && $inUser->id ? 'user' : 'feed';
    $form = fw_render_form($target, $target == 'user' ? $inUser->id : 0, array('compact' => true));
    if (!$form) { return false; }

    fw_add_assets();

    echo fw_tpl(mod_feedwall_tpl($cfg, 'mod_feedwall_form'), array('form' => $form, 'mod' => $mod), 'modules');
    return true;

}
