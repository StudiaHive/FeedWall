{* feed-wall: форма комментария. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<form class="fw-cform" data-post="{$post.id}" action="#" method="post">
    <input type="hidden" name="parent_id" value="0" />
    <img class="fw-ava-s" src="{$fwme.avatar}" alt="" />
    <div class="fw-cinput">
        <div class="fw-reply-to" style="display:none"></div>
        <textarea name="text" class="fw-textarea fw-mentionable" rows="1" maxlength="{$fwcfg.comm_maxlength}" placeholder="{$LANG.FW_WRITE_COMMENT}"></textarea>
        <div class="fw-cattach"></div>
        <div class="fw-ctools">
            {if $fwcfg.smiles}<a href="#" class="fw-tool" data-act="smiles" title="{$LANG.FW_SMILES}">&#128578;</a>{/if}
            {if $fwme.perms.comment_media}
                <label class="fw-tool" title="{$LANG.FW_ADD_PHOTO}">&#128247;<input type="file" class="fw-file" data-kind="cphoto" accept="image/*" multiple /></label>
                {if $fwcfg.gifs}<a href="#" class="fw-tool" data-act="gif" title="GIF">GIF</a>{/if}
                {if $fwcfg.voice}<a href="#" class="fw-tool fw-voice-btn" data-act="voice" title="{$LANG.FW_VOICE}">&#127908;</a>{/if}
            {/if}
            <button type="submit" class="fw-csend" title="{$LANG.FW_SEND}">&#10148;</button>
        </div>
    </div>
</form>
