<?php
namespace Lukasbableck\ContaoBetterElementgroupsBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Lukasbableck\ContaoBetterElementgroupsBundle\ContaoBetterElementgroupsBundle;

class Plugin implements BundlePluginInterface {
	public function getBundles(ParserInterface $parser): array {
		return [BundleConfig::create(ContaoBetterElementgroupsBundle::class)->setLoadAfter([ContaoCoreBundle::class])];
	}
}
