<?php

use Widget\Archive;

/**
 * 获取指定文章的类型
 *
 * @param int $cid 文章的唯一标识符
 * @return string 返回文章的类型
 * @throws \Typecho\Db\Exception
 */
function getPostType(int $cid): string
{
    try {
        $db = \Typecho\Db::get();
        $postType = $db->fetchRow($db->select('str_value')
            ->from('table.fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', 'postType')
        );
        return !empty($postType) ? $postType["str_value"] : 'post';
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return 'post';
    }
}

/**
 * 获取指定文章的略缩图
 *
 * @param int $cid 文章的唯一标识符
 * @return string 返回文章的略缩图地址
 */
function getPostThumbnail(int $cid): string {
    try {
        $db = \Typecho\Db::get();
        $thumbnail = $db->fetchRow($db->select('str_value')
            ->from('table.fields')
            ->where('cid = ?', $cid)
            ->where('name = ?', 'thumbnail')
        );

        return !empty($thumbnail["str_value"]) 
            ? $thumbnail["str_value"] 
            : \Utils\Helper::options()->themeUrl . '/assets/img/post.webp';
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return \Utils\Helper::options()->themeUrl . '/assets/img/post.webp';
    }
}

/**
 * 获取和更新文章浏览量
 */
function getPostView($archive): void {
    try {
        $cid = $archive->cid;
        $db = \Typecho\Db::get();
        
        // 获取当前文章的浏览量
        $row = $db->fetchRow($db->select('views')
            ->from('table.contents')
            ->where('cid = ?', $cid));
        $views = $row ? (int) $row['views'] : 0;

        // 如果是单篇文章页面，则增加浏览量
        if ($archive->is('single')) {
            $cookieViews = \Typecho\Cookie::get('__post_views');
            $viewedPosts = $cookieViews ? explode(',', $cookieViews) : [];
            
            if (!in_array($cid, $viewedPosts)) {
                $db->query($db->update('table.contents')
                    ->rows(['views' => $views + 1])
                    ->where('cid = ?', $cid));
                $viewedPosts[] = $cid;
                \Typecho\Cookie::set('__post_views', implode(',', $viewedPosts));
                $views++;
            }
        }

        // 格式化浏览量
        if ($views >= 10000) {
            $formattedViews = number_format($views / 10000, 1) . 'w';
        } elseif ($views >= 1000) {
            $formattedViews = number_format($views / 1000, 1) . 'k';
        } else {
            $formattedViews = $views;
        }
        echo $formattedViews;
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        echo '0';
    }
}

/**
 * 获取文章浏览数量
 */
function getPostViewNum($cid) {
    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('views')
            ->from('table.contents')
            ->where('cid = ?', $cid));
        return formatNumber($row ? (int) $row['views'] : 0);
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return '0';
    }
}

/**
 * 获取文章点赞数量
 */
function getPostLikeNum(int $cid): int {
    try {
        $db = \Typecho\Db::get();
        $likes = $db->fetchRow($db->select('likes')
            ->from('table.contents')
            ->where('cid = ?', $cid));
        return (int)($likes['likes'] ?? 0);
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return 0;
    }
}

/**
 * 获取文章链接
 */
function getPostLink($cid): string
{
    try {
        $db = \Typecho\Db::get();
        
        // 获取文章基本信息
        $article = $db->fetchRow($db->select()
            ->from('table.contents')
            ->where('cid = ?', $cid));
        
        if (!$article) {
            return '';
        }

        // 获取文章分类
        $category = $db->fetchRow($db->select('table.metas.*')
            ->from('table.metas')
            ->join('table.relationships', 'table.metas.mid = table.relationships.mid')
            ->where('table.relationships.cid = ?', $cid)
            ->where('table.metas.type = ?', 'category'));

        return \Typecho\Router::url($article['type'], array(
            'cid' => $cid,
            'slug' => $article['slug'],
            'category' => $category,
            'year' => date('Y', $article['created']),
            'month' => date('m', $article['created']),
            'day' => date('d', $article['created'])
        ), \Utils\Helper::options()->index);
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return '';
    }
}

