{* feed-wall: вложения. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
{if $p.photos}
    <div class="fw-photos fw-photos-{if $p.photos_count > 4}5{else}{$p.photos_count}{/if}">
        {foreach $p.photos as $ph}<a href="{$ph.file}" class="fw-photo" rel="fwg{$p.id}"><img src="{$ph.thumb}" alt="" loading="lazy" /></a>{/foreach}
    </div>
{/if}
{foreach $p.videos as $v}
    <div class="fw-video" data-embed="{$v.embed|fwe}" data-provider="{$v.provider}">
        {if $v.thumb}<img src="{$v.thumb|fwe}" alt="" loading="lazy" />{/if}
        <span class="fw-play">&#9654;</span><span class="fw-vprov">{$v.provider}</span>
        {if $v.title}<div class="fw-vtitle">{$v.title|fwe}</div>{/if}
    </div>
{/foreach}
{if $p.audios}
    <div class="fw-audios">
    {foreach $p.audios as $a}
        <div class="fw-audio" data-id="{$a.id}">
            <div class="fw-atitle">&#127925; {if $a.artist}<b>{$a.artist|fwe}</b> &mdash; {/if}{$a.title|fwe}</div>
            <audio controls preload="none" src="{$a.file}"></audio>
        </div>
    {/foreach}
    </div>
{/if}
{if $p.gif}<div class="fw-gif"><img src="{$p.gif.url|fwe}" alt="GIF" loading="lazy" /></div>{/if}
{if $p.link}
    <a class="fw-link" href="{$p.link.url|fwe}" target="_blank" rel="nofollow noopener">
        {if $p.link.image}<span class="fw-link-img"><img src="{$p.link.image|fwe}" alt="" loading="lazy" /></span>{/if}
        <span class="fw-link-info">
            <b>{if $p.link.title}{$p.link.title|fwe}{else}{$p.link.url|fwe}{/if}</b>
            {if $p.link.desc}<span class="fw-link-desc">{$p.link.desc|fwe}</span>{/if}
            <small>{if $p.link.site}{$p.link.site|fwe}{else}{$p.link.url|fwe|truncate:60}{/if}</small>
        </span>
    </a>
{/if}
{if $p.poll}{include file='com_feedwall_poll.tpl' poll=$p.poll post=$p}{/if}
