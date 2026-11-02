{* feed-wall: форма записи. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-form{if $compact} fw-compact{/if}" id="{$form_id}">
<form class="fw-post-form" action="#" method="post" data-target-type="{$target_type}" data-target-id="{$target_id}">
    {if $is_guest}
        <input type="text" name="guest_name" class="fw-input fw-guest-name" maxlength="50" placeholder="{$LANG.FW_YOUR_NAME}" />
    {/if}
    <div class="fw-form-main">
        {if !$is_guest}<img class="fw-ava" src="{$fwme.avatar}" alt="" />{/if}
        <div class="fw-form-text">
            <textarea name="text" class="fw-textarea fw-mentionable fw-autosize" rows="2" {if $fwcfg.maxlength}maxlength="{$fwcfg.maxlength}"{/if} placeholder="{$placeholder|fwe}"></textarea>
        </div>
    </div>
    <div class="fw-attachments"></div>
    {if $fwme.perms.poll}
    <div class="fw-poll-builder" style="display:none">
        <input type="text" class="fw-input" name="poll_question" maxlength="255" placeholder="{$LANG.FW_POLL_QUESTION}" />
        <div class="fw-poll-answers-edit">
            {section name=pa loop=2}
            <div class="fw-pa">
                <input type="text" class="fw-input" name="poll_answers[]" maxlength="150" placeholder="{$LANG.FW_POLL_ANSWER}" />
                <input type="hidden" name="poll_images[]" value="0" />
                {if $fwcfg.poll_photos}<label class="fw-tool" title="{$LANG.FW_ADD_PHOTO}">&#128247;<input type="file" class="fw-file" data-kind="pollphoto" accept="image/*" /></label>{/if}
            </div>
            {/section}
        </div>
        <a href="#" data-act="poll-add-answer">+ {$LANG.FW_POLL_ADD_ANSWER}</a>
        <label class="fw-check"><input type="checkbox" name="poll_multi" value="1" /> {$LANG.FW_POLL_MULTI}</label>
        <a href="#" class="fw-danger fw-right" data-act="poll-remove">{$LANG.FW_POLL_REMOVE}</a>
    </div>
    {/if}
    {if $fwme.perms.colored && $fwbgs}
    <div class="fw-bgs" title="{$LANG.FW_COLORED}">
        <a href="#" class="fw-bg fw-bg-none active" data-bg="0" title="{$LANG.FW_NO_BG}">&#10005;</a>
        {foreach $fwbgs as $bg}<a href="#" class="fw-bg" data-bg="{$bg.id}" style="{$bg.style|fwe}" title="{$bg.title|fwe}"></a>{/foreach}
        <input type="hidden" name="bg_id" value="0" />
    </div>
    {/if}
    <div class="fw-form-tools">
        {if $fwcfg.smiles}<a href="#" class="fw-tool" data-act="smiles" title="{$LANG.FW_SMILES}">&#128578;</a>{/if}
        {if $fwme.perms.photo}<label class="fw-tool" title="{$LANG.FW_ADD_PHOTO}">&#128247;<input type="file" class="fw-file" data-kind="photo" accept="image/*" multiple /></label>{/if}
        {if $fwme.perms.video}<a href="#" class="fw-tool" data-act="add-video" title="{$LANG.FW_ADD_VIDEO}">&#127916;</a>{/if}
        {if $fwme.perms.audio}<label class="fw-tool" title="{$LANG.FW_ADD_AUDIO}">&#127925;<input type="file" class="fw-file" data-kind="audio" accept="audio/*,.mp3,.ogg,.m4a" multiple /></label>{/if}
        {if $fwme.perms.gif && $fwcfg.gifs}<a href="#" class="fw-tool" data-act="gif" title="GIF">GIF</a>{/if}
        {if $fwme.perms.poll}<a href="#" class="fw-tool" data-act="poll" title="{$LANG.FW_ADD_POLL}">&#128202;</a>{/if}
        {if !$is_guest && $target_type!='club'}<label class="fw-check" title="{$LANG.FW_PRIVATE_HINT}"><input type="checkbox" name="is_private" value="1" /> &#128274; {$LANG.FW_PRIVATE}</label>{/if}
        {if $fwme.perms.anonymous && $target_type=='feed'}<label class="fw-check"><input type="checkbox" name="is_anonymous" value="1" /> &#127917; {$LANG.FW_ANONYMOUSLY}</label>{/if}
        <span class="fw-right">
            {if $fwcfg.maxlength}<span class="fw-counter">{$fwcfg.maxlength}</span>{/if}
            <button type="submit" class="fw-button fw-submit">{$LANG.FW_PUBLISH}</button>
        </span>
    </div>
    {if $captcha}<div class="fw-captcha">{$captcha}</div>{/if}
</form>
</div>
