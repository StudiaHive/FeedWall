{* feed-wall: статистика рекламы. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-page fw-ads">
    <h1 class="con_heading">{$LANG.FW_ADS_STATS}</h1>
    {if $ads_url}<p><a href="{$ads_url|fwe}">{$LANG.FW_ADS_ORDER}</a></p>{/if}
    {if !$ads}<div class="fw-empty">{$LANG.FW_ADS_NONE}</div>{/if}
    {foreach $ads as $ad}
        <div class="fw-ad-stat">
            <h3>{$ad.title|fwe} {if !$ad.is_pub}<small>({$LANG.FW_ADS_OFF})</small>{/if}</h3>
            <p>{$LANG.FW_ADS_VIEWS}: <b>{$ad.views_count}</b>{if $ad.max_views} / {$ad.max_views}{/if} &middot;
               {$LANG.FW_ADS_CLICKS}: <b>{$ad.clicks_count}</b>{if $ad.max_clicks} / {$ad.max_clicks}{/if} &middot; CTR: <b>{$ad.ctr}%</b>
               {if $ad.date_start || $ad.date_end}&middot; {$ad.date_start} &mdash; {$ad.date_end}{/if}</p>
            {if $ad.stats}
            <table class="fw-table"><tr><th>{$LANG.FW_DATE}</th><th>{$LANG.FW_ADS_VIEWS}</th><th>{$LANG.FW_ADS_CLICKS}</th></tr>
                {foreach $ad.stats as $s}<tr><td>{$s.date}</td><td>{$s.view}</td><td>{$s.click}</td></tr>{/foreach}
            </table>
            {/if}
        </div>
    {/foreach}
</div>
