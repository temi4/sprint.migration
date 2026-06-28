<?php

namespace Sprint\Migration\Helpers;

use CBlog;
use CBlogGroup;
use CBlogUserGroup;
use Sprint\Migration\Exceptions\HelperException;
use Sprint\Migration\Helper;
use Sprint\Migration\HelperManager;
use Sprint\Migration\Locale;

class BlogHelper extends Helper
{
    public function isEnabled(): bool
    {
        return $this->checkModules(['blog']);
    }

    public function getGroups(array $filter = []): array
    {
        return $this->fetchAll(CBlogGroup::GetList(
            [
                'SITE_ID' => 'ASC',
                'NAME'    => 'ASC',
                'ID'      => 'ASC',
            ],
            $filter,
            false,
            false,
            ['ID', 'SITE_ID', 'NAME']
        ));
    }

    public function getBlogs(array $filter = []): array
    {
        return $this->fetchAll(CBlog::GetList(
            [
                'GROUP_SITE_ID' => 'ASC',
                'GROUP_NAME'    => 'ASC',
                'NAME'          => 'ASC',
                'ID'            => 'ASC',
            ],
            $filter,
            false,
            false,
            [
                'ID',
                'NAME',
                'URL',
                'GROUP_ID',
                'GROUP_NAME',
                'GROUP_SITE_ID',
                'OWNER_ID',
                'OWNER_LOGIN',
                'ACTIVE',
            ]
        ));
    }

    /**
     * @throws HelperException
     */
    public function exportGroupById(int $groupId): array
    {
        $group = $this->getGroupById($groupId);

        return $this->export(
            $group,
            $this->getDefaultGroup(),
            ['ID']
        );
    }

    /**
     * @throws HelperException
     */
    public function exportGroups(array $groupIds): array
    {
        return array_map(
            fn($groupId) => $this->exportGroupById((int)$groupId),
            $this->makeNonEmptyArray($groupIds)
        );
    }

    /**
     * @throws HelperException
     */
    public function exportBlogById(int $blogId): array
    {
        $blog = $this->getBlogById($blogId);
        $group = $this->getGroupById((int)$blog['GROUP_ID']);

        if ((int)($blog['SOCNET_GROUP_ID'] ?? 0) > 0) {
            throw new HelperException("Socialnetwork blog \"$blogId\" is not supported");
        }

        $blog['GROUP'] = $this->export(
            $group,
            $this->getDefaultGroup(),
            ['ID']
        );

        $blog['OWNER_LOGIN'] = HelperManager::getInstance()
            ->User()
            ->getUserLoginById((int)$blog['OWNER_ID']);

        $blog['USER_GROUPS'] = $this->exportUserGroups($blog);

        return $this->prepareExportBlog($blog);
    }

    /**
     * @throws HelperException
     */
    public function exportBlogs(array $blogIds): array
    {
        return array_map(
            fn($blogId) => $this->exportBlogById((int)$blogId),
            $this->makeNonEmptyArray($blogIds)
        );
    }

    /**
     * @throws HelperException
     */
    public function saveGroup(array $fields): int
    {
        $this->checkRequiredKeys($fields, ['SITE_ID', 'NAME']);

        $fields = $this->prepareGroupFields($fields);
        $groupId = $this->getGroupId($fields['SITE_ID'], $fields['NAME']);

        if (!$groupId) {
            return $this->addGroup($fields);
        }

        $exists = $this->export($this->getGroupById($groupId), $this->getDefaultGroup(), ['ID']);
        $export = $this->export($fields, $this->getDefaultGroup(), []);
        if ($this->checkDiff($exists, $export)) {
            return $this->updateGroup($groupId, $fields);
        }

        return $groupId;
    }

    /**
     * @throws HelperException
     */
    public function saveBlog(int $groupId, array $fields): int
    {
        $this->checkRequiredKeys($fields, ['NAME', 'URL', 'OWNER_LOGIN']);

        if (empty($this->getGroupById($groupId))) {
            throw new HelperException("Blog group \"$groupId\" not found");
        }

        $hasUserGroups = array_key_exists('USER_GROUPS', $fields);
        $blogId = $this->getBlogId($fields['URL']);
        $fieldsForSave = $this->prepareBlogFieldsForSave($groupId, $fields);

        if (!$blogId) {
            $blogId = $this->addBlog($fieldsForSave);
            if ($hasUserGroups) {
                $this->saveUserGroups($blogId, $fields['USER_GROUPS']);
            }
            return $blogId;
        }

        $exists = $this->exportBlogById($blogId);
        $export = $this->prepareExportBlog(array_merge($fieldsForSave, [
            'GROUP'        => $this->export($this->getGroupById($groupId), $this->getDefaultGroup(), ['ID']),
            'OWNER_LOGIN'  => $fields['OWNER_LOGIN'],
            'USER_GROUPS'  => $fields['USER_GROUPS'] ?? [],
        ]));

        if (!$hasUserGroups) {
            unset($exists['USER_GROUPS'], $export['USER_GROUPS']);
        }

        if ($this->checkDiff($exists, $export)) {
            $blogId = $this->updateBlog($blogId, $fieldsForSave);
        }

        if ($hasUserGroups) {
            $this->saveUserGroups($blogId, $fields['USER_GROUPS']);
        }

        return $blogId;
    }

