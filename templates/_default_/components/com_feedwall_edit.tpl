{* feed-wall: редактирование записи. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<form class="fw-edit-form" action="#" method="post" data-id="{$post.id}">
    <textarea name="text" class="fw-textarea fw-mentionable fw-autosize" rows="3" {if $fwcfg.maxlength}maxlength="{$fwcfg.maxlength}"{/if}>{$post.text|fwe}</textarea>
    <div class="fw-attachments">
        {foreach $post.photos as $ph}<span class="fw-att fw-att-photo"><img src="{$ph.thumb}" alt="" /><input type="hidden" name="photos[]" value="{$ph.id}" /><a href="#" class="fw-att-del" data-act="att-del">&times;</a></span>{/foreach}
        {foreach $post.audios as $a}<span class="fw-att fw-att-audio">&#127925; {$a.title|fwe}<input type="hidden" name="audios[]" value="{$a.id}" /><a href="#" class="fw-att-del" data-act="att-del">&times;</a></span>{/foreach}
        {foreach $post.videos as $v}<span class="fw-att fw-att-video">&#127916; {$v.provider}<input type="hidden" name="videos[]" value="{$v.url|fwe}" /><a href="#" class="fw-att-del" data-act="att-del">&times;</a></span>{/foreach}
        {if $post.link}<label class="fw-att"><input type="checkbox" name="keep_link" value="1" checked="checked" /> &#128279; {$post.link.title|fwe|truncate:40}</label>{/if}
        {if $post.gif}<label class="fw-att"><input type="checkbox" name="keep_gif" value="1" checked="checked" /> GIF</label>{/if}
    </div>
    {if $fwme.perms.colored && $fwbgs && !$post.photos && !$post.poll}
    <div class="fw-bgs">
        <a href="#" class="fw-bg fw-bg-none{if !$post.bg_id} active{/if}" data-bg="0">&#10005;</a>
        {foreach $fwbgs as $bg}<a href="#" class="fw-bg{if $post.bg_id==$bg.id} active{/if}" data-bg="{$bg.id}" style="{$bg.style|fwe}"></a>{/foreach}
        <input type="hidden" name="bg_id" value="{$post.bg_id}" />
    </div>
    {/if}
    <div class="fw-form-tools">
        {if $fwme.perms.photo}<label class="fw-tool" title="{$LANG.FW_ADD_PHOTO}">&#128247;<input type="file" class="fw-file" data-kind="photo" accept="image/*" multiple /></label>{/if}
        {if $fwme.perms.video}<a href="#" class="fw-tool" data-act="add-video">&#127916;</a>{/if}
        {if $fwme.perms.audio}<label class="fw-tool">&#127925;<input type="file" class="fw-file" data-kind="audio" accept="audio/*" multiple /></label>{/if}
        {if $post.target_type!='club'}<label class="fw-check"><input type="checkbox" name="is_private" value="1"{if $post.is_private} checked="checked"{/if} /> &#128274; {$LANG.FW_PRIVATE}</label>{/if}
        <span class="fw-right">
            <a href="#" data-act="edit-cancel">{$LANG.FW_CANCEL}</a>
            <button type="submit" class="fw-button">{$LANG.FW_SAVE}</button>
        </span>
    </div>
</form>
