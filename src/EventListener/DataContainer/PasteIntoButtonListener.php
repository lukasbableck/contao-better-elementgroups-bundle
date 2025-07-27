<?php
namespace Lukasbableck\ContaoBetterElementgroupsBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Contao\Image;
use Contao\StringUtil;
use Contao\System;

#[AsCallback(table: 'tl_content', target: 'list.operations.pasteinto.button')]
class PasteIntoButtonListener {
	public function __invoke(array $row, ?string $href, string $label, string $title, ?string $icon, string $attributes, string $table, array $rootRecordIds, ?array $childRecordIds, bool $circularReference, ?string $previous, ?string $next, DataContainer $dc): string {
		$return = '';

		if ($row['type'] === 'element_group' || $row['type'] === 'tabs') {
			$strTable = $dc->table;
			$objSession = System::getContainer()->get('request_stack')->getSession();

			$blnClipboard = false;
			$arrClipboard = $objSession->get('CLIPBOARD');
			$blnMultiboard = false;

			if (!empty($arrClipboard[$strTable])) {
				$blnClipboard = true;
				$arrClipboard = $arrClipboard[$strTable];

				if (\is_array($arrClipboard['id'] ?? null)) {
					$blnMultiboard = true;
				}
			} else {
				$arrClipboard = null;
			}

			$labelPasteInto = $GLOBALS['TL_LANG'][$strTable]['pasteinto'] ?? $GLOBALS['TL_LANG']['DCA']['pasteinto'];
			$imagePasteInto = Image::getHtml('pasteinto.svg', \sprintf($labelPasteInto[1], $row['id']));

			if (($blnClipboard && $arrClipboard['mode'] == 'cut' && $row['id'] == $arrClipboard['id']) || ($blnMultiboard && $arrClipboard['mode'] == 'cutAll' && \in_array($row['id'], $arrClipboard['id']))) {
				$return .= ' '.Image::getHtml('pasteinto--disabled.svg');
			} elseif ($blnMultiboard) {
				$return .= ' <a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid='.$row['id'].'&amp;ptable=tl_content').'" title="'.StringUtil::specialchars(\sprintf($labelPasteInto[1], $row['id'])).'" data-action="contao--scroll-offset#store">'.$imagePasteInto.'</a>';
			} elseif ($blnClipboard) {
				$return .= ' <a href="'.Backend::addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid='.$row['id'].'&amp;id='.$arrClipboard['id'].'&amp;ptable=tl_content').'" title="'.StringUtil::specialchars(\sprintf($labelPasteInto[1], $row['id'])).'" data-action="contao--scroll-offset#store">'.$imagePasteInto.'</a>';
			}
		}

		return $return;
	}
}
