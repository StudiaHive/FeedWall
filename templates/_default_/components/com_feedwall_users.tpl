{* feed-wall: список пользователей. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-users-pop">
    <h3>{$title}</h3>
    {if !$users}<p class="fw-empty">{$LANG.FW_NOBODY}</p>{/if}
    <ul class="fw-users">
    {foreach $users as $u}
        <li><a href="{$u.url}"><img class="fw-ava-s" src="{$u.avatar}" alt="" /> {$u.nickname|fwe}</a>{if $show_reaction && $u.reaction && isset($fwreact[$u.reaction])} <span class="fw-ur">{$fwreact[$u.reaction].emoji}</span>{/if}</li>
    {/foreach}
    </ul>
</div>