    public function getGroupId(string $siteId, string $name): int
    {
        $group = CBlogGroup::GetList(
            ['ID' => 'ASC'],
            [
                'SITE_ID' => $siteId,
                'NAME'    => $name,
            ],
            false,
            false,
            ['ID']
        )->Fetch();

        return (int)($group['ID'] ?? 0);
    }

    public function getBlogId(string $url): int
    {
        $blog = CBlog::GetList(
            ['ID' => 'ASC'],
            ['URL' => $url],
            false,
            false,
            ['ID']
        )->Fetch();

        return (int)($blog['ID'] ?? 0);
    }

    /**
     * @throws HelperException
     */
    public function getGroupById(int $groupId): array
    {
        $group = CBlogGroup::GetList(
            ['ID' => 'ASC'],
            ['ID' => $groupId],
            false,
            false,
            ['ID', 'SITE_ID', 'NAME']
        )->Fetch();
        if (empty($group)) {
            throw new HelperException("Blog group \"$groupId\" not found");
        }

        return $this->filterKeys($group, array_keys($this->getDefaultGroup()));
    }

    /**
     * @throws HelperException
     */
    public function getBlogById(int $blogId): array
    {
        $blog = CBlog::GetList(
            ['ID' => 'ASC'],
            ['ID' => $blogId],
            false,
            false,
            $this->getBlogKeys()
        )->Fetch();
        if (empty($blog)) {
            throw new HelperException("Blog \"$blogId\" not found");
        }

        return $this->filterKeys($blog, $this->getBlogKeys());
    }

    /**
     * @throws HelperException
     */
    protected function addGroup(array $fields): int
    {
        $groupId = CBlogGroup::Add($fields);
        if ($groupId) {
            $this->outNotice(Locale::getMessage('BLOG_GROUP_UPDATED', ['#NAME#' => $fields['NAME']]));
            return (int)$groupId;
        }

        $this->throwApplicationExceptionIfExists();
        throw new HelperException("Blog group \"{$fields['NAME']}\" not added");
    }

    /**
     * @throws HelperException
     */
    protected function updateGroup(int $groupId, array $fields): int
    {
        $result = CBlogGroup::Update($groupId, $fields);
        if ($result) {
            $this->outNotice(Locale::getMessage('BLOG_GROUP_UPDATED', ['#NAME#' => $fields['NAME']]));
            return $groupId;
        }

        $this->throwApplicationExceptionIfExists();
        throw new HelperException("Blog group \"{$fields['NAME']}\" not updated");
    }

    /**
     * @throws HelperException
     */
    protected function addBlog(array $fields): int
    {
        $blogId = CBlog::Add($this->prepareBlogFieldsForAdd($fields));
        if ($blogId) {
            $this->outNotice(Locale::getMessage('BLOG_UPDATED', ['#NAME#' => $fields['NAME']]));
            return (int)$blogId;
        }

        $this->throwApplicationExceptionIfExists();
        throw new HelperException("Blog \"{$fields['NAME']}\" not added");
    }

    /**
     * @throws HelperException
     */
    protected function updateBlog(int $blogId, array $fields): int
    {
        $result = CBlog::Update($blogId, $this->prepareBlogFieldsForUpdate($fields));
        if ($result) {
            $this->outNotice(Locale::getMessage('BLOG_UPDATED', ['#NAME#' => $fields['NAME']]));
            return $blogId;
        }

        $this->throwApplicationExceptionIfExists();
        throw new HelperException("Blog \"{$fields['NAME']}\" not updated");
    }

    protected function prepareGroupFields(array $fields): array
    {
        return array_intersect_key(
            $fields,
            array_flip(array_keys($this->getDefaultGroup()))
        );
    }

    /**
     * @throws HelperException
     */
    protected function prepareBlogFieldsForSave(int $groupId, array $fields): array
    {
        $fields = array_merge($this->getDefaultBlog(), $fields);
        $fields['GROUP_ID'] = $groupId;
        $fields['OWNER_ID'] = HelperManager::getInstance()
            ->User()
            ->getUserIdByLogin((string)$fields['OWNER_LOGIN']);

        $this->unsetKeys($fields, [
            'GROUP',
            'OWNER_LOGIN',
            'USER_GROUPS',
        ]);

        return array_intersect_key(
            $fields,
            array_flip($this->getBlogSaveKeys())
        );
    }

