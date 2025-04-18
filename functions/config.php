<?php

// 主题设置
function themeConfig($form): void {
    $db = \Typecho\Db::get();
    $prefix = $db->getPrefix();
    $isSQL = $db->getAdapterName() == 'Mysql';  // 检查数据库类型
    
    try {
        // 获取数据库类型相关的SQL语法
        $integerType = $isSQL ? 'INT(10)' : 'INTEGER';
        $varcharType = 'varchar';
        $autoIncrement = $isSQL ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
        $enginePhrase = $isSQL ? 'ENGINE=InnoDB' : '';
        $showColumns = $isSQL ? 
            'SHOW COLUMNS FROM `' . $prefix . 'contents`' : 
            "SELECT * FROM sqlite_master WHERE type='table' AND name='" . $prefix . "contents'";
            
        // 添加新列
        $columnsToAdd = [
            'postType' => ["varchar(16) NOT NULL DEFAULT 'post'", 'contents'],
            'likes' => ["$integerType NOT NULL DEFAULT 0", 'contents'],
            'views' => ["$integerType NOT NULL DEFAULT 0", 'contents'],
            'likes' => ["$integerType NOT NULL DEFAULT 0", 'comments'],
            'region' => ["varchar(50) NULL", 'comments']
        ];

        foreach ($columnsToAdd as $column => $details) {
            $table = $details[1];
            $type = $details[0];
            
            if ($isSQL) {
                // MySQL方式添加列
                if (!in_array($column, $db->fetchAll("SHOW COLUMNS FROM `{$prefix}{$table}`"))) {
                    $db->query("ALTER TABLE `{$prefix}{$table}` ADD `{$column}` {$type};");
                }
            } else {
                // SQLite方式添加列
                $tableInfo = $db->fetchAll("PRAGMA table_info('{$prefix}{$table}')");
                $columnExists = false;
                foreach ($tableInfo as $col) {
                    if ($col['name'] == $column) {
                        $columnExists = true;
                        break;
                    }
                }
                if (!$columnExists) {
                    $db->query("ALTER TABLE `{$prefix}{$table}` ADD COLUMN `{$column}` {$type};");
                }
            }
        }

        // 检查并创建文章点赞列表
        if ($isSQL) {
            $sql = "SHOW TABLES LIKE '{$prefix}post_like_list'";
            $result = $db->fetchRow($sql);
        } else {
            $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$prefix}post_like_list'";
            $result = $db->fetchRow($sql);
        }
        
        if (!$result) {
            // 创建文章点赞列表
            $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}post_like_list` (
                `id` INTEGER NOT NULL PRIMARY KEY {$autoIncrement},
                `cid` {$integerType} NOT NULL,
                `name` {$varcharType}(255) NOT NULL,
                `mail` {$varcharType}(255) NOT NULL,
                `url` {$varcharType}(255)
            ) {$enginePhrase};";
            $db->query($sql);
        }
    } catch (Exception $e) {
        // 错误处理
    }
    ?>

    <?php
    // ----------------------------首页设置----------------------------
    // 主页头像
    $avatarEmail = new Typecho\Widget\Helper\Form\Element\Text(
        'avatarEmail',
        NULL,
        NULL,
        _t('主页头像邮箱'),
        _t('主页头像邮箱，调用Gravatar头像。')
    );
    $form->addInput($avatarEmail);

    $adminGender = new Typecho\Widget\Helper\Form\Element\Radio (
        'adminGender',
        array(
            'male' => _t('男（默认）'),
            'female'  => _t('女')
        ),
        'male',
        _t('主页博主性别'),
        _t('主页博主性别，默认为男。')
    );
    $form->addInput($adminGender);

    $adminLocation = new Typecho\Widget\Helper\Form\Element\Text(
        'adminLocation',
        NULL,
        '中国大陆',
        _t('主页博主地区'),
        _t('主页博主地区，默认为中国大陆。')
    );
    $form->addInput($adminLocation);

    $adminTags = new Typecho\Widget\Helper\Form\Element\Text(
        'adminTags',
        NULL,
        NULL,
        _t('主页博主标签'),
        _t('主页博主标签，用英文逗号分割。')
    );
    $form->addInput($adminTags);

    // 最近在玩
    $adminRecentPlay = new Typecho\Widget\Helper\Form\Element\Textarea(
        'adminRecentPlay',
        NULL,
        NULL,
        _t('最近在玩的游戏'),
        _t('最近在玩的游戏，以 游戏名 | 链接 | 图片链接 的形式填写，一个游戏一行。')
    );
    $form->addInput($adminRecentPlay);

    // ----------------------------全局设置----------------------------
    $gravatarPrefix = new Typecho\Widget\Helper\Form\Element\Text(
        'gravatarPrefix',
        NULL,
        'https://weavatar.com/avatar/',
        _t('Gravatar头像源'),
        _t('Gravatar头像源，默认使Weavatar。')
    );
    $form->addInput($gravatarPrefix);

    $tencentMapApiKey = new Typecho\Widget\Helper\Form\Element\Text(
        'tencentMapApiKey',
        NULL,
        NULL,
        _t('腾讯位置服务API Key'),
        _t('腾讯位置服务API Key，未填写则无法获取用户地理位置。')
    );
    $form->addInput($tencentMapApiKey);

    $customCss = new Typecho\Widget\Helper\Form\Element\Textarea(
        'customCss',
        NULL,
        NULL,
        _t('自定义css'),
        _t('自定义css，填写时无需填写style标签。')
    );
    $form->addInput($customCss);

    $customScript = new Typecho\Widget\Helper\Form\Element\Textarea(
        'customScript',
        NULL,
        NULL,
        _t('自定义script'),
        _t('自定义script，填写时无需填写script标签。')
    );
    $form->addInput($customScript);

    $favicon = new Typecho\Widget\Helper\Form\Element\Text(
        'favicon',
        NULL,
        \Utils\Helper::options()->themeUrl . '/assets/img/default-icon.ico',
        _t('网站 Favicon 设置'),
        _t('网站 Favicon，格式：图片 URL地址 或 Base64 地址')
    );
    $form->addInput($favicon);

    $sidebarAd = new Typecho\Widget\Helper\Form\Element\Textarea(
        'sidebarAd',
        NULL,
        NULL,
        _t('侧边栏广告'),
        _t('侧边栏广告。')
    );
    $form->addInput($sidebarAd);

    $sidebarBlock = new Typecho\Widget\Helper\Form\Element\Checkbox(
        'sidebarBlock',
        [
            'ShowRecentPosts'    => _t('显示最新文章'),
            'ShowRecentComments' => _t('显示最近评论')
        ],
        ['ShowRecentPosts', 'ShowRecentComments'],
        _t('侧边栏显示')
    );
    $form->addInput($sidebarBlock->multiMode());

    $postAd = new Typecho\Widget\Helper\Form\Element\Textarea(
        'postAd',
        NULL,
        NULL,
        _t('文章页广告'),
        _t('文章页广告。')
    );
    $form->addInput($postAd);

    $beian = new Typecho\Widget\Helper\Form\Element\Text(
        'beian',
        NULL,
        NULL,
        _t('备案号'),
        _t('备案号，填写文字即可，自动链接到工信部备案网站，不填写则不显示。')
    );
    $form->addInput($beian);
}

// 文章自定义字段
function themeFields($layout): void {
    $postType = new Typecho\Widget\Helper\Form\Element\Radio (
        'postType',
        array(
            'post' => _t('文章（默认）'),
            'shuoshuo'  => _t('说说')
        ),
        'post',
        _t('请选择文章类型'),
        _t('发布文章时的文章类型，默认为文章。')
    );
    $layout->addItem($postType);

    $postSticky = new Typecho\Widget\Helper\Form\Element\Radio (
        'postSticky',
        array(
            'normal' => _t('否（默认）'),
            'sticky'  => _t('是')
        ),
        'normal',
        _t('是否置顶'),
        _t('文章是否置顶，默认为否。')
    );
    $layout->addItem($postSticky);

    $description = new Typecho\Widget\Helper\Form\Element\Textarea(
        'description',
        NULL,
        NULL,
        _t('描述'),
        _t('简单一句话描述。')
    );
    $layout->addItem($description);

    $keywords = new Typecho\Widget\Helper\Form\Element\Text(
        'keywords',
        NULL,
        NULL,
        _t('关键词'),
        _t('多个关键词用英文下逗号隔开。')
    );
    $layout->addItem($keywords);

    $location = new Typecho\Widget\Helper\Form\Element\Text (
        'location',
        NULL,
        NULL,
        _t('位置'),
        _t('发布内容所在位置。')
    );
    $layout->addItem($location);

    $thumbnail = new Typecho\Widget\Helper\Form\Element\Text (
        'thumbnail',
        NULL,
        NULL,
        _t('文章略缩图'),
        _t('首页文章略缩图，若未设置则使用默认图像。')
    );
    $layout->addItem($thumbnail);
}

function themeInit($self): void {
    if ($self->request->getPathInfo() == "/reborn/api") {
        switch ($self->request->routeType) {
            case 'postLike':
                postLike($self);
                break;
            case 'postView':
                addPostView($self);
                break;
            case 'commentLike':
                commentLike($self);
                break;
        }
    }
    if (strpos($self->request->getRequestUri(), 'sitemap.xml') !== false) {
        $self->response->setStatus(200);
        $self->setThemeFile("sitemap.php");
    }
    //强制评论关闭反垃圾保护
    \Utils\Helper::options()->commentsAntiSpam = false;
    //关闭检查评论来源URL与文章链接是否一致判断
    \Utils\Helper::options()->commentsCheckReferer = false;
}