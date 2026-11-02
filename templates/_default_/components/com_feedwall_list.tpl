{* feed-wall: список записей (хэштег, понравившееся). (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-page fw-list">
    {if $heading}<h1 class="con_heading">{$heading|fwe}</h1>{/if}
    {$form}
    <div class="fw-posts" data-ctx="{$ctx_json}" data-page="{$page}" data-more="{if $has_more}1{else}0{/if}">{$posts}</div>
    {if $is_empty}<div class="fw-empty">{$LANG.FW_EMPTY}</div>{/if}
    {if $has_more}<div class="fw-more-wrap"><a href="#" class="fw-button fw-more">{$LANG.FW_SHOW_MORE}</a></div>{/if}
    {if $tags}
        <div class="fw-tagcloud"><h3>{$LANG.FW_POPULAR_TAGS}</h3>{foreach $tags as $t}<a href="{$t.url}" class="fw-tag fw-tag-{$t.size}">#{$t.tag}</a> {/foreach}</div>
    {/if}
</div>