/**
 * 获取文章点赞列表
 */
function getPostLikeList($cid, $limit = 10): array {
    try {
        $db = \Typecho\Db::get();
        $limit = max(1, (int)$limit);
        
        // 获取点赞总数
        $likesTotalNum = getPostLikeNum($cid);
        
        // 查询点赞列表
        $likesList = $db->fetchAll($db->select()
            ->from('table.post_like_list')
            ->where('cid = ?', $cid)
            ->order('id', \Typecho\Db::SORT_ASC)
            ->limit($limit));

        // 查询点赞人数
        $likesCount = $db->fetchRow($db->select(['COUNT(id) as count'])
            ->from('table.post_like_list')
            ->where('cid = ?', $cid));

        return array(
            'likesCount' => (int)($likesCount['count'] ?? 0),
            'likesTotalNum' => $likesTotalNum,
            'likesList' => $likesList
        );
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return array(
            'likesCount' => 0,
            'likesTotalNum' => 0,
            'likesList' => array()
        );
    }
}

/**
 * 获取文章点赞HTML
 */
function getPostLikeHtml(int $cid, string $location = 'index'): string {
    $likes = getPostLikeList($cid, 99999);
    $likesCount = $likes["likesCount"];
    $likesTotalNum = $likes["likesTotalNum"];
    $likesList = $likes["likesList"];
    
    $displayHtml = '';
    
    if ($location == 'index') {
        $displayHtml = '<span class="reborn rb-heart-o"></span>&nbsp;<span class="like-area">';
        if ($likesCount == 0) {
            $displayHtml .= $likesTotalNum . '人';
        } else {
            $names = array_slice(array_map(function($likePeople) {
                if ($likePeople['url']) {
                    return '<a class="like-people" target="_blank" rel="nofollow" href="' . 
                           ensureAbsoluteUrl(htmlspecialchars($likePeople['url'])) . '">' . 
                           htmlspecialchars($likePeople['name']) . '</a>';
                }
                return '<span class="like-people">' . htmlspecialchars($likePeople['name']) . '</span>';
            }, $likesList), 0, 10);
            
            $displayHtml .= implode('、', $names);
            if ($likesTotalNum > 10 || $likesTotalNum > $likesCount) {
                $displayHtml .= ' 等' . $likesTotalNum . '人';
            }
        }
        $displayHtml .= '</span>';
    } elseif ($location == 'shuoshuo') {
        $displayHtml = '<div class="shuoshuo-like"><span class="reborn rb-heart-o"></span></div><span class="like-area">';
        if ($likesCount == 0) {
            $displayHtml .= '<span class="like-num">' . $likesTotalNum . '人</span>';
        } else {
            $avatars = array_map(function($likePeople) {
                $peopleMail = $likePeople['mail'] ?? '';
                $gravatarUrl = getGravatarUrl($peopleMail);
                if ($likePeople['url']) {
                    return '<a class="like-people" target="_blank" rel="nofollow" title="' . 
                           htmlspecialchars($likePeople['name']) . '" href="' . 
                           ensureAbsoluteUrl(htmlspecialchars($likePeople['url'])) . '">' . 
                           '<img src="' . htmlspecialchars($gravatarUrl) . '" alt="' . 
                           htmlspecialchars($likePeople['name']) . '" class="like-avatar" />' . '</a>';
                }
                return '<img src="' . htmlspecialchars($gravatarUrl) . '" title="' . 
                       htmlspecialchars($likePeople['name']) . '" alt="' . 
                       htmlspecialchars($likePeople['name']) . '" class="like-avatar" />';
            }, $likesList);
            
            $displayHtml .= implode(' ', $avatars);
            if ($likesTotalNum > $likesCount) {
                $displayHtml .= '<span class="like-num">等' . $likesTotalNum . '人</span>';
            }
        }
        $displayHtml .= '</span>';
    } elseif ($location == 'post') {
        $displayHtml = '<div class="post-like-num">' . $likesTotalNum . '人喜欢</div>';
        if ($likesCount != 0) {
            $displayHtml .= '<div class="like-people-list">';
            $avatars = array_map(function($likePeople) {
                $peopleMail = $likePeople['mail'] ?? '';
                $gravatarUrl = getGravatarUrl($peopleMail);
                if ($likePeople['url']) {
                    return '<a class="like-people" target="_blank" rel="nofollow" title="' . 
                           htmlspecialchars($likePeople['name']) . '" href="' . 
                           ensureAbsoluteUrl(htmlspecialchars($likePeople['url'])) . '">' . 
                           '<img src="' . htmlspecialchars($gravatarUrl) . '" alt="' . 
                           htmlspecialchars($likePeople['name']) . '" class="like-avatar" />' . '</a>';
                }
                return '<img src="' . htmlspecialchars($gravatarUrl) . '" title="' . 
                       htmlspecialchars($likePeople['name']) . '" alt="' . 
                       htmlspecialchars($likePeople['name']) . '" class="like-avatar" />';
            }, $likesList);
            $displayHtml .= implode(' ', $avatars);
            $displayHtml .= '</div>';
        }
    }
    return $displayHtml;
}

