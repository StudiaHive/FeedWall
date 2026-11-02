{* feed-wall: опрос. (c) StudiaHive / InstantCMS community revival, GPL v2 *}
<div class="fw-poll{if $poll.is_photo} fw-poll-photo{/if}" data-poll="{$poll.id}" data-multi="{$poll.is_multi}">
    <div class="fw-poll-q">&#128202; {$poll.question|fwe}</div>
    {if $poll.is_multi}<div class="fw-poll-hint">{$LANG.FW_POLL_MULTI_HINT}</div>{/if}
    <div class="fw-poll-answers">
    {foreach $poll.answers as $a}
        {if $poll.show_results}
            <div class="fw-poll-res{if $a.is_my} fw-my{/if}">
                {if $a.photo}<a href="{$a.photo.file}" class="fw-photo fw-poll-img" rel="fwp{$poll.id}"><img src="{$a.photo.thumb}" alt="" /></a>{/if}
                <div class="fw-poll-line">
                    <div class="fw-poll-fill" style="width:{$a.percent}%"></div>
                    <span class="fw-poll-title">{if $a.is_my}&#10004; {/if}{$a.title|fwe}</span>
                    <b class="fw-poll-pc">{$a.percent}%</b>
                </div>
                <a href="#" class="fw-poll-votes" data-act="voters" data-answer="{$a.id}" title="{$LANG.FW_VOTERS}">{$a.votes}</a>
            </div>
        {else}
            <label class="fw-poll-opt">
                <input type="{if $poll.is_multi}checkbox{else}radio{/if}" name="fwp{$poll.id}" value="{$a.id}" />
                {if $a.photo}<img class="fw-poll-img" src="{$a.photo.thumb}" alt="" />{/if}
                <span>{$a.title|fwe}</span>
            </label>
        {/if}
    {/foreach}
    </div>
    {if $poll.can_vote}<button type="button" class="fw-button fw-vote-btn" data-act="vote">{$LANG.FW_VOTE}</button>{/if}
    <div class="fw-poll-foot">
        {$LANG.FW_VOTED}: <a href="#" data-act="voters" data-answer="0">{$poll.voters_count}</a>
        {if !$fwme.id} &middot; <a href="/login">{$LANG.FW_POLL_LOGIN}</a>{/if}
    </div>
</div>
