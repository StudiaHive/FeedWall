{* feed-wall: реакции и действия. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-bar" data-id="{$post.id}">
    <div class="fw-bar-stats">
        {if $post.reactions_list}
            <a href="#" class="fw-react-sum" data-act="reactors" title="{$LANG.FW_REACTORS}">{foreach $post.reactions_list as $rk => $rn}{if isset($fwreact.$rk)}<span class="fw-rs" title="{$fwreact.$rk.title} {$rn}">{$fwreact.$rk.emoji}</span>{/if}{/foreach} <span class="fw-rcount">{$post.likes_count}</span></a>
        {/if}
        <span class="fw-bar-right">
            {if $post.comments_count}<span class="fw-stat">{$post.comments_count} {$LANG.FW_COMMENTS_SHORT}</span>{/if}
            {if $post.shares_count}<span class="fw-stat">{$post.shares_count} {$LANG.FW_SHARES_SHORT}</span>{/if}
            {if $fwcfg.views}<span class="fw-stat fw-views" title="{$LANG.FW_VIEWS}">&#128065; {$post.views_count}</span>{/if}
        </span>
    </div>
    <div class="fw-actions">
        {if $post.can_react}
            <span class="fw-react-wrap">
                <a href="#" class="fw-btn fw-react-btn{if $post.my_reaction} active{/if}" data-act="react" data-reaction="{if $post.my_reaction}{$post.my_reaction}{else}like{/if}">{if $post.my_reaction && isset($fwreact[$post.my_reaction])}{$fwreact[$post.my_reaction].emoji} {$fwreact[$post.my_reaction].title}{else}&#128077; {$LANG.FW_LIKE}{/if}</a>
                {if $fwcfg.reactions}<span class="fw-react-pop">{foreach $fwreact as $rk => $rv}<a href="#" data-act="react" data-reaction="{$rk}" title="{$rv.title}">{$rv.emoji}</a>{/foreach}</span>{/if}
            </span>
        {/if}
        {if $post.can_comment}<a href="#" class="fw-btn" data-act="comment-focus">&#128172; {$LANG.FW_COMMENT}</a>{/if}
        {if $post.can_share}<a href="#" class="fw-btn" data-act="share">&#8618; {$LANG.FW_SHARE}</a>{/if}
        {if $fwcfg.share_buttons && !$post.is_private}
            <span class="fw-social">
                <a href="#" class="fw-sbtn" data-net="vk" title="VK">VK</a>
                <a href="#" class="fw-sbtn" data-net="tg" title="Telegram">TG</a>
                <a href="#" class="fw-sbtn" data-net="x" title="X">X</a>
                <a href="#" class="fw-sbtn" data-net="fb" title="Facebook">f</a>
            </span>
        {/if}
    </div>
</div>