/**
 * 获取最新的非说说类型文章
/**
 * 获取最新的非说说类型文章
 *
 * @param int $limit 要获取的文章数量
 * @return array 最新的文章列表
 */
function getLatestPosts(int $limit = 5): array
{
    try {
        $db = \Typecho\Db::get();
        $isSQL = $db->getAdapterName() == 'Mysql';  // 检查数据库类型

        // 构建查询
        if ($isSQL) {
            // MySQL查询
            $query = $db->select('table.contents.*')
                ->from('table.contents')
                ->join('table.fields', 'table.contents.cid = table.fields.cid')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.fields.name = ?', 'postType')
                ->where('table.fields.str_value != ?', 'shuoshuo')
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                ->limit($limit);
        } else {
            // SQLite查询
            $query = $db->select('DISTINCT table.contents.*')
                ->from('table.contents')
                ->join('table.fields', 'table.contents.cid = table.fields.cid', \Typecho\Db::LEFT_JOIN)
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.fields.name = ? OR table.fields.name IS NULL', 'postType')
                ->where('table.fields.str_value != ? OR table.fields.str_value IS NULL', 'shuoshuo')
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                ->limit($limit);
        }

        // 执行查询
        $posts = $db->fetchAll($query);
        
        if (empty($posts)) {
            error_log('No posts found in getLatestPosts');
            return [];
        }

        // 返回结果前记录日志
        error_log('Found ' . count($posts) . ' posts in getLatestPosts');
        return $posts;
    } catch (Exception $e) {
        error_log('Database Query Error in getLatestPosts: ' . $e->getMessage());
        error_log('SQL Query: ' . $query->__toString());
        return [];
    }
}

/**
 * 生成图片展示HTML
 */
function generateGalleryHtml($images, $cid = 0, $showAll = 0): string {
    $imageCount = count($images);
    $imageHtml = '<div class="gallery-images">';
    $imagesProcessed = 0;
    
    if ($imageCount == 4) {
        $rows = array_chunk($images, 2);
        foreach ($rows as $row) {
            $imageHtml .= '<div class="gallery-row-2">';
            foreach ($row as $image) {
                $imageHtml .= generateGalleryItem($image);
            }
            $imageHtml .= '</div>';
        }
    } else {
        $rows = array_chunk($images, 3);
        foreach ($rows as $row) {
            $imageHtml .= '<div class="gallery-row">';
            foreach ($row as $image) {
                if ($showAll == 0 && $imagesProcessed == 8 && $imageCount >= 9) {
                    $remainingCount = $imageCount - 9;
                    $imageHtml .= generateGalleryItem($image, $cid, $remainingCount);
                    break 2;
                }
                $imageHtml .= generateGalleryItem($image);
                $imagesProcessed++;
            }
            $imageHtml .= '</div>';
            if ($imageCount <= 9) {
                $imageCount -= 3;
            }
        }
    }
    $imageHtml .= '</div>';
    return $imageHtml;
}

