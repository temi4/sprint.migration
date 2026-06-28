<?php

namespace Sprint\Migration\Builders;

use Sprint\Migration\Exceptions\HelperException;
use Sprint\Migration\Exceptions\MigrationException;
use Sprint\Migration\Exceptions\RebuildException;
use Sprint\Migration\Locale;
use Sprint\Migration\Module;
use Sprint\Migration\VersionBuilder;

class BlogPostBuilder extends VersionBuilder
{
    protected function isBuilderEnabled()
    {
        return $this->getHelperManager()->Blog()->isEnabled();
    }

    protected function initialize()
    {
        $this->setGroup(Locale::getMessage('BUILDER_GROUP_Blog'));
        $this->setTitle(implode(' ', [
            Locale::getMessage('BUILDER_BlogPostExport1'),
            Locale::getMessage('DEVELOPER_LABEL'),
        ]));

        $this->setDescription(implode(PHP_EOL, [
            Locale::getMessage('DEVELOPER_NAME', ['#VALUE#' => '@temi4']),
            Locale::getMessage('BUILDER_BlogPostExport_Info'),
        ]));

        $this->addVersionFields();
    }

    /**
     * @throws HelperException
     * @throws MigrationException
     * @throws RebuildException
     */
    protected function execute()
    {
        $helper = $this->getHelperManager();
        $blogId = (int)$this->addFieldAndReturn('blog_id', [
            'title' => Locale::getMessage('BUILDER_BlogPostExport_blog_id'),
            'width' => 350,
            'items' => $this->getBlogsSelect(),
        ]);

        $blog = $helper->Blog()->exportBlogById($blogId);
        $blogRef = [
            'GROUP' => $blog['GROUP'],
            'URL'   => $blog['URL'],
        ];

        $version = $this->getVersionName();
        $exchangeDir = $this->getVersionConfig()->getVersionExchangeDir($version);

        $postIds = $this->getPostIds($blogId);
        $posts = $helper->Blog()->exportPosts($postIds, $exchangeDir);

        if (empty($posts)) {
            $this->rebuildField('post_filter_mode');
        }

        $this->createVersionFile(
            Module::getModuleTemplateFile('BlogPostExport'),
            [
                'version' => $version,
                'blog'    => $blogRef,
                'posts'   => $posts,
            ]
        );
    }

    /**
     * @throws RebuildException
     */
    protected function getPostIds(int $blogId): array
    {
        $mode = $this->addFieldAndReturn('post_filter_mode', [
            'title' => Locale::getMessage('BUILDER_BlogPostExport_filter'),
            'width' => 250,
            'select' => [
                [
                    'title' => Locale::getMessage('BUILDER_SelectAll'),
                    'value' => 'all',
                ],
                [
                    'title' => Locale::getMessage('BUILDER_BlogPostExport_SelectSomeId'),
                    'value' => 'list_id',
                ],
                [
                    'title' => Locale::getMessage('BUILDER_BlogPostExport_SelectSomeCode'),
                    'value' => 'list_code',
                ],
            ],
        ]);

        if ($mode === 'list_id') {
            $ids = $this->addFieldAndReturn('post_filter_list_id', [
                'title' => Locale::getMessage('BUILDER_BlogPostExport_FilterListId'),
                'width' => 350,
                'height' => 40,
            ]);

            return array_map('intval', $this->explodeString($ids));
        }

        $filter = [];
        if ($mode === 'list_code') {
            $codes = $this->addFieldAndReturn('post_filter_list_code', [
                'title' => Locale::getMessage('BUILDER_BlogPostExport_FilterListCode'),
                'width' => 350,
                'height' => 40,
            ]);

            $codes = $this->explodeString($codes);
            if (empty($codes)) {
                return [];
            }

            $filter['@CODE'] = $codes;
        }

        return array_column($this->getHelperManager()->Blog()->getPosts($blogId, $filter), 'ID');
    }

    protected function getBlogsSelect(): array
    {
        $items = array_map(function ($item) {
            $item['GROUP_TITLE'] = '[' . $item['GROUP_SITE_ID'] . '] ' . $item['GROUP_NAME'];
            $item['TITLE'] = '[' . $item['URL'] . '] ' . $item['NAME'];
            return $item;
        }, $this->getHelperManager()->Blog()->getBlogs());

        return $this->createSelectWithGroups($items, 'ID', 'TITLE', 'GROUP_TITLE');
    }
}
