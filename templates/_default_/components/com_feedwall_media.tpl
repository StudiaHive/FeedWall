{* feed-wall: фото и музыка пользователя. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-page fw-media">
    <h1 class="con_heading">{$heading|fwe}</h1>
    <div class="fw-tabs">
        <a class="fw-tab{if $type=='photo'} active{/if}" href="/feedwall/photos/{$owner.id}">{$LANG.FW_PHOTOS}</a>
        <a class="fw-tab{if $type=='audio'} active{/if}" href="/feedwall/music/{$owner.id}">{$LANG.FW_MUSIC}</a>
        <a class="fw-tab" href="{$owner.url}">&larr; {$owner.nickname|fwe}</a>
    </div>
    {if !$list}<div class="fw-empty">{$LANG.FW_NO_MEDIA}</div>{/if}
    {if $type=='photo'}
        <div class="fw-grid">{foreach $list as $m}<a href="{$m.file}" class="fw-photo" rel="fwmedia"><img src="{$m.thumb}" alt="" loading="lazy" /></a>{/foreach}</div>
    {else}
        <div class="fw-audios">{foreach $list as $a}
            <div class="fw-audio" data-id="{$a.id}"><div class="fw-atitle">&#127925; {if $a.artist}<b>{$a.artist|fwe}</b> &mdash; {/if}{$a.title|fwe} <small>({$a.plays_count})</small></div><audio controls preload="none" src="{$a.file}"></audio></div>
        {/foreach}</div>
    {/if}
    {$pagebar}
</div>
