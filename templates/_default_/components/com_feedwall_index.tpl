{* feed-wall: главная лента. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-page fw-index">
    <h1 class="con_heading">{$title|fwe}</h1>
    <div class="fw-tabs">
        {foreach $tabs as $t}<a href="{$t.url}" class="fw-tab{if $t.active} active{/if}">{$t.title}</a>{/foreach}
        {if $fwme.id}<a href="/feedwall/settings" class="fw-tab fw-tab-settings" title="{$LANG.FW_SETTINGS}">&#9881;</a>{/if}
    </div>
    {if $periods}
    <div class="fw-periods">
        <a href="/feedwall/{$tab}" class="{if !$period}active{/if}">{$LANG.FW_PERIOD_ALL}</a>
        <a href="/feedwall/{$tab}?period=day" class="{if $period=='day'}active{/if}">{$LANG.FW_PERIOD_DAY}</a>
        <a href="/feedwall/{$tab}?period=week" class="{if $period=='week'}active{/if}">{$LANG.FW_PERIOD_WEEK}</a>
        <a href="/feedwall/{$tab}?period=month" class="{if $period=='month'}active{/if}">{$LANG.FW_PERIOD_MONTH}</a>
    </div>
    {/if}
    {$form}
    <div class="fw-posts" data-ctx="{$ctx_json}" data-page="{$page}" data-more="{if $has_more}1{else}0{/if}">{$posts}</div>
    {if $is_empty}<div class="fw-empty">{$LANG.FW_EMPTY}</div>{/if}
    {if $has_more}<div class="fw-more-wrap"><a href="#" class="fw-button fw-more">{$LANG.FW_SHOW_MORE}</a></div>{/if}
    {$pagebar}
</div>
