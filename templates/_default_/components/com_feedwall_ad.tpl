{* feed-wall: рекламный блок. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-post fw-ad" data-ad="{$ad.id}">
    <div class="fw-ad-label">{$LANG.FW_AD_LABEL}</div>
    
    {if $ad.image}<a href="{$ad.go_url}" target="_blank" rel="nofollow noopener" class="fw-ad-img"><img src="{$ad.image|fwe}" alt="" /></a>{/if}
    {if $ad.title}<div class="fw-ad-title"><a href="{$ad.go_url}" target="_blank" rel="nofollow noopener">{$ad.title|fwe}</a></div>{/if}
    {if $ad.text}<div class="fw-ad-text">{$ad.text|fwe|nl2br}</div>{/if}
    {if $ad.url}<a href="{$ad.go_url}" target="_blank" rel="nofollow noopener" class="fw-button fw-ad-btn">{$LANG.FW_AD_MORE}</a>{/if}
</div>
