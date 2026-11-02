{* feed-wall: одна запись. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-post{if $post.is_pinned || $post.is_pinned_feed} fw-pinned{/if}{if $post.type=='activity'} fw-activity{/if}" id="fw-post-{$post.id}" data-id="{$post.id}" data-author="{$post.user_id}">
    <div class="fw-post-head">
        {if $post.author}
            <a class="fw-ava fw-usercard" data-uid="{$post.author.id}" href="{$post.author.url}"><img src="{$post.author.avatar}" alt="" /></a>
        {else}
            <span class="fw-ava fw-ava-anon">{if $post.is_guest}&#128100;{else}&#127917;{/if}</span>
        {/if}
        <div class="fw-post-who">
            {if $post.author}
                <a class="fw-name fw-usercard" data-uid="{$post.author.id}" href="{$post.author.url}">{$post.author.nickname|fwe}</a>{if $post.author.is_verified}<span class="fw-verified" title="{$LANG.FW_VERIFIED}">&#10004;</span>{/if}
            {elseif $post.is_guest}
                <span class="fw-name">{if $post.guest_name}{$post.guest_name|fwe}{else}{$LANG.FW_GUEST}{/if}</span> <span class="fw-guest-mark">{$LANG.FW_GUEST}</span>
            {else}
                <span class="fw-name">{$LANG.FW_ANONYMOUS}</span>
            {/if}
            {if $post.target}<span class="fw-target"> &#9656; <a href="{$post.target.url}">{$post.target.title|fwe}</a></span>{/if}
            {if $post.type=='share'}<span class="fw-share-mark">{$LANG.FW_SHARED}</span>{/if}
            <div class="fw-post-meta">
                <a class="fw-date" href="{$post.url}" title="{$post.date_full|strip_tags}">{$post.date}</a>
                {if $post.date_edit}<span class="fw-edited" title="{$post.date_edit}">&middot; {$LANG.FW_EDITED}</span>{/if}
                {if $post.is_private}<span class="fw-private" title="{$LANG.FW_PRIVATE}">&#128274;</span>{/if}
                {if $post.is_pinned || $post.is_pinned_feed}<span class="fw-pin-mark" title="{$LANG.FW_PINNED}">&#128204;</span>{/if}
                {if $post.type=='rss' && $post.source_url}<a class="fw-source" href="{$post.source_url|fwe}" target="_blank" rel="nofollow noopener">{$LANG.FW_SOURCE}</a>{/if}
            </div>
        </div>
        {if $post.type!='activity'}
        <div class="fw-menu">
            <a class="fw-menu-btn" href="#" data-act="menu">&#8943;</a>
            <ul class="fw-menu-list">
                <li><a href="#" data-act="copy-link">{$LANG.FW_COPY_LINK}</a></li>
                {if $post.can_edit}<li><a href="#" data-act="edit">{$LANG.FW_EDIT}</a></li>{/if}
                {if $post.can_pin}<li><a href="#" data-act="pin">{if $post.is_pinned}{$LANG.FW_UNPIN}{else}{$LANG.FW_PIN}{/if}</a></li>{/if}
                {if $post.can_pin_feed && $post.target_type!='club'}<li><a href="#" data-act="pin_feed">{if $post.is_pinned_feed}{$LANG.FW_UNPIN_FEED}{else}{$LANG.FW_PIN_FEED}{/if}</a></li>{/if}
                {if $fwme.id && !$post.is_my}<li><a href="#" data-act="hide">{$LANG.FW_HIDE_POST}</a></li>
                    {if $post.author}<li><a href="#" data-act="hide_author">{$LANG.FW_HIDE_AUTHOR}</a></li>{/if}{/if}
                {if $fwcfg.complaints && !$post.is_my}<li><a href="#" data-act="complain">{$LANG.FW_COMPLAIN}</a></li>{/if}
                {if $post.can_delete}<li><a href="#" data-act="delete" class="fw-danger">{$LANG.FW_DELETE}</a></li>{/if}
            </ul>
        </div>
        {/if}
    </div>

    <div class="fw-post-body">
    {if $post.activity}
        <div class="fw-activity-msg">{$post.activity.message}</div>
        {if $post.activity.description}<div class="fw-activity-desc">{$post.activity.description}</div>{/if}
        {if $post.activity.image}<a class="fw-activity-img" href="{$post.activity.url|fwe}"><img src="{$post.activity.image}" alt="" loading="lazy" /></a>{/if}
    {else}
        {if $post.bg && $post.html}
            <div class="fw-text fw-colored" style="{$post.bg.style|fwe}">{$post.html}</div>
        {elseif $post.html}
            <div class="fw-text{if !$post.is_full} fw-cut{/if}">{$post.html}</div>
        {/if}
        {include file='com_feedwall_attach.tpl' p=$post}
        {if $post.type=='share'}
            {if $post.original}
                <div class="fw-repost">
                    <div class="fw-repost-head">
                        {if $post.original.author}<a href="{$post.original.author.url}"><img class="fw-ava-xs" src="{$post.original.author.avatar}" alt="" /> {$post.original.author.nickname|fwe}</a>
                        {else}{if $post.original.guest_name}{$post.original.guest_name|fwe}{else}{$LANG.FW_ANONYMOUS}{/if}{/if}
                        &middot; <a class="fw-date" href="{$post.original.url}">{$post.original.date}</a>
                    </div>
                    {if $post.original.bg && $post.original.html}<div class="fw-text fw-colored" style="{$post.original.bg.style|fwe}">{$post.original.html}</div>
                    {elseif $post.original.html}<div class="fw-text fw-cut">{$post.original.html}</div>{/if}
                    {include file='com_feedwall_attach.tpl' p=$post.original}
                </div>
            {else}
                <div class="fw-repost fw-repost-deleted">{$LANG.FW_ORIGINAL_DELETED}</div>
            {/if}
        {/if}
    {/if}
    </div>

    {if $post.social}
        {include file='com_feedwall_bar.tpl' post=$post}
        {if !$fwcfg.off_comments && ($post.comments_count || $post.can_comment)}
        <div class="fw-comments" data-post="{$post.id}">
            {if $post.comments_count > $post.comments_shown}
                <a href="#" class="fw-more-comments" data-act="all-comments">{$LANG.FW_SHOW_ALL_COMMENTS} ({$post.comments_count})</a>
            {/if}
            <div class="fw-comments-list">
                {foreach $post.comments as $c}{include file='com_feedwall_comment.tpl' c=$c post=$post}{/foreach}
            </div>
            {if $post.can_comment}{include file='com_feedwall_cform.tpl' post=$post}{elseif !$fwme.id}<div class="fw-login-hint"><a href="/login">{$LANG.FW_LOGIN_TO_COMMENT}</a></div>{/if}
        </div>
        {/if}
    {/if}
</div>
