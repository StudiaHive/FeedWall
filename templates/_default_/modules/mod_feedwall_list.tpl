{* feed-wall: модуль последних записей. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-mod fw-mod-list">
{foreach $items as $it}
    <div class="fw-mod-item">
        {if $it.author_url}<a href="{$it.author_url}" class="fw-mod-ava"><img src="{$it.avatar}" alt="" /></a>{else}<span class="fw-mod-ava"><img src="{$it.avatar}" alt="" /></span>{/if}
        <div class="fw-mod-body">
            <div class="fw-mod-head">{if $it.author_url}<a href="{$it.author_url}">{$it.author|fwe}</a>{else}<span>{$it.author|fwe}</span>{/if} <span class="fw-mod-date">{$it.date}</span></div>
            {if $show_text && $it.text}<div class="fw-mod-text"><a href="{$it.url}">{$it.text|fwe}</a></div>{elseif $it.has_media}<div class="fw-mod-text"><a href="{$it.url}">{$LANG.FW_MOD_MEDIA}</a></div>{/if}
            <div class="fw-mod-stat">&#9829; {$it.likes} &nbsp; &#128172; {$it.comments}</div>
        </div>
    </div>
{/foreach}
{if $show_link}<div class="fw-mod-all"><a href="/feedwall">{$LANG.FW_MOD_ALL}</a></div>{/if}
</div>
