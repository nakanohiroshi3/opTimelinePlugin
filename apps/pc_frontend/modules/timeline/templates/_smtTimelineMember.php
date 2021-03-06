<script type="text/javascript">
//<![CDATA[
var gorgon = {
      'mode': 'member',
      'memberId': <?php echo $id; ?>,
    };
//]]>
</script>
<script type="text/javascript" src="<?php echo url_for('@homepage'); ?>opTimelinePlugin/js/jquery.timeline.js"></script>
<script type="text/javascript" src="<?php echo url_for('@homepage'); ?>opTimelinePlugin/js/gorgon-smt.js"></script>
<script id="timelineTemplate" type="text/x-jquery-tmpl">
<div class="timeline font10 row">
  <div class="span2"><a href="<?php echo url_for('@homepage'); ?>member/${memberId}"><img src="${memberImage}" class="rad6" width="46" height="46" /></a></div>
  <div class="span10">
    <div id="timelinebody-${id}" style="min-height: 48px;">
    {{html body}}
    </div>
    <div id="commentlist-${id}"></div>
  </div>
</div>
<div class="row span10" id="timeline-now-comment-${id}" style="margin-left: 50px;">
  <button class="timeline-now-comment-button commentbutton btn span10 small" style="height: 20px;  padding-top: 2px; padding-bottom: 2px; margin-left: 0px; margin-right: 0px; padding-left: 0px; padding-right: 0px;">コメントする</button>
</div>
<div class="row hide" id="timeline-comment-form-${id}">
  <form class="span10 offset2" style="margin-bottom: 0px;">
    <textarea class="span10" data-timeline-id="${id}" data-post-csrftoken="<?php echo $token; ?>" style="height: 35px;" id="comment-textarea-${id}"></textarea>
    <button data-timeline-id="${id}" data-post-csrftoken="<?php echo $token; ?>" data-post-baseurl="<?php echo url_for('@homepage'); ?>" class="btn primary small timeline-comment-button center span10" style="height: 20px; padding: 1px; text-align: center;">投稿</button>
  </form>
</div>
</script>

<script id="timelineCommentTemplate" type="text/x-jquery-tmpl">
<div class="comment font10 row">
  <div class="span10">
    <div class="row">
      <hr class="toumei2">
      <div class="span1"><a href="<?php echo url_for('@homepage'); ?>member/${memberId}"><img src="${memberImage}" class="rad6" width="23" height="23" /></a></div>
      <div class="span9">{{html body}}</div>
    </div>
  </div>
</div>
</script>

<script id="timeline-error-template" type="text/x-jquery-tmpl">
  <div id="error-panel" style="display: none;">
    <span id="error">${json.message}</span>
  </div>
</script>
<script id="timeline-delete-confirm-template" type="text/x-jquery-tmpl">
  <div id="delete-confirm-panel" style="display: none;">
   <span id="error">${json.message}</span>
  </div>
</script>

<div class="row">
  <div class="gadget_header span12"><?php if ($member): ?><?php echo $member->getName(); ?>さんのタイムライン<?php else: ?>タイムライン<?php endif; ?></div>
</div>
<?php if ($member): ?>
<?php if ($sf_user->getMemberId()==$member->getId()): ?>
<a href="<?php echo url_for('@homepage'); ?>member/config?category=timelineScreenName">■スクリーンネーム設定画面</a><br />
<?php endif; ?>
<?php endif; ?>

<div id="timeline-list" class="row span12 hide" data-post-baseurl="<?php echo url_for('@homepage'); ?>" data-last-id="" data-loadmore-id="">
</div>
<div id="timeline-list-loader" class="row span12 center show">
<img src="<?php echo url_for('@homepage'); ?>images/ajax-loader.gif" alt="Now loading..." />
</div>
<div id="gorgon-submit" data-post-csrftoken="<?php echo $token; ?>" data-post-baseurl="<?php echo url_for('@homepage'); ?>"></div>
<div class="row">
  <button class="span12 btn small" id="gorgon-loadmore">もっと読む</button>
</div>

