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

function mod_feedwall_tags($mod, $cfg){

    if (!mod_feedwall_init()) { return false; }

    $model = fw_model();
    if (!$model->config['hashtags']) { return false; }

    $tags = $model->getTags(isset($cfg['limit']) ? max(1, min(200, (int)$cfg['limit'])) : 30);
    if (!$tags) { return false; }

    fw_add_assets();

    echo fw_tpl(mod_feedwall_tpl($cfg, 'mod_feedwall_tags'), array('tags' => $tags, 'mod' => $mod), 'modules');
    return true;

}
