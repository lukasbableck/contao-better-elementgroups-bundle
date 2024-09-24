<?php

namespace Lukasbableck\ContaoBetterElementgroupsBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\ContentModel;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\DataContainer;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;

#[AsCallback(table: 'tl_content', target: 'list.sorting.child_record')]
class ContentChildRecordListener {
	public function __construct(private ContaoFramework $framework) {
		$this->framework = $framework;
	}

	public function __invoke(array $row): string {
		$orig = $this->framework->getAdapter(System::class)->importStatic('tl_content')->{'addCteType'}($row);

		if ($row['type'] == 'element_group') {
			$objModel = new ContentModel();
			$objModel->setRow($row);

			$preview = '<html>';
			$arrColumns = ['tl_content.pid=? AND tl_content.ptable=?'];
			$children = ContentModel::findBy($arrColumns, [$objModel->id, 'tl_content'], ['order' => 'sorting']);
			if (is_iterable($children)) {
				foreach ($children as $child) {
					dump($child);
					$buttons = $this->generateButtons($child->row(), 'tl_content');
					$preview .= '
					<div class="tl_content click2edit toggle_select">
						<div class="inside hover-div">
							<div class="tl_content_right">
								'.$buttons.'
							</div>
							'.$this->__invoke($child->row()).'
						</div>
					</div>';
				}
			}

			if ($preview == '') {
				return $orig;
			}

			$preview .= '</html>';

			$dom = new \DOMDocument();
			$dom->loadHTML('<html>'.$orig.'</html>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
			$xpath = new \DOMXPath($dom);
			$node = $xpath->query('//div[@class="cte_preview"]')->item(0);

			$domPreview = new \DOMDocument();
			$domPreview->loadHTML($preview, \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);
			while ($node->hasChildNodes()) {
				$node->removeChild($node->firstChild);
			}

			$node->appendChild($dom->importNode($domPreview->documentElement, true));
			$orig = $dom->saveHTML();
			$orig = str_replace(['<html>', '</html>', '<body>', '</body>'], '', $orig);
			$GLOBALS['TL_CSS'][] = 'bundles/contaobetterelementgroups/backend.css';
		}

		return $orig;
	}

	protected function generateButtons($arrRow, $strTable, $arrRootIds = [], $blnCircularReference = false, $arrChildRecordIds = null, $strPrevious = null, $strNext = null) {
		if (!\is_array($GLOBALS['TL_DCA'][$strTable]['list']['operations'] ?? null)) {
			return '';
		}

		$return = '';

		foreach ($GLOBALS['TL_DCA'][$strTable]['list']['operations'] as $k => $v) {
			$v = \is_array($v) ? $v : [$v];

			$dataContainer = DataContainer::getDriverForTable($strTable);
			$dc = (new \ReflectionClass($dataContainer))->newInstanceWithoutConstructor();
			$config = new DataContainerOperation($k, $v, $arrRow, $dc);

			// Call a custom function instead of using the default button
			if (\is_array($v['button_callback'] ?? null)) {
				$callback = System::importStatic($v['button_callback'][0]);
				$ref = new \ReflectionMethod($callback, $v['button_callback'][1]);

				if ($ref->getNumberOfParameters() === 1 && ($type = $ref->getParameters()[0]->getType()) && $type->getName() === DataContainerOperation::class) {
					$callback->{$v['button_callback'][1]}($config);
				} else {
					$return .= $callback->{$v['button_callback'][1]}($arrRow, $config['href'] ?? null, $config['label'], $config['title'], $config['icon'] ?? null, $config['attributes'], $strTable, $arrRootIds, $arrChildRecordIds, $blnCircularReference, $strPrevious, $strNext, $this);
					continue;
				}
			} elseif (\is_callable($v['button_callback'] ?? null)) {
				$ref = new \ReflectionFunction($v['button_callback']);

				if ($ref->getNumberOfParameters() === 1 && ($type = $ref->getParameters()[0]->getType()) && $type->getName() === DataContainerOperation::class) {
					$v['button_callback']($config);
				} else {
					$return .= $v['button_callback']($arrRow, $config['href'] ?? null, $config['label'], $config['title'], $config['icon'] ?? null, $config['attributes'], $strTable, $arrRootIds, $arrChildRecordIds, $blnCircularReference, $strPrevious, $strNext, $this);
					continue;
				}
			}

			if (($html = $config->getHtml()) !== null) {
				$return .= $html;
				continue;
			}

			$isPopup = $k == 'show';
			$href = null;

			if ($config->getUrl() !== null) {
				$href = $config->getUrl();
			} elseif (!empty($config['route'])) {
				$params = ['id' => $arrRow['id']];

				if ($isPopup) {
					$params['popup'] = '1';
				}

				$href = System::getContainer()->get('router')->generate($config['route'], $params);
			} elseif (isset($config['href'])) {
				$href = Backend::addToUrl($config['href'].'&amp;id='.$arrRow['id'].(Input::get('nb') ? '&amp;nc=1' : '').($isPopup ? '&amp;popup=1' : ''));
			}

			parse_str(StringUtil::decodeEntities($config['href'] ?? $v['href'] ?? ''), $params);

			if (($params['act'] ?? null) == 'toggle' && isset($params['field'])) {
				// Hide the toggle icon if the user does not have access to the field
				if ((($GLOBALS['TL_DCA'][$strTable]['fields'][$params['field']]['toggle'] ?? false) !== true && ($GLOBALS['TL_DCA'][$strTable]['fields'][$params['field']]['reverseToggle'] ?? false) !== true) || (DataContainer::isFieldExcluded($strTable, $params['field']) && !System::getContainer()->get('security.helper')->isGranted(ContaoCorePermissions::USER_CAN_EDIT_FIELD_OF_TABLE, $strTable.'::'.$params['field']))) {
					continue;
				}

				$icon = $config['icon'];
				$_icon = pathinfo($config['icon'], \PATHINFO_FILENAME).'_.'.pathinfo($config['icon'], \PATHINFO_EXTENSION);

				if (str_contains($config['icon'], '/')) {
					$_icon = \dirname($config['icon']).'/'.$_icon;
				}

				if ($icon == 'visible.svg') {
					$_icon = 'invisible.svg';
				} elseif ($icon == 'featured.svg') {
					$_icon = 'unfeatured.svg';
				}

				$state = $arrRow[$params['field']] ? 1 : 0;

				if (($config['reverse'] ?? false) || ($GLOBALS['TL_DCA'][$strTable]['fields'][$params['field']]['reverseToggle'] ?? false)) {
					$state = $arrRow[$params['field']] ? 0 : 1;
				}

				if ($href === null) {
					$return .= Image::getHtml($config['icon'], $config['label']).' ';
				} else {
					if (isset($config['titleDisabled'])) {
						$titleDisabled = $config['titleDisabled'];
					} else {
						$titleDisabled = (\is_array($v['label']) && isset($v['label'][2])) ? \sprintf($v['label'][2], $arrRow['id']) : $config['title'];
					}

					$return .= '<a href="'.$href.'" title="'.StringUtil::specialchars($state ? $config['title'] : $titleDisabled).'" data-title="'.StringUtil::specialchars($config['title']).'" data-title-disabled="'.StringUtil::specialchars($titleDisabled).'" data-action="contao--scroll-offset#store" onclick="return AjaxRequest.toggleField(this,'.($icon == 'visible.svg' ? 'true' : 'false').')">'.Image::getHtml($state ? $icon : $_icon, $config['label'], 'data-icon="'.$icon.'" data-icon-disabled="'.$_icon.'" data-state="'.$state.'"').'</a> ';
				}
			} elseif ($href === null) {
				$return .= Image::getHtml($config['icon'], $config['label']).' ';
			} else {
				$return .= '<a href="'.$href.'" title="'.StringUtil::specialchars($config['title']).'"'.($isPopup ? ' onclick="Backend.openModalIframe({\'title\':\''.StringUtil::specialchars(str_replace("'", "\\'", $config['label'])).'\',\'url\':this.href});return false"' : '').$config['attributes'].'>'.Image::getHtml($config['icon'], $config['label']).'</a> ';
			}
		}

		return trim($return);
	}
}