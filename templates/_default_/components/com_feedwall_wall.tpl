{* feed-wall: стена пользователя / клуба. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-wall" data-target-type="{$target_type}" data-target-id="{$target_id}">
    {$form}
    <div class="fw-posts" data-ctx="{$ctx_json}" data-page="{$page}" data-more="{if $has_more}1{else}0{/if}">{$posts}</div>
    {if $is_empty}<div class="fw-empty">{$LANG.FW_WALL_EMPTY}</div>{/if}
    {if $has_more}<div class="fw-more-wrap"><a href="#" class="fw-button fw-more">{$LANG.FW_SHOW_MORE}</a></div>{/if}
</div>
