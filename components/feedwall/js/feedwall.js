/*
 * feed-wall 1.0.0 — лента и стена для InstantCMS 1.11
 * (c) StudiaHive / InstantCMS community revival, GPL v2
 */
(function($){
'use strict';

var C = window.FWCFG || {lang:{}, smiles:{}};
var L = function(k){ return (C.lang && C.lang[k]) ? C.lang[k] : k; };
var EMOJI = ['😀','😂','🤣','😊','😍','😘','😎','🤔','😉','🙂','😇','🥳','😢','😭','😡','😱','👍','👎','👏','🙏','💪','🔥','❤️','💔','🎉','✨','⭐','✅','❌','⚡','☀️','🌹','🍀','🎁','☕','🍕','⚽','🎵','💡','💯'];
var FW = window.FeedWall = {};

// ------------------------------------------------------------------ AJAX
FW.api = function(action, data, ok, fail){
    var opts = {
        url: '/feedwall/ajax/' + action,
        type: 'POST',
        dataType: 'json',
        headers: {'X-FW-Token': C.token || ''},
        data: data || {}
    };
    if (window.FormData && data instanceof FormData){ opts.processData = false; opts.contentType = false; }
    return $.ajax(opts).done(function(r){
        if (!r || r.error){
            if (fail && fail(r) === false) { return; }
            FW.toast(r && r.message ? r.message : L('FW_ERROR'), true);
            return;
        }
        if (ok) { ok(r); }
    }).fail(function(){
        if (fail && fail(null) === false) { return; }
        FW.toast(L('FW_ERROR'), true);
    });
};

// ------------------------------------------------------------------ уведомления, окна
FW.toast = function(msg, isErr){
    if (!msg) { return; }
    var $t = $('<div class="fw-toast"></div>').toggleClass('fw-toast-err', !!isErr).text(msg).appendTo('body');
    setTimeout(function(){ $t.addClass('fw-show'); }, 10);
    setTimeout(function(){ $t.removeClass('fw-show'); setTimeout(function(){ $t.remove(); }, 400); }, 2800);
};

FW.modal = function(html, title){
    FW.closeModal();
    var $m = $('<div class="fw-modal-bg"><div class="fw-modal"><a href="#" class="fw-modal-x">&times;</a><div class="fw-modal-title"></div><div class="fw-modal-body"></div></div></div>');
    if (title) { $m.find('.fw-modal-title').text(title); } else { $m.find('.fw-modal-title').remove(); }
    $m.find('.fw-modal-body').html(html);
    $m.appendTo('body');
    return $m;
};
FW.closeModal = function(){ $('.fw-modal-bg').remove(); };
$(document).on('click', '.fw-modal-x', function(e){ e.preventDefault(); FW.closeModal(); });
$(document).on('click', '.fw-modal-bg', function(e){ if (e.target === this) { FW.closeModal(); } });
$(document).on('keyup', function(e){ if (e.keyCode === 27) { FW.closeModal(); $('.fw-pop').remove(); } });

FW.confirm = function(msg, cb){ if (window.confirm(msg)) { cb(); } };

function postOf(el){ return $(el).closest('.fw-post'); }
function postId(el){ return postOf(el).data('id'); }
function commentOf(el){ return $(el).closest('.fw-comment'); }

// ------------------------------------------------------------------ инициализация фрагмента
FW.init = function(ctx){
    var $ctx = $(ctx || document);
    // сворачивание длинного текста
    if (C.cut_height){
        $ctx.find('.fw-cut').each(function(){
            var $t = $(this);
            if ($t.data('cutdone')) { return; }
            $t.data('cutdone', 1);
            if (this.scrollHeight > C.cut_height + 40){
                $t.addClass('fw-cut-on').css('max-height', C.cut_height + 'px');
                $('<a href="#" class="fw-cut-more"></a>').text(L('FW_SHOW_MORE')).insertAfter($t);
            }
        });
    }
    // фотогалереи
    if ($.fn.colorbox){
        $ctx.find('a.fw-photo').each(function(){
            if ($(this).data('cbdone')) { return; }
            $(this).data('cbdone', 1).colorbox({photo: true, maxWidth: '95%', maxHeight: '95%', current: '{current} / {total}'});
        });
    }
    // просмотры
    if (C.views) { $ctx.find('.fw-post[data-id]').each(function(){ FW.watchView(this); }); }
};

$(document).on('click', '.fw-cut-more', function(e){
    e.preventDefault();
    $(this).prev('.fw-cut').removeClass('fw-cut-on').css('max-height', '');
    $(this).remove();
});

// ------------------------------------------------------------------ просмотры
var viewQueue = [], viewSent = {}, viewTimer = null, observer = null;
FW.watchView = function(el){
    var id = $(el).data('id');
    if (!id || viewSent[id]) { return; }
    if (!observer && window.IntersectionObserver){
        observer = new IntersectionObserver(function(entries){
            $.each(entries, function(i, en){
                if (en.isIntersecting){
                    var pid = $(en.target).data('id');
                    observer.unobserve(en.target);
                    if (pid && !viewSent[pid]) { viewSent[pid] = 1; viewQueue.push(pid); }
                }
            });
            if (viewQueue.length && !viewTimer) { viewTimer = setTimeout(flushViews, 2000); }
        }, {threshold: 0.5});
    }
    if (observer) { observer.observe(el); }
};
function flushViews(){
    viewTimer = null;
    if (!viewQueue.length) { return; }
    var ids = viewQueue.splice(0, 50);
    $.ajax({url: '/feedwall/ajax/views', type: 'POST', dataType: 'json', headers: {'X-FW-Token': C.token}, data: {ids: ids}});
}

// ------------------------------------------------------------------ подгрузка ленты
FW.loadMore = function($posts){
    if ($posts.data('loading') || String($posts.attr('data-more')) !== '1') { return; }
    var ctx = {};
    try { ctx = JSON.parse($posts.attr('data-ctx') || '{}'); } catch(err){ ctx = {}; }
    var page = parseInt($posts.attr('data-page'), 10) + 1;
    ctx.page = page;
    $posts.data('loading', 1);
    var $btn = $posts.siblings('.fw-more-wrap').find('.fw-more').text(L('FW_LOADING'));
    FW.api('more', ctx, function(r){
        var $html = $('<div></div>').html(r.html);
        $html.children().each(function(){
            var id = $(this).data('id');
            if (id && $posts.find('#fw-post-' + id).length) { $(this).remove(); }
        });
        var $new = $html.children();
        $posts.append($new);
        FW.init($new);
        $posts.attr('data-page', page).attr('data-more', r.has_more ? '1' : '0');
        if (!r.has_more) { $posts.siblings('.fw-more-wrap').remove(); }
        $btn.text(L('FW_SHOW_MORE'));
    }).always(function(){ $posts.data('loading', 0); });
};
$(document).on('click', '.fw-more', function(e){
    e.preventDefault();
    FW.loadMore($(this).closest('.fw-more-wrap').siblings('.fw-posts'));
});
$(window).on('scroll', function(){
    if (!C.infinite || C.infinite_type !== 'scroll') { return; }
    $('.fw-posts[data-more="1"]').each(function(){
        var $p = $(this);
        if ($(window).scrollTop() + $(window).height() > $p.offset().top + $p.outerHeight() - 400) { FW.loadMore($p); }
    });
});
if (C.infinite){ $(function(){ $('.fw-pagebar, .fw-index .pagebar').toggle(C.infinite_type !== 'scroll'); }); }

// ------------------------------------------------------------------ меню записи
$(document).on('click', '.fw-menu-btn', function(e){
    e.preventDefault(); e.stopPropagation();
    var $m = $(this).closest('.fw-menu');
    $('.fw-menu.open').not($m).removeClass('open');
    $m.toggleClass('open');
});
$(document).on('click', function(e){
    if (!$(e.target).closest('.fw-menu').length) { $('.fw-menu.open').removeClass('open'); }
    if (!$(e.target).closest('.fw-pop, [data-act="smiles"], [data-act="gif"], .fw-mention-list').length) { $('.fw-pop').remove(); }
});
$(document).on('click', '.fw-menu-list a', function(){ $(this).closest('.fw-menu').removeClass('open'); });

// ------------------------------------------------------------------ действия с записью
function absUrl(path){ return window.location.protocol + '//' + window.location.host + path; }

$(document).on('click', '[data-act="copy-link"]', function(e){
    e.preventDefault();
    var url = absUrl('/feedwall/post/' + postId(this));
    if (navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(url).then(function(){ FW.toast(L('FW_LINK_COPIED')); }, function(){ window.prompt('', url); });
    } else { window.prompt('', url); }
});

$(document).on('click', '.fw-post [data-act="delete"]', function(e){
    e.preventDefault();
    var $p = postOf(this);
    FW.confirm(L('FW_CONFIRM_DELETE'), function(){
        FW.api('delete', {post_id: $p.data('id')}, function(r){
            $p.slideUp(200, function(){ $p.remove(); });
            FW.toast(r.message);
            if ($('.fw-view').length) { setTimeout(function(){ window.location.href = '/feedwall'; }, 800); }
        });
    });
});

$(document).on('click', '.fw-post [data-act="pin"], .fw-post [data-act="pin_feed"]', function(e){
    e.preventDefault();
    var act = $(this).data('act');
    FW.api(act, {post_id: postId(this)}, function(r){
        FW.toast(r.message);
        setTimeout(function(){ window.location.reload(); }, 700);
    });
});

$(document).on('click', '.fw-post [data-act="hide"], .fw-post [data-act="hide_author"]', function(e){
    e.preventDefault();
    var $p = postOf(this), act = $(this).data('act');
    FW.api(act, {post_id: $p.data('id')}, function(r){
        FW.toast(r.message);
        if (act === 'hide_author' && r.author_id) {
            $('.fw-post[data-author="' + parseInt(r.author_id, 10) + '"]').slideUp(200, function(){ $(this).remove(); });
        } else { $p.slideUp(200, function(){ $p.remove(); }); }
    });
});

$(document).on('click', '.fw-unhide', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    FW.api('unhide', {id: id}, function(){ $('#fw-hidden-' + parseInt(id, 10)).remove(); });
});

$(document).on('click', '[data-act="complain"], [data-act="comment_complain"]', function(e){
    e.preventDefault();
    var data = {post_id: postId(this)};
    if ($(this).data('act') === 'comment_complain') { data.comment_id = commentOf(this).data('id'); }
    var reason = window.prompt(L('FW_COMPLAIN_PROMPT'), '');
    if (reason === null) { return; }
    data.reason = reason;
    FW.api('complain', data, function(r){ FW.toast(r.message); });
});

// редактирование записи
$(document).on('click', '.fw-post [data-act="edit"]', function(e){
    e.preventDefault();
    var $p = postOf(this);
    FW.api('edit_form', {post_id: $p.data('id')}, function(r){
        var $body = $p.children('.fw-post-body');
        $body.data('orig', $body.html()).html(r.html);
        FW.initForm($body.find('form'));
    });
});
$(document).on('click', '[data-act="edit-cancel"]', function(e){
    e.preventDefault();
    var $body = $(this).closest('.fw-post-body');
    $body.html($body.data('orig'));
    FW.init($body);
});
$(document).on('submit', '.fw-edit-form', function(e){
    e.preventDefault();
    var $f = $(this), $p = postOf($f);
    var data = $f.serialize() + '&post_id=' + encodeURIComponent($p.data('id'));
    FW.api('edit_save', data, function(r){
        var $n = $(r.html);
        $p.replaceWith($n);
        FW.init($n);
    });
});

// реакции
$(document).on('click', '.fw-bar [data-act="react"]', function(e){
    e.preventDefault();
    var $bar = $(this).closest('.fw-bar');
    if (!C.user_id) { window.location.href = '/login'; return; }
    FW.api('react', {post_id: $bar.data('id'), reaction: $(this).data('reaction')}, function(r){
        if (r.html){ var $n = $(r.html); $bar.replaceWith($n); }
    });
});
$(document).on('click', '[data-act="reactors"]', function(e){
    e.preventDefault();
    FW.api('reactors', {post_id: postId(this)}, function(r){ FW.modal(r.html); });
});
$(document).on('click', '[data-act="comment_reactors"]', function(e){
    e.preventDefault();
    FW.api('reactors', {post_id: postId(this), comment_id: commentOf(this).data('id')}, function(r){ FW.modal(r.html); });
});

// репост и соцсети
$(document).on('click', '.fw-bar [data-act="share"]', function(e){
    e.preventDefault();
    var id = postId(this), $bar = $(this).closest('.fw-bar');
    var $f = $('<form class="fw-share-form"><textarea class="fw-textarea" name="text" rows="3" maxlength="1000"></textarea>' +
        '<label class="fw-radio"><input type="radio" name="mode" value="feed" checked="checked"> <span class="m1"></span></label>' +
        '<label class="fw-radio"><input type="radio" name="mode" value="wall"> <span class="m2"></span></label>' +
        '<label class="fw-radio"><input type="radio" name="mode" value="status"> <span class="m3"></span></label>' +
        '<div class="fw-right"><button type="submit" class="fw-button"></button></div></form>');
    $f.find('textarea').attr('placeholder', L('FW_SHARE_TEXT'));
    $f.find('.m1').text(L('FW_SHARE_FEED')); $f.find('.m2').text(L('FW_SHARE_WALL')); $f.find('.m3').text(L('FW_SHARE_STATUS'));
    $f.find('button').text(L('FW_SHARE'));
    var $m = FW.modal($f, L('FW_SHARE'));
    $m.find('form').on('submit', function(ev){
        ev.preventDefault();
        FW.api('share', {post_id: id, text: $(this).find('textarea').val(), mode: $(this).find('input[name=mode]:checked').val()}, function(r){
            FW.closeModal(); FW.toast(r.message);
        });
    });
});
$(document).on('click', '.fw-sbtn', function(e){
    e.preventDefault();
    var url = encodeURIComponent(absUrl('/feedwall/post/' + postId(this)));
    var t = encodeURIComponent($.trim(postOf(this).find('.fw-text').first().text()).substr(0, 200));
    var map = {
        vk: 'https://vk.com/share.php?url=' + url,
        tg: 'https://t.me/share/url?url=' + url + '&text=' + t,
        x:  'https://twitter.com/intent/tweet?url=' + url + '&text=' + t,
        fb: 'https://www.facebook.com/sharer/sharer.php?u=' + url
    };
    var net = $(this).data('net');
    if (map[net]) { window.open(map[net], 'fwshare', 'width=640,height=480,noopener'); }
});

// опросы
$(document).on('click', '[data-act="vote"]', function(e){
    e.preventDefault();
    var $poll = $(this).closest('.fw-poll');
    var answers = $poll.find('input:checked').map(function(){ return this.value; }).get();
    if (!answers.length) { return; }
    FW.api('vote', {poll_id: $poll.data('poll'), post_id: postId(this), answers: answers}, function(r){
        var $n = $(r.html); $poll.replaceWith($n); FW.init($n);
    });
});
$(document).on('click', '[data-act="voters"]', function(e){
    e.preventDefault();
    var $poll = $(this).closest('.fw-poll');
    FW.api('voters', {poll_id: $poll.data('poll'), post_id: postId(this), answer_id: $(this).data('answer')}, function(r){ FW.modal(r.html); });
});

// видео: превью → плеер
var VIDEO_HOSTS = /^https:\/\/(www\.youtube\.com|rutube\.ru|player\.vimeo\.com|vk\.com|www\.facebook\.com)\//;
$(document).on('click', '.fw-video', function(e){
    var embed = String($(this).data('embed') || '');
    if (!VIDEO_HOSTS.test(embed) || $(this).find('iframe').length) { return; }
    e.preventDefault();
    var src = embed + (embed.indexOf('?') > -1 ? '&' : '?') + 'autoplay=1';
    var $if = $('<iframe allowfullscreen="allowfullscreen" frameborder="0" allow="autoplay; encrypted-media; fullscreen"></iframe>').attr('src', src);
    $(this).addClass('fw-playing').empty().append($if);
});

// счётчик прослушиваний
var played = {};
document.addEventListener('play', function(e){
    var $a = $(e.target).closest('.fw-audio');
    if (!$a.length) { return; }
    var id = $a.data('id');
    if (!id || played[id]) { return; }
    played[id] = 1;
    $.ajax({url: '/feedwall/ajax/audio_play', type: 'POST', dataType: 'json', headers: {'X-FW-Token': C.token}, data: {id: id}});
    $('audio').not(e.target).each(function(){ this.pause(); });
}, true);

// ------------------------------------------------------------------ комментарии
$(document).on('click', '[data-act="comment-focus"]', function(e){
    e.preventDefault();
    var $p = postOf(this), $ta = $p.find('.fw-cform textarea').first();
    if ($ta.length) { $ta.focus(); }
    else if (!C.user_id) { window.location.href = '/login'; }
});
$(document).on('click', '[data-act="all-comments"]', function(e){
    e.preventDefault();
    var $a = $(this), $p = postOf(this);
    FW.api('comments', {post_id: $p.data('id')}, function(r){
        var $list = $p.find('.fw-comments-list').html(r.html);
        $a.remove(); FW.init($list);
    });
});
$(document).on('click', '[data-act="answers"]', function(e){
    e.preventDefault();
    var $a = $(this), $c = commentOf(this);
    FW.api('comments', {post_id: $c.data('post'), parent_id: $c.data('id')}, function(r){
        var $box = $c.find('.fw-answers').first().html(r.html);
        $a.remove(); FW.init($box);
    });
});
$(document).on('click', '[data-act="reply"]', function(e){
    e.preventDefault();
    var $c = commentOf(this), $p = postOf(this), $f = $p.find('.fw-cform').first();
    if (!$f.length) { return; }
    $f.find('input[name=parent_id]').val($c.data('root'));
    var login = $c.data('login');
    var $ta = $f.find('textarea');
    if (login && $ta.val().indexOf('@' + login) === -1) { $ta.val('@' + login + ' ' + $ta.val()); }
    var name = $.trim($c.find('.fw-chead .fw-name').first().text());
    $f.find('.fw-reply-to').empty().append($('<span></span>').text(L('FW_ANSWER') + ': ' + name))
        .append(' <a href="#" class="fw-reply-cancel">&times;</a>').show();
    $ta.focus();
});
$(document).on('click', '.fw-reply-cancel', function(e){
    e.preventDefault();
    var $f = $(this).closest('.fw-cform');
    $f.find('input[name=parent_id]').val(0);
    $f.find('.fw-reply-to').hide().empty();
});
$(document).on('keydown', '.fw-cform textarea', function(e){
    if (e.keyCode === 13 && !e.shiftKey && !$('.fw-mention-list').length){ e.preventDefault(); $(this).closest('form').submit(); }
});
$(document).on('submit', '.fw-cform', function(e){
    e.preventDefault();
    var $f = $(this), $p = postOf($f);
    if ($f.data('busy')) { return; }
    $f.data('busy', 1);
    var data = $f.serialize() + '&post_id=' + encodeURIComponent($p.data('id'));
    FW.api('comment_add', data, function(r){
        var $n = $(r.html);
        if (r.parent_id){
            $p.find('#fw-comment-' + parseInt(r.parent_id, 10) + ' > .fw-cbody > .fw-answers').append($n);
        } else {
            $p.find('.fw-comments-list').append($n);
        }
        FW.init($n);
        $f[0].reset();
        $f.find('input[name=parent_id]').val(0);
        $f.find('.fw-reply-to').hide().empty();
        $f.find('.fw-cattach').empty();
        $f.find('textarea').css('height', '');
    }).always(function(){ $f.data('busy', 0); });
});
$(document).on('click', '[data-act="comment_like"]', function(e){
    e.preventDefault();
    var $a = $(this), $c = commentOf(this);
    FW.api('comment_like', {comment_id: $c.data('id')}, function(r){
        $a.toggleClass('active', !!r.reaction).find('span').text(r.count > 0 ? r.count : '');
    });
});
$(document).on('click', '[data-act="comment_delete"]', function(e){
    e.preventDefault();
    var $c = commentOf(this);
    FW.confirm(L('FW_CONFIRM_DELETE_COMMENT'), function(){
        FW.api('comment_delete', {comment_id: $c.data('id')}, function(){ $c.slideUp(150, function(){ $c.remove(); }); });
    });
});
$(document).on('click', '[data-act="comment_edit"]', function(e){
    e.preventDefault();
    var $c = commentOf(this), $t = $c.find('.fw-ctext').first();
    if ($t.find('textarea').length) { return; }
    var $ta = $('<textarea class="fw-textarea" rows="2"></textarea>').val(typeof $t.attr('data-raw') === 'string' ? $t.attr('data-raw') : $t.text());
    var $save = $('<button type="button" class="fw-button fw-small"></button>').text(L('FW_SAVE'));
    var $cancel = $('<a href="#" class="fw-small"></a>').text(L('FW_CANCEL'));
    var orig = $t.html();
    $t.empty().append($ta, $save, ' ', $cancel);
    $cancel.on('click', function(ev){ ev.preventDefault(); $t.html(orig); });
    $save.on('click', function(){
        var val = $ta.val();
        FW.api('comment_edit', {comment_id: $c.data('id'), text: val}, function(r){ $t.html(r.html).attr('data-raw', val); });
    });
});

// ------------------------------------------------------------------ форма записи
FW.initForm = function($f){
    $f.find('.fw-autosize').each(function(){ autosize(this); });
};
function autosize(el){ el.style.height = 'auto'; el.style.height = Math.min(600, el.scrollHeight + 2) + 'px'; }
$(document).on('input', '.fw-autosize, .fw-cform textarea', function(){ autosize(this); });

$(document).on('input', '.fw-post-form textarea[name=text]', function(){
    var $f = $(this).closest('form');
    if (C.maxlength) { $f.find('.fw-counter').text(C.maxlength - this.value.length).toggleClass('fw-over', this.value.length > C.maxlength); }
    applyBg($f);
    if (C.link_preview) { schedulePreview($f, this.value); }
});

// цветной фон
function applyBg($f){
    var $ta = $f.find('textarea[name=text]'), $sel = $f.find('.fw-bg.active');
    var tooLong = $ta.val().length > (C.colored_max || 200);
    var blocked = tooLong || $f.find('.fw-attachments .fw-att').length > 0 || $f.find('.fw-poll-builder:visible').length > 0;
    $f.find('.fw-bgs').toggleClass('fw-disabled', blocked);
    if (blocked || !$sel.length || String($sel.data('bg')) === '0'){
        $ta.removeClass('fw-colored').attr('style', function(){ return ($ta.attr('style') || '').replace(/background[^;]*;?|color[^;]*;?/g, ''); });
        $f.find('input[name=bg_id]').val(blocked ? 0 : $f.find('input[name=bg_id]').val());
        return;
    }
    $ta.addClass('fw-colored').attr('style', $sel.attr('style'));
}
$(document).on('click', '.fw-bg', function(e){
    e.preventDefault();
    var $f = $(this).closest('form');
    if ($f.find('.fw-bgs').hasClass('fw-disabled')) { return; }
    $f.find('.fw-bg').removeClass('active'); $(this).addClass('active');
    $f.find('input[name=bg_id]').val($(this).data('bg'));
    applyBg($f);
});

// опрос
$(document).on('click', '.fw-post-form [data-act="poll"]', function(e){
    e.preventDefault();
    var $f = $(this).closest('form'); $f.find('.fw-poll-builder').toggle(); applyBg($f);
    $f.find('input[name=poll_question]').focus();
});
$(document).on('click', '[data-act="poll-remove"]', function(e){
    e.preventDefault();
    var $b = $(this).closest('.fw-poll-builder');
    $b.find('input[type=text]').val(''); $b.find('input[name="poll_images[]"]').val(0); $b.find('.fw-pa-img').remove();
    $b.hide(); applyBg($b.closest('form'));
});
$(document).on('click', '[data-act="poll-add-answer"]', function(e){
    e.preventDefault();
    var $list = $(this).siblings('.fw-poll-answers-edit');
    if ($list.children().length >= 10) { return; }
    var $n = $list.children().first().clone();
    $n.find('input[type=text]').val(''); $n.find('input[type=hidden]').val(0); $n.find('.fw-pa-img').remove();
    $list.append($n); $n.find('input[type=text]').focus();
});

// отправка записи
$(document).on('submit', '.fw-post-form', function(e){
    e.preventDefault();
    var $f = $(this);
    if ($f.data('busy')) { return; }
    var $btn = $f.find('.fw-submit').prop('disabled', true);
    $f.data('busy', 1);
    var data = $f.serialize() + '&target_type=' + encodeURIComponent($f.data('target-type')) + '&target_id=' + encodeURIComponent($f.data('target-id'));
    FW.api('add', data, function(r){
        var $wrap = $f.closest('.fw-page, .fw-wall');
        var $posts = $wrap.find('.fw-posts').first();
        if ($posts.length && r.html){
            var $n = $(r.html);
            var $pinned = $posts.children('.fw-pinned').last();
            if ($pinned.length) { $n.insertAfter($pinned); } else { $posts.prepend($n); }
            $wrap.find('.fw-empty').remove();
            FW.init($n);
        }
        FW.toast(r.message);
        resetForm($f);
    }, function(){ reloadCaptcha($f); }).always(function(){ $f.data('busy', 0); $btn.prop('disabled', false); });
});
function resetForm($f){
    $f[0].reset();
    $f.find('.fw-attachments').empty();
    $f.find('.fw-poll-builder').hide().find('.fw-pa-img').remove();
    $f.find('input[name="poll_images[]"]').val(0);
    $f.find('.fw-bg').removeClass('active').filter('[data-bg="0"]').addClass('active');
    $f.find('input[name=bg_id]').val(0);
    $f.removeData('linkUrl').removeData('linkSkip');
    applyBg($f);
    $f.find('textarea').css('height', '');
    if (C.maxlength) { $f.find('.fw-counter').text(C.maxlength); }
    reloadCaptcha($f);
}
function reloadCaptcha($f){
    $f.find('.fw-captcha img').each(function(){
        var src = $(this).attr('src').replace(/[?&]fwr=\d+/, '');
        $(this).attr('src', src + (src.indexOf('?') > -1 ? '&' : '?') + 'fwr=' + new Date().getTime());
    });
    $f.find('.fw-captcha input[type=text]').val('');
}

// вложения: служебные функции
function attBox($f){ return $f.is('.fw-cform') ? $f.find('.fw-cattach') : $f.find('.fw-attachments'); }
function addAtt($f, $el){ attBox($f).append($el); applyBg($f); return $el; }
$(document).on('click', '[data-act="att-del"]', function(e){
    e.preventDefault();
    var $f = $(this).closest('form'), $a = $(this).closest('.fw-att');
    if ($a.hasClass('fw-att-link')) { $f.data('linkSkip', $f.data('linkUrl')); }
    $a.remove(); applyBg($f);
});
function attPhoto(name, m){
    return $('<span class="fw-att fw-att-photo"></span>')
        .append($('<img alt="">').attr('src', m.thumb || m.file))
        .append($('<input type="hidden">').attr('name', name).val(m.id))
        .append('<a href="#" class="fw-att-del" data-act="att-del">&times;</a>');
}
function attAudio(m){
    return $('<span class="fw-att fw-att-audio"></span>').text('\uD83C\uDFB5 ' + (m.artist ? m.artist + ' — ' : '') + m.title)
        .append($('<input type="hidden" name="audios[]">').val(m.id))
        .append('<a href="#" class="fw-att-del" data-act="att-del">&times;</a>');
}
function attVideo(v){
    var $a = $('<span class="fw-att fw-att-video"></span>');
    if (v.thumb) { $a.append($('<img alt="">').attr('src', v.thumb)); }
    $a.append($('<span></span>').text('\uD83C\uDFAC ' + v.provider));
    $a.append($('<input type="hidden" name="videos[]">').val(v.url));
    $a.append('<a href="#" class="fw-att-del" data-act="att-del">&times;</a>');
    return $a;
}

// загрузка файлов
$(document).on('change', '.fw-file', function(){
    var input = this, kind = $(this).data('kind'), $f = $(this).closest('form');
    var files = Array.prototype.slice.call(this.files || []);
    if (!files.length) { return; }
    if (kind === 'photo' && $f.find('.fw-attachments .fw-att-photo').length + files.length > C.photos_max){
        FW.toast(L('FW_MAX_FILES').replace('%d', C.photos_max), true);
        files = files.slice(0, Math.max(0, C.photos_max - $f.find('.fw-attachments .fw-att-photo').length));
    }
    if (kind === 'cphoto') { files = files.slice(0, 4); }
    if (kind === 'pollphoto') { files = files.slice(0, 1); }
    $.each(files, function(i, file){
        var fd = new FormData();
        fd.append('type', kind === 'audio' ? 'audio' : 'photo');
        fd.append('file', file);
        var $ph = addAtt($f, $('<span class="fw-att fw-att-loading"></span>').text(L('FW_UPLOADING')));
        if (kind === 'pollphoto') { $ph.remove(); FW.toast(L('FW_UPLOADING')); }
        FW.api('upload', fd, function(r){
            $ph.remove();
            var m = r.media;
            if (kind === 'photo') { addAtt($f, attPhoto('photos[]', m)); }
            else if (kind === 'cphoto') { addAtt($f, attPhoto('photos[]', m)); }
            else if (kind === 'audio') { addAtt($f, attAudio(m)); }
            else if (kind === 'pollphoto'){
                var $pa = $(input).closest('.fw-pa');
                $pa.find('input[name="poll_images[]"]').val(m.id);
                $pa.find('.fw-pa-img').remove();
                $pa.append($('<img class="fw-pa-img" alt="">').attr('src', m.thumb || m.file));
            }
        }, function(){ $ph.remove(); });
    });
    input.value = '';
});

// видео по ссылке
$(document).on('click', '[data-act="add-video"]', function(e){
    e.preventDefault();
    var $f = $(this).closest('form');
    if ($f.find('.fw-att-video').length >= C.video_max) { FW.toast(L('FW_MAX_FILES').replace('%d', C.video_max), true); return; }
    var url = window.prompt('YouTube / RuTube / Vimeo / VK / Facebook URL', '');
    if (!url) { return; }
    FW.api('video_parse', {url: url}, function(r){ addAtt($f, attVideo(r.video)); });
});

// превью ссылки
var previewTimer = null;
function schedulePreview($f, text){
    clearTimeout(previewTimer);
    previewTimer = setTimeout(function(){
        var m = String(text).match(/https?:\/\/[^\s<>"']{4,}/i);
        if (!m) { return; }
        var url = m[0];
        if ($f.data('linkUrl') === url || $f.data('linkSkip') === url) { return; }
        if ($f.find('.fw-att-link').length) { return; }
        $f.data('linkUrl', url);
        FW.api('link_preview', {url: url}, function(r){
            if (r.video){
                if ($f.find('.fw-att-video input[value="' + url.replace(/"/g, '') + '"]').length) { return; }
                if ($f.find('.fw-att-video').length < C.video_max) { addAtt($f, attVideo(r.video)); }
                return;
            }
            if (!r.link) { return; }
            var l = r.link, $a = $('<span class="fw-att fw-att-link"></span>');
            if (l.image) { $a.append($('<img alt="">').attr('src', l.image)); }
            $a.append($('<span class="fw-att-ltext"></span>').append($('<b></b>').text(l.title || l.url)).append($('<small></small>').text(l.site || '')));
            $.each(['url','title','desc','image','site'], function(i, k){ $a.append($('<input type="hidden">').attr('name', 'link[' + k + ']').val(l[k] || '')); });
            $a.append('<a href="#" class="fw-att-del" data-act="att-del">&times;</a>');
            addAtt($f, $a);
        }, function(){ return false; });
    }, 900);
}

// ------------------------------------------------------------------ вставка в текст
function insertAtCaret(ta, text){
    var s = ta.selectionStart, e = ta.selectionEnd, v = ta.value;
    if (typeof s !== 'number') { ta.value += text; return; }
    ta.value = v.substring(0, s) + text + v.substring(e);
    ta.selectionStart = ta.selectionEnd = s + text.length;
    $(ta).trigger('input').focus();
}
function popNear($btn, cls){
    $('.fw-pop').remove();
    var $pop = $('<div class="fw-pop"></div>').addClass(cls).appendTo('body');
    var o = $btn.offset();
    var left = Math.max(5, Math.min(o.left, $(window).width() - 330));
    $pop.css({left: left, top: o.top + $btn.outerHeight() + 4});
    return $pop;
}

// смайлы и эмодзи
$(document).on('click', '[data-act="smiles"]', function(e){
    e.preventDefault(); e.stopPropagation();
    var $btn = $(this), ta = $btn.closest('form').find('textarea[name=text]')[0];
    if ($('.fw-pop-smiles').length) { $('.fw-pop').remove(); return; }
    var $pop = popNear($btn, 'fw-pop-smiles');
    var $em = $('<div class="fw-emoji-list"></div>').appendTo($pop);
    $.each(EMOJI, function(i, em){ $('<a href="#"></a>').text(em).attr('data-ins', em).appendTo($em); });
    if (C.smiles){
        var $sm = $('<div class="fw-smile-list"></div>').appendTo($pop);
        $.each(C.smiles, function(name, src){
            $('<a href="#"></a>').attr('data-ins', ':' + name + ':').attr('title', ':' + name + ':').append($('<img alt="">').attr('src', src)).appendTo($sm);
        });
    }
    $pop.on('click', 'a[data-ins]', function(ev){ ev.preventDefault(); if (ta) { insertAtCaret(ta, $(this).attr('data-ins') + ' '); } });
});

// GIF
$(document).on('click', '[data-act="gif"]', function(e){
    e.preventDefault(); e.stopPropagation();
    var $btn = $(this), $f = $btn.closest('form');
    if ($('.fw-pop-gif').length) { $('.fw-pop').remove(); return; }
    var $pop = popNear($btn, 'fw-pop-gif');
    var $q = $('<input type="text" class="fw-input">').attr('placeholder', L('FW_SEARCH_GIF')).appendTo($pop);
    var $cats = $('<div class="fw-gif-cats"></div>').appendTo($pop);
    var $grid = $('<div class="fw-gif-grid"></div>').appendTo($pop);
    var cat = 0, timer = null;
    function load(withCats){
        $grid.text(L('FW_LOADING'));
        FW.api('gifs', {q: $q.val(), cat: cat, with_cats: withCats ? 1 : 0}, function(r){
            $grid.empty();
            if (withCats && r.cats){
                $.each(r.cats, function(i, c){ $('<a href="#"></a>').text(c.title).attr('data-cat', c.id).appendTo($cats); });
            }
            if (!r.list || !r.list.length) { $grid.text(L('FW_NO_GIFS')); return; }
            $.each(r.list, function(i, g){
                $('<a href="#"></a>').attr('data-url', g.url).attr('title', g.title || '').append($('<img alt="" loading="lazy">').attr('src', g.preview || g.url)).appendTo($grid);
            });
        });
    }
    $q.on('input', function(){ clearTimeout(timer); timer = setTimeout(function(){ load(false); }, 400); });
    $cats.on('click', 'a', function(ev){ ev.preventDefault(); $cats.find('a').removeClass('active'); $(this).addClass('active'); cat = $(this).data('cat'); load(false); });
    $grid.on('click', 'a[data-url]', function(ev){
        ev.preventDefault();
        var url = $(this).attr('data-url');
        attBox($f).find('.fw-att-gif').remove();
        addAtt($f, $('<span class="fw-att fw-att-gif"></span>').append($('<img alt="GIF">').attr('src', url))
            .append($('<input type="hidden" name="gif">').val(url)).append('<a href="#" class="fw-att-del" data-act="att-del">&times;</a>'));
        $('.fw-pop').remove();
    });
    load(true);
    $q.focus();
});

// упоминания
var mentionTimer = null;
$(document).on('input', '.fw-mentionable', function(){
    if (!C.mentions || !C.user_id) { return; }
    var ta = this, pos = ta.selectionStart || 0;
    var m = ta.value.substring(0, pos).match(/(^|\s)@([\w.\-\u0400-\u04FF]{1,30})$/);
    clearTimeout(mentionTimer);
    if (!m) { $('.fw-mention-list').remove(); return; }
    var q = m[2];
    mentionTimer = setTimeout(function(){
        FW.api('mention', {q: q}, function(r){
            $('.fw-mention-list').remove();
            if (!r.list || !r.list.length) { return; }
            var $l = $('<div class="fw-pop fw-mention-list"></div>').appendTo('body');
            var o = $(ta).offset();
            $l.css({left: o.left, top: o.top + $(ta).outerHeight()});
            $.each(r.list, function(i, u){
                $('<a href="#"></a>').attr('data-login', u.login).append($('<img alt="">').attr('src', u.avatar)).append($('<span></span>').text(' ' + u.nickname + ' ')).append($('<small></small>').text('@' + u.login)).appendTo($l);
            });
            $l.on('mousedown', 'a', function(ev){
                ev.preventDefault();
                var p2 = ta.selectionStart, before = ta.value.substring(0, p2).replace(/@[\w.\-\u0400-\u04FF]{1,30}$/, '@' + $(this).data('login') + ' ');
                ta.value = before + ta.value.substring(p2);
                ta.selectionStart = ta.selectionEnd = before.length;
                $l.remove(); $(ta).focus();
            });
        }, function(){ return false; });
    }, 250);
});
$(document).on('blur', '.fw-mentionable', function(){ setTimeout(function(){ $('.fw-mention-list').remove(); }, 200); });

// карточка пользователя
var cardCache = {}, cardTimer = null;
$(document).on('mouseenter', '.fw-usercard[data-uid]', function(){
    var $el = $(this), uid = $el.data('uid');
    clearTimeout(cardTimer);
    cardTimer = setTimeout(function(){
        function show(html){
            $('.fw-pop-card').remove();
            var $pop = popNear($el, 'fw-pop-card').html(html);
            $pop.on('mouseleave', function(){ $pop.remove(); });
        }
        if (cardCache[uid]) { show(cardCache[uid]); return; }
        FW.api('usercard', {user_id: uid}, function(r){ cardCache[uid] = r.html; show(r.html); }, function(){ return false; });
    }, 600);
});
$(document).on('mouseleave', '.fw-usercard[data-uid]', function(){
    clearTimeout(cardTimer);
    setTimeout(function(){ if (!$('.fw-pop-card:hover').length) { $('.fw-pop-card').remove(); } }, 400);
});

// голосовые сообщения
var rec = null;
$(document).on('click', '[data-act="voice"]', function(e){
    e.preventDefault();
    var $btn = $(this), $f = $btn.closest('form');
    if (rec && rec.state === 'recording'){ rec.stop(); return; }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder){ FW.toast(L('FW_VOICE_UNSUPPORTED'), true); return; }
    navigator.mediaDevices.getUserMedia({audio: true}).then(function(stream){
        var chunks = [], started = Date.now(), limit;
        rec = new MediaRecorder(stream);
        rec.ondataavailable = function(ev){ if (ev.data && ev.data.size) { chunks.push(ev.data); } };
        rec.onstop = function(){
            clearTimeout(limit);
            stream.getTracks().forEach(function(t){ t.stop(); });
            $btn.removeClass('fw-recording').html('&#127908;');
            var dur = Math.round((Date.now() - started) / 1000);
            var type = rec.mimeType || 'audio/webm';
            var blob = new Blob(chunks, {type: type});
            if (!blob.size) { return; }
            var ext = type.indexOf('ogg') > -1 ? 'ogg' : (type.indexOf('mp4') > -1 ? 'm4a' : 'webm');
            var fd = new FormData();
            fd.append('type', 'voice'); fd.append('duration', dur); fd.append('file', blob, 'voice.' + ext);
            FW.api('upload', fd, function(r){
                attBox($f).find('.fw-att-voice').remove();
                addAtt($f, $('<span class="fw-att fw-att-voice"></span>').text('\uD83C\uDFA4 ' + dur + 's')
                    .append($('<input type="hidden" name="voice">').val(r.media.id)).append('<a href="#" class="fw-att-del" data-act="att-del">&times;</a>'));
            });
        };
        rec.start();
        $btn.addClass('fw-recording').text('\u25A0 ' + L('FW_STOP'));
        limit = setTimeout(function(){ if (rec && rec.state === 'recording') { rec.stop(); } }, (C.voice_max || 60) * 1000);
    }, function(){ FW.toast(L('FW_VOICE_UNSUPPORTED'), true); });
});

// ------------------------------------------------------------------ старт
$(function(){
    FW.init(document);
    $('.fw-post-form').each(function(){ FW.initForm($(this)); });
    if (window.location.hash && /^#fw-comment-\d+$/.test(window.location.hash)){
        var $c = $(window.location.hash);
        if ($c.length) { $c.addClass('fw-highlight'); $('html,body').scrollTop($c.offset().top - 80); }
    }
});

})(jQuery);