/**
 * 生成单个图片项HTML
 */
function generateGalleryItem($image, $cid = 0, $remainingCount = 0): string {
    $html = '<div class="gallery-image-item">';
    if ($remainingCount > 0) {
        $html .= $image . '<a class="overlay" href="' . getPostLink($cid) . '">+' . $remainingCount . '</a>';
    } else {
        $html .= $image;
    }
    $html .= '</div>';
    return $html;
}

/**
 * 获取作者文章统计
 * 返回文章数量、总赞数和总浏览量
 *
 * @return array 包含统计数据的数组
 */
function getAuthorPostStats() {
    try {
        $db = \Typecho\Db::get();
        $isSQL = $db->getAdapterName() == 'Mysql';  // 检查数据库类型

        if ($isSQL) {
            // MySQL查询
            $query = $db->select([
                    'COUNT(DISTINCT table.contents.cid) as numPosts',
                    'COALESCE(SUM(table.contents.likes), 0) as totalLikes',
                    'COALESCE(SUM(table.contents.views), 0) as totalViews'
                ])
                ->from('table.contents')
                ->join('table.fields', 'table.contents.cid = table.fields.cid')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.fields.name = ?', 'postType')
                ->where('table.fields.str_value != ?', 'shuoshuo');
        } else {
            // SQLite查询
            $query = $db->select([
                    'COUNT(DISTINCT table.contents.cid) as numPosts',
                    'COALESCE(SUM(CAST(table.contents.likes AS INTEGER)), 0) as totalLikes',
                    'COALESCE(SUM(CAST(table.contents.views AS INTEGER)), 0) as totalViews'
                ])
                ->from('table.contents')
                ->join('table.fields', 'table.contents.cid = table.fields.cid', 'LEFT JOIN')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.fields.name IS NULL OR (table.fields.name = ? AND table.fields.str_value != ?)', 'postType', 'shuoshuo');
        }

        $result = $db->fetchRow($query);
        
        // 调试日志
        error_log('Stats query result: ' . print_r($result, true));

        // 确保返回值为整数
        $stats = [
            'numPosts' => (int)($result['numPosts'] ?? 0),
            'totalLikes' => (int)($result['totalLikes'] ?? 0),
            'totalViews' => (int)($result['totalViews'] ?? 0)
        ];

        // 格式化数字
        return [
            'numPosts' => formatNumber($stats['numPosts']),
            'totalLikes' => formatNumber($stats['totalLikes']),
            'totalViews' => formatNumber($stats['totalViews'])
        ];
    } catch (Exception $e) {
        error_log('Error in getAuthorPostStats: ' . $e->getMessage());
        error_log('Query: ' . $query->__toString());
        return [
            'numPosts' => '0',
            'totalLikes' => '0',
            'totalViews' => '0'
        ];
    }
}


/**
 * 生成文章目录树
 */
function generateToc($content): string {
    $idCounter = 1;
    $matches = array();
    preg_match_all('/<h([1-5])(?![^>]*class=)([^>]*)>(.*?)<\/h\\1>/', $content, $matches, PREG_SET_ORDER);
    
    if (!$matches) {
        return '暂无目录';
    }
    
    $toc = '<ul class="ul-toc">';
    $currentLevel = 0;
    
    foreach ($matches as $match) {
        $level = (int)$match[1];
        $attributes = $match[2];
        $title = strip_tags($match[3]);
        $anchor = 'header-' . $idCounter++;
        
        $content = str_replace(
            $match[0],
            '<h' . $level . ' id="' . $anchor . '"' . $attributes . '>' . $match[3] . '</h' . $level . '>',
            $content
        );
        
        if ($currentLevel == 0) {
            $currentLevel = $level;
        }
        
        while ($currentLevel < $level) {
            $toc .= '<ul>';
            $currentLevel++;
        }
        
        while ($currentLevel > $level) {
            $toc .= '</ul></li>';
            $currentLevel--;
        }
        
        $toc .= '<li><a href="#' . $anchor . '" class="toc-link">' . $title . '</a></li>';
    }
    
    while ($currentLevel > 0) {
        $toc .= '</ul>';
        $currentLevel--;
    }
    
    return $toc;
}

