<?php

/**
 * 根据IP地址获取地区信息。
 *
 * @param string $ip      要查询的IP地址
 *
 * @return string 返回根据IP地址获取的地区信息。
 */
function getRegionByIp(string $ip): string {
    $apiKey = \Utils\Helper::options()->tencentMapApiKey;
    if (!$apiKey) return '未知';
    $url = "https://apis.map.qq.com/ws/location/v1/ip?key={$apiKey}&ip={$ip}";
    $response = file_get_contents($url);
    if ($response === FALSE) {
        return "未知";
    }
    $data = json_decode($response, true);
    if ($data['status'] !== 0) {
        return "未知";
    }
    if ($data['result']['ad_info']['nation_code']!=156) {
        return $data['result']['ad_info']['nation'];
    } else {
        $province = $data['result']['ad_info']['province'];
        if (mb_substr($province, -1) === '省' || mb_substr($province, -1) === '市')
            return mb_substr($province, 0, mb_strlen($province) - 1);
        else
            return $province;
    }
}

/**
 * 根据评论的coid获取对应的地区信息。
 *
 * @param int $coid 评论的ID
 * @return string 返回对应的地区信息
 * @throws \Typecho\Db\Exception
 */
function getRegionByCoid(int $coid): string {
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    
    // 使用参数绑定防止SQL注入
    $select = $db->select('region', 'ip')
        ->from('table.comments')
        ->where('coid = ?', $coid);
    
    $result = $db->fetchRow($select);
    $apiKey = \Utils\Helper::options()->tencentMapApiKey;
    
    if ($result && empty($result['region'])) {
        if ($apiKey) {
            $newRegion = getRegionByIp($result['ip']);
            // 使用表别名防止SQLite兼容性问题
            $update = $db->update('table.comments')
                ->rows(['region' => $newRegion])
                ->where('coid = ?', $coid);
            $db->query($update);
            return $newRegion;
        } else {
            return '未知';
        }
    }
    return $result ? $result['region'] : '';
}

/**
 * 获取评论点赞数量
 *
 * @param int $coid 评论ID
 * @return int 点赞数量
 */
