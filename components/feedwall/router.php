<?php
/******************************************************************************/
//                    feed-wall (feedwall) for InstantCMS 1.11                //
//              Copyright (c) 2026 StudiaHive / InstantCMS community revival  //
//                        LICENSED BY GNU/GPL v2                              //
/******************************************************************************/

function routes_feedwall(){

    $routes = array();

    $routes[] = array('_uri' => '/^feedwall\/page\-([0-9]+)$/i', 'do' => 'view', 1 => 'page');
    $routes[] = array('_uri' => '/^feedwall\/(all|friends|my|popular|discussed|viewed)$/i', 'do' => 'view', 1 => 'tab');
    $routes[] = array('_uri' => '/^feedwall\/(all|friends|my|popular|discussed|viewed)\/page\-([0-9]+)$/i', 'do' => 'view', 1 => 'tab', 2 => 'page');
    $routes[] = array('_uri' => '/^feedwall\/post\/([0-9]+)$/i', 'do' => 'post', 1 => 'id');
    $routes[] = array('_uri' => '/^feedwall\/tag\/([^\/]+)$/iu', 'do' => 'tag', 1 => 'tag');
    $routes[] = array('_uri' => '/^feedwall\/tag\/([^\/]+)\/page\-([0-9]+)$/iu', 'do' => 'tag', 1 => 'tag', 2 => 'page');
    $routes[] = array('_uri' => '/^feedwall\/(user|club)\/([0-9]+)$/i', 'do' => 'wall', 1 => 'target_type', 2 => 'target_id');
    $routes[] = array('_uri' => '/^feedwall\/(user|club)\/([0-9]+)\/page\-([0-9]+)$/i', 'do' => 'wall', 1 => 'target_type', 2 => 'target_id', 3 => 'page');
    $routes[] = array('_uri' => '/^feedwall\/likes\/([0-9]+)$/i', 'do' => 'likes', 1 => 'user_id');
    $routes[] = array('_uri' => '/^feedwall\/likes\/([0-9]+)\/page\-([0-9]+)$/i', 'do' => 'likes', 1 => 'user_id', 2 => 'page');
    $routes[] = array('_uri' => '/^feedwall\/(photos|music)\/([0-9]+)$/i', 'do' => 'media', 1 => 'media_type', 2 => 'user_id');
    $routes[] = array('_uri' => '/^feedwall\/(photos|music)\/([0-9]+)\/page\-([0-9]+)$/i', 'do' => 'media', 1 => 'media_type', 2 => 'user_id', 3 => 'page');
    $routes[] = array('_uri' => '/^feedwall\/settings$/i', 'do' => 'settings');
    $routes[] = array('_uri' => '/^feedwall\/ads$/i', 'do' => 'ads');
    $routes[] = array('_uri' => '/^feedwall\/go\/([0-9]+)$/i', 'do' => 'go', 1 => 'id');
    $routes[] = array('_uri' => '/^feedwall\/ajax\/([a-z_]+)$/i', 'do' => 'ajax', 1 => 'action');

    return $routes;

}
