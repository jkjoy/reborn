<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<section class="widget" id="sidebar-comment-list">
    <h3 class="widget-title"><?php _e('最新评论'); ?></h3>
    <ul class="widget-list">
        <?php
        $comments = getLatestComments($this->options->commentsListSize);
        foreach ($comments as $comment):
            // 获取评论所属文章的 URL
            $postUrl = Typecho_Router::url('post', array('cid' => $comment['cid']), Helper::options()->index);
            ?>
            <li>
                <a class="flex comment-item"  href="<?php echo $postUrl . '#comment-' . $comment['coid']; ?>">
                    <img class="comment-author-avatar" src="<?php echo getGravatarUrl($comment['mail']); ?>" alt="<?php echo $comment['author']; ?>">
                    <div class="flex-1">
                        <div class="comment-author"><?php echo $comment['author']; ?></div>
                        <div class="comment-content"><?php echo commentEmojiReplace($comment['text']); ?></div>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>