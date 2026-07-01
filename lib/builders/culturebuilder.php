<?php

namespace Sprint\Migration\Builders;

use Sprint\Migration\Exceptions\HelperException;
use Sprint\Migration\Exceptions\MigrationException;
use Sprint\Migration\Exceptions\RebuildException;
use Sprint\Migration\Locale;
use Sprint\Migration\Module;
use Sprint\Migration\VersionBuilder;

class CultureBuilder extends VersionBuilder
{
    protected function isBuilderEnabled()
    {
        return $this->getHelperManager()->Culture()->isEnabled();
    }

    protected function initialize()
    {
        $this->setGroup(Locale::getMessage('BUILDER_GROUP_Main'));

        $this->setTitle(implode(' ', [
            Locale::getMessage('BUILDER_CultureExport1'),
            Locale::getMessage('DEVELOPER_LABEL'),
        ]));

        $this->setDescription(implode(PHP_EOL, [
            Locale::getMessage('DEVELOPER_NAME', ['#VALUE#' => '@yanochka_dev']),
            Locale::getMessage('BUILDER_CultureExport_Info')
        ]));

        $this->addVersionFields();
    }

    /**
     * @throws RebuildException
     * @throws HelperException
     * @throws MigrationException
     */
    protected function execute()
    {
        $helper = $this->getHelperManager();

        $cultureItems = $helper->Culture()->exportCultures();

        $cultureCodes = $this->addFieldAndReturn(
            'culture_codes',
            [
                'title'       => Locale::getMessage('BUILDER_CultureExport_culture_codes'),
                'placeholder' => '',
                'multiple'    => 1,
                'value'       => [],
                'width'       => 250,
                'select'      => $this->createSelect(
                    $cultureItems,
                    'CODE',
                    'NAME',
                    true
                ),
            ]
        );

        $exportItems = array_filter(
            $cultureItems,
            fn($item) => in_array($item['CODE'], $cultureCodes)
        );

        $this->createVersionFile(
            Module::getModuleTemplateFile('CultureExport'),
            [
                'items' => $exportItems,
            ]
        );
    }

}