    protected function prepareBlogFieldsForAdd(array $fields): array
    {
        global $DB;

        if (empty($fields['=DATE_CREATE'])) {
            $fields['=DATE_CREATE'] = $DB->CurrentTimeFunction();
        }

        if (empty($fields['=DATE_UPDATE'])) {
            $fields['=DATE_UPDATE'] = $DB->CurrentTimeFunction();
        }

        return $fields;
    }

    protected function prepareBlogFieldsForUpdate(array $fields): array
    {
        global $DB;

        if (empty($fields['=DATE_UPDATE'])) {
            $fields['=DATE_UPDATE'] = $DB->CurrentTimeFunction();
        }

        return $fields;
    }

    protected function prepareExportBlog(array $fields): array
    {
        $fields = array_merge($this->getDefaultBlog(), $fields);
        $fields = $this->normalizeUserGroups($fields);

        return $this->export(
            $fields,
            $this->getDefaultBlog(),
            [
                'ID',
                'GROUP_ID',
                'OWNER_ID',
                'SOCNET_GROUP_ID',
                'DATE_CREATE',
                'DATE_UPDATE',
                'LAST_POST_ID',
                'LAST_POST_DATE',
                'AUTO_GROUPS',
            ]
        );
    }

    protected function normalizeUserGroups(array $fields): array
    {
        if (isset($fields['USER_GROUPS']) && is_array($fields['USER_GROUPS'])) {
            $fields['USER_GROUPS'] = array_values(array_map(function ($item) {
                return array_intersect_key(
                    array_merge([
                        'AUTO'          => 'N',
                        'PERMS_POST'    => false,
                        'PERMS_COMMENT' => false,
                    ], $item),
                    array_flip(['NAME', 'AUTO', 'PERMS_POST', 'PERMS_COMMENT'])
                );
            }, $fields['USER_GROUPS']));
        }

        return $fields;
    }

