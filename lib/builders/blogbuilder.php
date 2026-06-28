<?php

namespace Sprint\Migration\Builders;

use Sprint\Migration\Exceptions\HelperException;
use Sprint\Migration\Exceptions\MigrationException;
use Sprint\Migration\Exceptions\RebuildException;
use Sprint\Migration\Locale;
use Sprint\Migration\Module;
use Sprint\Migration\VersionBuilder;

class BlogBuilder extends VersionBuilder
{
    protected function isBuilderEnabled()
    {
        return $this->getHelperManager()->Blog()->isEnabled();
    }

    protected function initialize()
    {
        $this->setGroup(Locale::getMessage('BUILDER_GROUP_Blog'));

        $this->setTitle(Locale::getMessage('BUILDER_BlogExport1'));

        $this->setDescription(implode(PHP_EOL, [
            Locale::getMessage('DEVELOPER_NAME', ['#VALUE#' => '@temi4']),
            Locale::getMessage('BUILDER_BlogExport_Info'),
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

        $groupIds = $this->addFieldAndReturn('group_ids', [
            'title'    => Locale::getMessage('BUILDER_BlogExport_group_ids'),
            'width'    => 350,
            'multiple' => 1,
            'value'    => [],
            'items'    => $this->getGroupsSelect(),
        ]);

        $blogIds = $this->addFieldAndReturn('blog_ids', [
            'title'    => Locale::getMessage('BUILDER_BlogExport_blog_ids'),
            'width'    => 350,
            'multiple' => 1,
            'value'    => [],
            'items'    => $this->getBlogsSelect(),
        ]);

        $groups = $helper->Blog()->exportGroups($groupIds);
        $blogs = $helper->Blog()->exportBlogs($blogIds);

        if (empty($groups) && empty($blogs)) {
            $this->rebuildField('group_ids');
        }

        $this->createVersionFile(
            Module::getModuleTemplateFile('BlogExport'),
            [
                'groups' => $groups,
                'blogs'  => $blogs,
            ]
        );
    }

    protected function getGroupsSelect(): array
    {
        $items = array_map(function ($item) {
            $item['TITLE'] = '[' . $item['SITE_ID'] . '] ' . $item['NAME'];
            return $item;
        }, $this->getHelperManager()->Blog()->getGroups());

        return $this->createSelectWithGroups($items, 'ID', 'TITLE', 'SITE_ID');
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
