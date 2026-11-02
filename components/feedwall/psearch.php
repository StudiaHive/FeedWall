<?php
/******************************************************************************/
//                                                                            //
//              feed-wall 1.0.0 — компонент ленты и стены для InstantCMS 1.x  //
//           (c) StudiaHive / InstantCMS community revival, GPL v2            //
//                                                                            //
/******************************************************************************/

if(!defined('VALID_CMS')) { die('ACCESS DENIED'); }

/**
 * Поиск по записям feed-wall (только публичное содержимое, видимое гостю).
 */
function search_feedwall($query, $look){

    $inDB        = cmsDatabase::getInstance();
    $searchModel = cms_model_search::initModel();

    global $_LANG;

    $sql = "SELECT p.id, p.text, p.date_pub, p.user_id, p.is_anonymous, u.nickname
            FROM cms_feedwall_posts p
            LEFT JOIN cms_users u ON u.id = p.user_id
            WHERE MATCH(p.text) AGAINST ('$query' IN BOOLEAN MODE)
              AND p.is_pub = 1 AND p.is_private = 0 AND p.type = 'post'
              AND (p.target_type <> 'club' OR p.target_id IN (SELECT c.id FROM cms_clubs c WHERE c.clubtype <> 'private'))
            ORDER BY p.date_pub DESC LIMIT 100";

    $result = $inDB->query($sql);

    if (!$result || !$inDB->num_rows($result)) { return; }

    cmsCore::loadLanguage('components/feedwall');

    while($item = $inDB->fetch_assoc($result)){

        $text  = trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string)$item['text'], ENT_QUOTES, 'UTF-8'))));
        $title = mb_substr($text, 0, 70);
        if (mb_strlen($text) > 70) { $title .= '...'; }
        if ($title === '') { $title = '#'.$item['id']; }

        $place = $_LANG['FW_COMPONENT'];
        if (!$item['is_anonymous'] && $item['nickname']) { $place .= ' &rarr; '.htmlspecialchars($item['nickname'], ENT_QUOTES, 'UTF-8'); }

        $searchModel->addResult(array(
            'link'        => '/feedwall/post/'.$item['id'],
            'place'       => $place,
            'placelink'   => '/feedwall',
            'description' => $searchModel->getProposalWithSearchWord($text),
            'title'       => $title,
            'pubdate'     => $item['date_pub'],
            'session_id'  => session_id()
        ));

    }

}