function getCommentLikeNum(int $coid): int
{
    try {
        $db = \Typecho\Db::get();
        // 使用表别名确保SQLite兼容性
        $likes = $db->fetchRow($db->select('likes')
            ->from('table.comments')
            ->where('coid = ?', $coid));
        return (int)($likes['likes'] ?? 0);
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * 判断文章是否有评论
 *
 * @param int $cid 文章ID
 * @return int 评论数量
 */
function haveComments(int $cid): int {
    try {
        $db = \Typecho\Db::get();
        // 使用COUNT(*)而不是COUNT(field)以提高兼容性
        $comments = $db->fetchRow($db->select('COUNT(*) AS count')
            ->from('table.comments')
            ->where('cid = ?', $cid)
            ->where('status = ?', 'approved'));
        return (int)($comments['count'] ?? 0);
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * 确保URL为绝对路径
 */
function ensureAbsoluteUrl($url) {
    if (empty($url)) {
        return '#';
    }
    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        return 'http://' . $url;
    }
    return $url;
}

/**
 * 获取指定文章的评论，包括子评论
 *
 * @param int $cid 文章ID
 * @param int $parent 父评论ID
 * @param null $limit 限制数量
 * @return array 评论列表
 */
function getCommentsWithReplies(int $cid, int $parent = 0, $limit = null): array
{
    try {
        $db = \Typecho\Db::get();
        $options = \Typecho\Widget::widget('Widget_Options');
        $commentsOrder = $options->commentsOrder;

        $select = $db->select(
            'coid, author, authorId, ownerId, mail, text, created, parent, url, cid'
        )->from('table.comments')
            ->where('cid = ?', $cid)
            ->where('parent = ?', $parent)
            ->where('type = ?', 'comment')
            ->where('status = ?', 'approved')
            ->order('created', $commentsOrder);

        if ($limit !== null) {
            $select->limit($limit);
        }

        $comments = $db->fetchAll($select);
        foreach ($comments as &$comment) {
            $comment['replies'] = getCommentsWithReplies($cid, $comment['coid']);
        }
        return $comments;
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * 递归渲染评论
 */
function renderComments(array $comments, string $link, int $maxTopLevelComments = 5): void
{
    $commentCount = count($comments);
    $displayCount = 0;
    $showAll = $maxTopLevelComments === 0;
    
    foreach ($comments as $comment) {
        if ($showAll || $displayCount < $maxTopLevelComments) {
            echo '<li id="comment-' . htmlspecialchars($comment['coid']) . '" class="comment-item">';
            echo '<div class="comment-item-header">';
            
            $hasLink = !empty($comment['url']) 
                ? ' href="' . htmlspecialchars(ensureAbsoluteUrl($comment['url'])) . '" target="_blank" rel="nofollow"'
                : '';
            
            echo '<a class="comment-author"' . $hasLink . '>' . htmlspecialchars($comment['author']) . '</a>';
            echo '<span class="separator post-comment flex-1" data-cid="' . 
                htmlspecialchars($comment['cid']) . '" data-coid="' . 
                htmlspecialchars($comment['coid']) . '" data-name="' . 
                htmlspecialchars($comment['author']) . '" data-location="index">' . 
                commentEmojiReplace($comment['text']) .'</span>';
            echo '</div>';
            
            if (!empty($comment['replies'])) {
                echo '<ul class="comment-replies">';
                renderReplies($comment['replies'], $comment['author']);
                echo '</ul>';
            }
            echo '</li>';
            $displayCount++;
        } else {
            break;
        }
    }
    
    if (!$showAll && $commentCount > $maxTopLevelComments) {
        echo '<li class="comment-item"><a class="more-comments underline" href="' . 
            htmlspecialchars($link) . '#comments">查看更多</a></li>';
    }
}

/**
 * 递归渲染回复评论
 */
function renderReplies(array $replies, string $parentAuthor): void
{
    foreach ($replies as $reply) {
        echo '<li id="comment-' . htmlspecialchars($reply['coid']) . '" class="comment-item">';
        echo '<div class="comment-item-header">';
        
        $hasLink = !empty($reply['url']) 
            ? ' href="' . htmlspecialchars(ensureAbsoluteUrl($reply['url'])) . '" target="_blank" rel="nofollow"'
            : '';
        
        echo '<a class="comment-author"' . $hasLink . '>' . htmlspecialchars($reply['author']) . '</a> ';
        commentReply($reply['coid']);
        echo '<span class="post-comment flex-1" data-cid="' . 
            htmlspecialchars($reply['cid']) . '" data-coid="' . 
            htmlspecialchars($reply['coid']) . '" data-name="' . 
            htmlspecialchars($reply['author']) . '" data-location="index">' . 
            commentEmojiReplace($reply['text']) .'</span>';
        echo '</div>';
        
        if (!empty($reply['replies'])) {
            echo '<ul class="comment-replies">';
            renderReplies($reply['replies'], $reply['author']);
            echo '</ul>';
        }
        echo '</li>';
    }
}

/**
 * 评论回复功能
 */
function commentReply(int $coid)
{
    try {
        $db = \Typecho\Db::get();
        
        // 获取当前评论信息
        $comment = $db->fetchRow($db->select()
            ->from('table.comments')
            ->where('coid = ?', $coid));
        
        if ($comment && $comment['parent'] != 0) {
            // 获取父评论信息
            $parentComment = $db->fetchRow($db->select()
                ->from('table.comments')
                ->where('coid = ?', $comment['parent']));
            
            if ($parentComment) {
                $hasLink = !empty($parentComment['url']) 
                    ? ' href="' . htmlspecialchars(ensureAbsoluteUrl($parentComment['url'])) . 
                      '" target="_blank" rel="nofollow"'
                    : '';
                
                $parentAuthor = htmlspecialchars($parentComment['author']);
                echo '回复 <a class="comment-author"' . $hasLink . '>' . 
                     $parentAuthor . '</a><span class="separator"></span>';
            }
        }
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
    }
}

/**
 * 移除评论段落标签
 */
function removeCommentPar($content)
{
    return preg_replace("/^<p>(.*)<\/p>$/", '$1', $content);
}

/**
 * 评论表情替换
 */
function commentEmojiReplace($comment_text): string {
    $directory = '/usr/themes/reborn/assets/emoji/';
    $categories = ['wechat', 'xiaodianshi'];
    $data_OwO = [];
    
    try {
        $db = \Typecho\Db::get();
        // 使用参数绑定防止SQL注入
        $siteUrlRow = $db->fetchRow($db->select('value')
            ->from('table.options')
            ->where('name = ?', 'siteUrl'));
        $siteUrl = $siteUrlRow['value'];
        
        foreach ($categories as $category) {
            $path = __TYPECHO_ROOT_DIR__ . $directory . $category;
            if (is_dir($path)) {
                $files = scandir($path);
                foreach ($files as $file) {
                    if (strpos($file, '.png') !== false) {
                        $emoji_name = mb_substr($file, 0, -4);
                        $data_OwO['@(' . $emoji_name . ')'] = sprintf(
                            '<img src="%s%s%s/%s" alt="%s" class="rb-emoji-item">',
                            htmlspecialchars($siteUrl),
                            $directory,
                            $category,
                            htmlspecialchars($file),
                            htmlspecialchars($emoji_name)
                        );
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
    }
    
    return strtr($comment_text, $data_OwO);
}