    protected function exportUserGroups(array $blog): array
    {
        $autoGroupIds = $this->unserializeIds($blog['AUTO_GROUPS'] ?? '');
        $groups = [
            1 => CBlogUserGroup::GetByID(1),
            2 => CBlogUserGroup::GetByID(2),
        ];

        $dbres = CBlogUserGroup::GetList(
            ['ID' => 'ASC'],
            ['BLOG_ID' => (int)$blog['ID']],
            false,
            false,
            ['ID', 'BLOG_ID', 'NAME']
        );
        while ($group = $dbres->Fetch()) {
            $groups[(int)$group['ID']] = $group;
        }

        $result = [];
        foreach ($groups as $groupId => $group) {
            if (empty($group['NAME'])) {
                continue;
            }

            $item = [
                'NAME'          => $group['NAME'],
                'AUTO'          => in_array((int)$groupId, $autoGroupIds, true) ? 'Y' : 'N',
                'PERMS_POST'    => CBlogUserGroup::GetGroupPerms((int)$groupId, (int)$blog['ID'], 0, BLOG_PERMS_POST),
                'PERMS_COMMENT' => CBlogUserGroup::GetGroupPerms((int)$groupId, (int)$blog['ID'], 0, BLOG_PERMS_COMMENT),
            ];

            if ($groupId <= 2 && $item['AUTO'] === 'N' && $item['PERMS_POST'] === false && $item['PERMS_COMMENT'] === false) {
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    protected function saveUserGroups(int $blogId, array $items, bool $deleteOldGroups = true): void
    {
        $items = $this->normalizeUserGroups(['USER_GROUPS' => $items])['USER_GROUPS'];
        $currentGroups = $this->getUserGroups($blogId);
        $updatedIds = [];
        $autoGroupIds = [];

        foreach ($items as $item) {
            if (empty($item['NAME'])) {
                continue;
            }

            $groupId = $this->getUserGroupId($blogId, $item['NAME']);

            if (!$groupId) {
                $groupId = (int)CBlogUserGroup::Add([
                    'BLOG_ID' => $blogId,
                    'NAME'    => $item['NAME'],
                ]);
            }

            if (!$groupId) {
                continue;
            }

            if ($item['PERMS_POST'] !== false) {
                CBlogUserGroup::SetGroupPerms($groupId, $blogId, 0, $item['PERMS_POST'], BLOG_PERMS_POST);
            }

            if ($item['PERMS_COMMENT'] !== false) {
                CBlogUserGroup::SetGroupPerms($groupId, $blogId, 0, $item['PERMS_COMMENT'], BLOG_PERMS_COMMENT);
            }

            if ($item['AUTO'] === 'Y') {
                $autoGroupIds[] = $groupId;
            }

            $updatedIds[] = $groupId;
        }

        if ($deleteOldGroups) {
            foreach ($currentGroups as $currentGroup) {
                $currentGroupId = (int)$currentGroup['ID'];
                if ($currentGroupId > 2 && !in_array($currentGroupId, $updatedIds, true)) {
                    CBlogUserGroup::Delete($currentGroupId);
                }
            }
        }

        CBlog::Update($blogId, [
            'AUTO_GROUPS' => !empty($autoGroupIds) ? serialize($autoGroupIds) : '',
        ]);
    }

    protected function getUserGroups(int $blogId): array
    {
        $groups = [];
        foreach ([1, 2] as $groupId) {
            $group = CBlogUserGroup::GetByID($groupId);
            if (!empty($group)) {
                $groups[$groupId] = $group;
            }
        }

        $dbres = CBlogUserGroup::GetList(
            ['ID' => 'ASC'],
            ['BLOG_ID' => $blogId],
            false,
            false,
            ['ID', 'BLOG_ID', 'NAME']
        );
        while ($group = $dbres->Fetch()) {
            $groups[(int)$group['ID']] = $group;
        }

        return $groups;
    }

    protected function getUserGroupId(int $blogId, string $name): int
    {
        foreach ($this->getUserGroups($blogId) as $group) {
            if ($group['NAME'] === $name) {
                return (int)$group['ID'];
            }
        }

        return 0;
    }

    protected function unserializeIds(mixed $value): array
    {
        if (empty($value) || !is_string($value)) {
            return [];
        }

        $ids = @unserialize($value, ['allowed_classes' => false]);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', $ids));
    }

    protected function filterKeys(array $item, array $keys): array
    {
        return array_intersect_key($item, array_flip($keys));
    }

    protected function getDefaultGroup(): array
    {
        return [
            'SITE_ID' => '',
            'NAME'    => '',
        ];
    }

    protected function getDefaultBlog(): array
    {
        return [
            'GROUP'             => [],
            'OWNER_LOGIN'       => '',
            'NAME'              => '',
            'DESCRIPTION'       => '',
            'ACTIVE'            => 'Y',
            'URL'               => '',
            'REAL_URL'          => '',
            'ENABLE_COMMENTS'   => 'Y',
            'ENABLE_IMG_VERIF'  => 'N',
            'EMAIL_NOTIFY'      => 'Y',
            'ENABLE_RSS'        => 'Y',
            'ALLOW_HTML'        => 'N',
            'SEARCH_INDEX'      => 'Y',
            'USE_SOCNET'        => 'N',
            'EDITOR_USE_FONT'   => 'N',
            'EDITOR_USE_LINK'   => 'N',
            'EDITOR_USE_IMAGE'  => 'N',
            'EDITOR_USE_FORMAT' => 'N',
            'EDITOR_USE_VIDEO'  => 'N',
            'USER_GROUPS'       => [],
        ];
    }

    protected function getBlogKeys(): array
    {
        return [
            'ID',
            'NAME',
            'DESCRIPTION',
            'DATE_CREATE',
            'DATE_UPDATE',
            'ACTIVE',
            'OWNER_ID',
            'SOCNET_GROUP_ID',
            'URL',
            'REAL_URL',
            'GROUP_ID',
            'ENABLE_COMMENTS',
            'ENABLE_IMG_VERIF',
            'EMAIL_NOTIFY',
            'ENABLE_RSS',
            'LAST_POST_ID',
            'LAST_POST_DATE',
            'AUTO_GROUPS',
            'ALLOW_HTML',
            'SEARCH_INDEX',
            'USE_SOCNET',
            'EDITOR_USE_FONT',
            'EDITOR_USE_LINK',
            'EDITOR_USE_IMAGE',
            'EDITOR_USE_FORMAT',
            'EDITOR_USE_VIDEO',
        ];
    }

    protected function getBlogSaveKeys(): array
    {
        return [
            'NAME',
            'DESCRIPTION',
            'ACTIVE',
            'OWNER_ID',
            'URL',
            'REAL_URL',
            'GROUP_ID',
            'ENABLE_COMMENTS',
            'ENABLE_IMG_VERIF',
            'EMAIL_NOTIFY',
            'ENABLE_RSS',
            'ALLOW_HTML',
            'SEARCH_INDEX',
            'USE_SOCNET',
            'EDITOR_USE_FONT',
            'EDITOR_USE_LINK',
            'EDITOR_USE_IMAGE',
            'EDITOR_USE_FORMAT',
            'EDITOR_USE_VIDEO',
        ];
    }
}
