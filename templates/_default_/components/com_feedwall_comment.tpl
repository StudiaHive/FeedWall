{* feed-wall: комментарий. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-comment{if $c.parent_id} fw-answer{/if}" id="fw-comment-{$c.id}" data-id="{$c.id}" data-post="{$c.post_id}" data-root="{if $c.parent_id}{$c.parent_id}{else}{$c.id}{/if}" data-login="{if $c.user}{$c.user.login}{/if}">
    {if $c.user}
        <a href="{$c.user.url}" class="fw-ava-s fw-usercard" data-uid="{$c.user.id}"><img src="{$c.user.avatar}" alt="" /></a>
    {else}<span class="fw-ava-s fw-ava-anon">&#128100;</span>{/if}
    <div class="fw-cbody">
        <div class="fw-cbubble">
            <div class="fw-chead">
                {if $c.user}<a href="{$c.user.url}" class="fw-name fw-usercard" data-uid="{$c.user.id}">{$c.user.nickname|fwe}</a>{if $c.user.is_verified}<span class="fw-verified">&#10004;</span>{/if}{else}<span class="fw-name">{$LANG.FW_DELETED_USER}</span>{/if}
                {if $c.answer_user && $c.parent_id}<span class="fw-to">&#8594; {$c.answer_user.nickname|fwe}</span>{/if}
            </div>
            <div class="fw-ctext" data-raw="{$c.text|fwe}">{$c.html}</div>
        </div>
        {if $c.photos}<div class="fw-cphotos">{foreach $c.photos as $ph}<a href="{$ph.file}" class="fw-photo" rel="fwc{$c.id}"><img src="{$ph.thumb}" alt="" loading="lazy" /></a>{/foreach}</div>{/if}
        {if $c.gif}<div class="fw-gif fw-cgif"><img src="{$c.gif.url|fwe}" alt="GIF" loading="lazy" /></div>{/if}
        {if $c.voice}<div class="fw-voice" data-id="{$c.voice.id}">&#127908; <audio controls preload="none" src="{$c.voice.file}"></audio>{if $c.voice.duration}<span class="fw-vdur">{$c.voice.duration}{$LANG.FW_SEC}</span>{/if}</div>{/if}
        <div class="fw-cfoot">
            <span class="fw-date" title="{$c.date_pub}">{$c.date}</span>
            {if $post.can_comment}<a href="#" data-act="reply">{$LANG.FW_REPLY}</a>{/if}
            {if $c.can_like}<a href="#" class="fw-clike{if $c.is_liked} active{/if}" data-act="comment_like">&#10084; <span>{if $c.likes_count}{$c.likes_count}{/if}</span></a>
            {elseif $c.likes_count}<span class="fw-clike">&#10084; {$c.likes_count}</span>{/if}
            {if $c.likes_count}<a href="#" data-act="comment_reactors" class="fw-small">{$LANG.FW_WHO_LIKED}</a>{/if}
            {if $c.can_edit}<a href="#" data-act="comment_edit">{$LANG.FW_EDIT}</a>{/if}
            {if $c.can_delete}<a href="#" data-act="comment_delete" class="fw-danger">{$LANG.FW_DELETE}</a>{/if}
            {if $fwcfg.complaints && $fwme.id != $c.user_id}<a href="#" data-act="comment_complain" class="fw-small">{$LANG.FW_COMPLAIN}</a>{/if}
        </div>
        {if !$c.parent_id}
            <div class="fw-answers">
                {foreach $c.answers as $a}{include file='com_feedwall_comment.tpl' c=$a post=$post}{/foreach}
            </div>
            {if $c.answers_count > count($c.answers)}<a href="#" class="fw-more-answers" data-act="answers">{$LANG.FW_SHOW_ANSWERS} ({$c.answers_count})</a>{/if}
        {/if}
    </div>
</div>
