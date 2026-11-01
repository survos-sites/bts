<?php

declare(strict_types=1);

namespace App\Menu;

use Survos\MenuBundle\Attribute\AsMenuListener;
use Survos\MenuBundle\Event\MenuEvent;
use Survos\MenuBundle\MenuSlot;
use Survos\TablerBundle\Menu\MenuBuilderTrait;
use Symfony\Component\Translation\LocaleSwitcher;

final class AppMenu
{
    use MenuBuilderTrait;

    public function __construct(private readonly LocaleSwitcher $localeSwitcher)
    {
    }

    #[AsMenuListener(MenuSlot::NAVBAR_MENU)]
    public function navigation(MenuEvent $event): void
    {
        $menu = $event->getMenu();
        $locale = $this->localeSwitcher->getLocale();
        $parameters = ['_locale' => $locale];

        $this->add($menu, 'app_homepage', $parameters, label: 'app_homepage', icon: 'tabler:home', translationDomain: 'routing');
        $this->add($menu, 'app_catalog_search', $parameters, label: 'app_catalog_search', icon: 'tabler:search', translationDomain: 'routing');

        $api = $this->addSubmenu($menu, 'api', 'tabler:code', translationDomain: 'routing');
        $this->add($api, '_api_/amsts{._format}_get_collection', ['_format' => 'jsonld'], label: 'api_collection', translationDomain: 'routing');
        $this->add($api, 'api_doc', ['_format' => 'html'], label: 'api_doc', translationDomain: 'routing');

        $sourceLocale = 'nl' === $locale ? 'nl' : 'en';
        $sources = $this->addSubmenu($menu, 'sources', 'tabler:book', translationDomain: 'routing');
        $this->add($sources, uri: 'https://belowthesurface.amsterdam/'.$sourceLocale, label: 'source_project', translationDomain: 'routing');
        $this->add($sources, uri: 'https://belowthesurface.amsterdam/'.$sourceLocale.'/pagina/publicaties-en-datasets', label: 'source_datasets', translationDomain: 'routing');
        $this->add($sources, uri: 'https://statics.belowthesurface.amsterdam/downloadbare-datasets/Downloadtabel_NL.csv', label: 'source_csv_nl', translationDomain: 'routing');
        $this->add($sources, uri: 'https://statics.belowthesurface.amsterdam/downloadbare-datasets/Downloadtabel_EN.csv', label: 'source_csv_en', translationDomain: 'routing');
        $this->add($sources, uri: 'https://belowthesurface.amsterdam/'.$sourceLocale.'/pagina/disclaimer', label: 'source_terms', translationDomain: 'routing');

        $code = $this->addSubmenu($menu, 'code', 'tabler:brand-github', translationDomain: 'routing');
        $this->add($code, uri: 'https://github.com/survos-sites/BelowTheSurface', label: 'project_github', translationDomain: 'routing');
        $this->add($code, uri: 'https://github.com/paulinevos/symfonycon-2025', label: 'related_demo', translationDomain: 'routing');
    }

    #[AsMenuListener(MenuSlot::ADMIN_NAVBAR_MENU)]
    public function administration(MenuEvent $event): void
    {
        $this->add($event->getMenu(), 'meili_admin', ['_locale' => $this->localeSwitcher->getLocale()], label: 'meili_admin', icon: 'tabler:edit', translationDomain: 'routing');
    }
}
