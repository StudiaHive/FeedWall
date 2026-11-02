{* feed-wall: обёртка страницы. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-page">
    <h1 class="con_heading">{$heading|fwe}</h1>
    {if $back}<div class="fw-back"><a href="{$back}">&larr; {$LANG.FW_BACK}</a></div>{/if}
    {$body}
</div>
