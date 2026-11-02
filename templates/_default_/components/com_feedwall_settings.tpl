{* feed-wall: настройки пользователя. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-page fw-settings">
    <h1 class="con_heading">{$LANG.FW_SETTINGS}</h1>
    <form action="/feedwall/settings" method="post">
        <input type="hidden" name="fw_token" value="{$fwtoken}" />
        <input type="hidden" name="save" value="1" />
        <fieldset><legend>{$LANG.FW_PRIVACY}</legend>
        {foreach ['who_write'=>$LANG.FW_WHO_WRITE, 'who_comment'=>$LANG.FW_WHO_COMMENT, 'who_react'=>$LANG.FW_WHO_REACT] as $k => $label}
            <p><label>{$label}</label><br />
            <select name="{$k}">
                <option value="all"{if $ucfg.$k=='all'} selected="selected"{/if}>{$LANG.FW_PRIV_ALL}</option>
                <option value="friends"{if $ucfg.$k=='friends'} selected="selected"{/if}>{$LANG.FW_PRIV_FRIENDS}</option>
                <option value="nobody"{if $ucfg.$k=='nobody'} selected="selected"{/if}>{$LANG.FW_PRIV_NOBODY}</option>
            </select></p>
        {/foreach}
        <p><label><input type="checkbox" name="allow_mentions" value="1"{if $ucfg.allow_mentions} checked="checked"{/if} /> {$LANG.FW_ALLOW_MENTIONS}</label></p>
        <p><label><input type="checkbox" name="in_feed" value="1"{if $ucfg.in_feed} checked="checked"{/if} /> {$LANG.FW_IN_FEED}</label></p>
        </fieldset>
        <fieldset><legend>{$LANG.FW_NOTIFICATIONS}</legend>
        {foreach ['notify_wall'=>$LANG.FW_N_WALL, 'notify_mention'=>$LANG.FW_N_MENTION, 'notify_comment'=>$LANG.FW_N_COMMENT, 'notify_reply'=>$LANG.FW_N_REPLY, 'notify_react'=>$LANG.FW_N_REACT, 'notify_share'=>$LANG.FW_N_SHARE] as $k => $label}
            <p><label><input type="checkbox" name="{$k}" value="1"{if $ucfg.$k} checked="checked"{/if} /> {$label}</label></p>
        {/foreach}
        </fieldset>
        <p><input type="submit" class="fw-button" value="{$LANG.FW_SAVE}" /></p>
    </form>
    <h3>{$LANG.FW_HIDDEN_LIST}</h3>
    {if $hidden}
        <ul class="fw-hidden-list">
        {foreach $hidden as $h}
            <li id="fw-hidden-{$h.id}">{if $h.user}{$LANG.FW_AUTHOR}: <a href="{$h.user.url}">{$h.user.nickname|fwe}</a>{else}{$LANG.FW_POST} <a href="/feedwall/post/{$h.post_id}">#{$h.post_id}</a>{/if}
                &middot; <a href="#" class="fw-unhide" data-id="{$h.id}">{$LANG.FW_UNHIDE}</a></li>
        {/foreach}
        </ul>
    {else}<p class="fw-empty">{$LANG.FW_HIDDEN_EMPTY}</p>{/if}
    {if $ads}<p><a href="/feedwall/ads">{$LANG.FW_ADS_STATS}</a></p>{/if}
</div>
