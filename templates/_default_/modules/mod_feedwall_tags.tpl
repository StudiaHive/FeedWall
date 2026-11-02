{* feed-wall: модуль хэштегов. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-mod fw-mod-tags fw-tagcloud">{foreach $tags as $t}<a href="{$t.url}" class="fw-tag fw-tag-{$t.size}">#{$t.tag|fwe}</a> {/foreach}</div>
