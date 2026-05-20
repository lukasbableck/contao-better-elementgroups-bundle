<?php
namespace Lukasbableck\ContaoBetterElementgroupsBundle\DataContainer;

use Contao\Backend;
use Contao\ContentModel;
use Contao\CoreBundle\DataContainer\DataContainerOperationsBuilder;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\String\HtmlAttributes;
use Contao\DataContainer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class DecoratedDataContainerOperationsBuilder extends DataContainerOperationsBuilder {
	public function __construct(
		ContaoFramework $framework,
		private readonly Environment $twig,
		private readonly Security $security,
		private readonly UrlGeneratorInterface $urlGenerator,
	) {
		parent::__construct($framework, $twig, $security, $urlGenerator);
	}

	public function initializeWithButtons(string $table, array $record, DataContainer $dataContainer, ?callable $legacyCallback = null): DataContainerOperationsBuilder {
		$builder = parent::initializeWithButtons($table, $record, $dataContainer, $legacyCallback);
        if(($record['ptable'] ?? '') !== 'tl_content') {
            return $builder;
        }
		foreach ($builder->operations as $operation) {
			if (!\array_key_exists('attributes', $operation) || !$operation['attributes'] || ($operation['attributes']['onclick'] ?? false) || ($operation['method'] ?? 'GET') !== 'GET') {
				continue;
			}

			$operation['attributes']['onclick'] = "Backend.openModalIframe({title:'".str_replace("'", "\\'", \sprintf($operation['label'][1] ?? '%s', $record['id']))."', url:this.href+'&popup=1&nb=1'});return false";
		}

		return $builder;
	}

	public function addNewButton(string $mode, string $table, int $pid, ?int $id = null): self {
		[$label, $title] = $this->getLabelAndTitle($table, 'pastenew'.$mode, $pid);

		$operation = [
			'label' => $label,
			'title' => $title,
			'attributes' => (new HtmlAttributes($GLOBALS['TL_DCA'][$table]['list']['operations']['new']['attributes'] ?? null))->set('data-action', 'contao--scroll-offset#store'),
			'icon' => $GLOBALS['TL_DCA'][$table]['list']['operations']['new']['icon'] ?? 'new.svg',
			'href' => $this->getNewHref($mode, $pid, $id),
			'method' => $GLOBALS['TL_DCA'][$table]['list']['operations']['new']['method'] ?? 'POST',
			'primary' => $GLOBALS['TL_DCA'][$table]['list']['operations']['new']['primary'] ?? false,
		];

		if ($table != 'tl_content') {
			$this->append($operation);

			return $this;
		}

		$contentElement = ContentModel::findById($pid);
		if (!$contentElement || $contentElement->ptable != 'tl_content') {
			$this->append($operation);

			return $this;
		}

		$operation['href'] = Backend::addToUrl($operation['href'].'&ptable=tl_content');
		$this->append($operation);

		return $this;
	}
}
