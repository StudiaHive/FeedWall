{* feed-wall: карточка пользователя. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-ucard">
    <a href="{$u.url}"><img class="fw-ucard-ava" src="{$u.avatar}" alt="" /></a>
    <div class="fw-ucard-info">
        <a class="fw-name" href="{$u.url}">{$u.nickname|fwe}</a>{if $u.is_verified}<span class="fw-verified">&#10004;</span>{/if}
        {if $u.status_text}<div class="fw-ucard-status">{$u.status_text|fwe}</div>{/if}
        <div class="fw-ucard-stats">{$LANG.FW_POSTS}: <b>{$u.posts_count}</b> &middot; {$LANG.FW_FRIENDS}: <b>{$u.friends_count}</b>{if $u.is_friend} &middot; {$LANG.FW_IS_FRIEND}{/if}</div>
        <div class="fw-ucard-links">
            <a href="/feedwall/user/{$u.id}">{$LANG.FW_WALL}</a> &middot;
            <a href="/feedwall/photos/{$u.id}">{$LANG.FW_PHOTOS}</a> &middot;
            <a href="/feedwall/music/{$u.id}">{$LANG.FW_MUSIC}</a>
            {if $fwme.id && $fwme.id != $u.id} &middot; <a href="{$u.url}" class="fw-ucard-msg">{$LANG.FW_WRITE_MESSAGE}</a>{/if}
        </div>
    </div>
</div>