/**
 * 获取置顶文章CIDs
 */
function getStickyPostsCids() {
    try {
        $db = \Typecho\Db::get();
        
        $query = $db->select('table.contents.cid')
            ->from('table.contents')
            ->join('table.fields', 'table.fields.cid = table.contents.cid')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.fields.name = ?', 'postSticky')
            ->where('table.fields.str_value = ?', 'sticky')
            ->order('table.contents.created', \Typecho\Db::SORT_DESC);

        $cids = $db->fetchAll($query);
        return array_column($cids, 'cid');
    } catch (Exception $e) {
        error_log('Database Query Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * MBTI相关函数
 */
function replaceMbtiShortcode($content) {
    $pattern = '/\[mbti\s*=\s*\'([^\']+)\'\s*per1\s*=\s*"(\d+)"\s*per2\s*=\s*"(\d+)"\s*per3\s*=\s*"(\d+)"\s*per4\s*=\s*"(\d+)"\s*per5\s*=\s*"(\d+)"\s*per6\s*=\s*"(\d+)"\]/';
    
    return preg_replace_callback($pattern, function ($matches) {
        $mbti = $matches[1];
        $percentages = array_map('intval', array_slice($matches, 2, 6));
        $translatedMbti = translateMbti($mbti);
        $svgName = substr($mbti, 0, 4);
        
        ob_start();
        ?>
        <div class="mbti flex">
            <img src="<?php echo $this->options->themeUrl('assets/img/16personalities/' . $svgName . '.svg'); ?>" 
                 alt="<?php echo htmlspecialchars($svgName); ?>"/>
            <div class="mbti-info">
                <div class="mbti-name"><?php echo htmlspecialchars($mbti); ?></div>
                <?php foreach ($translatedMbti['mainType'] as $index => $trait): ?>
                    <div class="mbti-per" data-per="<?php echo $percentages[$index]; ?>">
                        <?php echo htmlspecialchars($trait); ?>
                    </div>
                <?php endforeach; ?>
                <?php foreach ($translatedMbti['additionalType'] as $index => $trait): ?>
                    <div class="mbti-per" data-per="<?php echo $percentages[$index + 4]; ?>">
                        <?php echo htmlspecialchars($trait); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }, $content);
}

/**
 * MBTI字符映射
 */
function translateMbti($mbti) {
    $mbtiMap = [
        'I' => '内向', 'E' => '外向',
        'S' => '现实', 'N' => '直觉',
        'T' => '逻辑', 'F' => '情感',
        'P' => '展望', 'J' => '计划'
    ];
    
    $additionalMap = [
        'A' => '自信', 'T' => '动荡',
        'C' => '高冷', 'H' => '温暖'
    ];
    
    $mainType = substr($mbti, 0, 4);
    $additionalType = substr($mbti, 5);
    
    $translatedType = array_map(function($char) use ($mbtiMap) {
        return $mbtiMap[$char] ?? '';
    }, str_split($mainType));
    
    $translatedAdditional = array_filter(array_map(function($char) use ($additionalMap) {
        return $char !== '-' ? ($additionalMap[$char] ?? '') : '';
    }, str_split($additionalType)));
    
    return [
        'mainType' => $translatedType,
        'additionalType' => array_values($translatedAdditional)
    ];
}