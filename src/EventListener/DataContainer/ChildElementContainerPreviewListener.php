<?php
namespace Lukasbableck\ContaoBetterElementgroupsBundle\EventListener\DataContainer;

use Contao\Backend;
use Contao\ContentModel;
use Contao\CoreBundle\DataContainer\ClipboardManager;
use Contao\CoreBundle\DataContainer\DataContainerGlobalOperationsBuilder;
use Contao\CoreBundle\DataContainer\DataContainerOperation;
use Contao\CoreBundle\DataContainer\DataContainerOperationsBuilder;
use Contao\CoreBundle\EventListener\DataContainer\ContentElementViewListener;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Security\ContaoCorePermissions;
use Contao\CoreBundle\Security\DataContainer\CreateAction;
use Contao\DataContainer;
use Contao\DC_Table;
use Contao\Input;
use Contao\System;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ChildElementContainerPreviewListener extends ContentElementViewListener {
	public function __construct(
		#[Autowire('@contao.data_container.clipboard_manager')]
		private readonly ClipboardManager $clipboardManager,
		private readonly ContentElementViewListener $inner,
		#[Autowire('@contao.data_container.global_operations_builder')]
		private readonly DataContainerGlobalOperationsBuilder $globalOperationsBuilder,
		#[Autowire('@contao.data_container.operations_builder')]
		private readonly DataContainerOperationsBuilder $operationsBuilder,
		private readonly Environment $twig,
		private readonly Security $security,
		ContaoFramework $framework,
		TranslatorInterface $translator
	) {
		parent::__construct($framework, $translator);
	}

	public function generateLabel(array $row, string $label, DC_Table $dc): array|string {
		$label = $this->inner->generateLabel($row, $label, $dc);

		if ($dc->parentTable !== 'tl_theme') {
			$childRecords = $this->generateRecords(ContentModel::findByPid($row['id'], ['order' => 'sorting ASC'])?->fetchAll() ?? [], $dc);
			if (\count($childRecords) === 0) {
				return $label;
			}

			$label[1] = $this->twig->render('@Contao/backend/data_container/table/view/nested_grid_records.html.twig', [
				'records' => $childRecords,
				'table' => $dc->table,
				'is_sortable' => false,
				'pid' => $row['id'],
				'as_select' => false,
				'as_picker' => false,
				'display_grid' => true,
			]);
		}

		return $label;
	}

	private function generateRecords(array $rows, DataContainer $dataContainer): array {
		$blnHasSorting = ($GLOBALS['TL_DCA']['tl_content']['list']['sorting']['fields'][0] ?? null) == 'sorting';

		$arrClipboard = $this->clipboardManager->get('tl_content');
		$blnClipboard = $arrClipboard !== null;
		$blnMultiboard = $arrClipboard !== null && \is_array($arrClipboard['id'] ?? null);
		$canAddNew = !$blnClipboard
						&& !($GLOBALS['TL_DCA']['tl_content']['config']['closed'] ?? null)
						&& !($GLOBALS['TL_DCA']['tl_content']['config']['notCreatable'] ?? null)
						&& !($GLOBALS['TL_DCA']['tl_content']['config']['notEditable'] ?? null);

		$operations = $this->globalOperationsBuilder->initialize('tl_content');

		$intWrapLevel = 0;
		$records = [];
		$blnIndent = false;
		for ($i = 0; $i < \count($rows); ++$i) {
			$row = $rows[$i];
			$record = [
				'id' => $row['id'],
				'is_draft' => (string) ($row['tstamp'] ?? null) === '0',
			];

			if ($GLOBALS['TL_DCA']['tl_content']['list']['sorting']['renderAsGrid'] ?? false) {
				$blnWrapperStart = isset($row['type']) && \in_array($row['type'], $GLOBALS['TL_WRAPPERS']['start']);
				$blnWrapperSeparator = isset($row['type']) && \in_array($row['type'], $GLOBALS['TL_WRAPPERS']['separator']);
				$blnWrapperStop = isset($row['type']) && \in_array($row['type'], $GLOBALS['TL_WRAPPERS']['stop']);
				$blnIndentFirst = isset($rows[$i - 1]['type']) && \in_array($rows[$i - 1]['type'], $GLOBALS['TL_WRAPPERS']['start']);
				$blnIndentLast = isset($rows[$i + 1]['type']) && \in_array($rows[$i + 1]['type'], $GLOBALS['TL_WRAPPERS']['stop']);

				// Closing wrappers
				if ($blnWrapperStop && --$intWrapLevel < 1) {
					$blnIndent = false;
				}

				$record['display'] = [
					'wrapper_start' => $blnWrapperStart,
					'wrapper_separator' => $blnWrapperSeparator,
					'wrapper_stop' => $blnWrapperStop,
					'wrap_level' => $blnIndent ? $intWrapLevel : null,
					'indent_first' => $blnIndentFirst,
					'indent_last' => $blnIndentLast,
				];

				// Opening wrappers
				if ($blnWrapperStart && ++$intWrapLevel > 0) {
					$blnIndent = true;
				}
			}

			$record['class'] = $GLOBALS['TL_DCA']['tl_content']['list']['sorting']['child_record_class'] ?? '';

			if (Input::get('act') != 'select') {
				$recordOperations = $this->generateButtons($row, 'tl_content', $dataContainer, [], false, null, $models[$i - 1]['id'] ?? null, $models[$i + 1]['id'] ?? null);

				// Sortable table
				if ($blnHasSorting) {
					// Prevent circular references
					if ($blnClipboard && !$this->clipboardManager->canPasteAfterOrInto('tl_content', $row['id'])) {
						$recordOperations->addSeparator();
						$recordOperations->addPasteButton('pasteafter', 'tl_content', null);
					}

					// Copy/move multiple
					elseif ($blnMultiboard) {
						$recordOperations->addSeparator();
						$recordOperations->addPasteButton('pasteafter', 'tl_content', Backend::addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$row['id'].'&amp;ptable='.$row['ptable']));
					}

					// Paste buttons
					elseif ($blnClipboard) {
						$recordOperations->addSeparator();
						$recordOperations->addPasteButton('pasteafter', 'tl_content', Backend::addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$row['id'].'&amp;id='.$arrClipboard['id'].'&amp;ptable='.$row['ptable']));
					}

					// Create new button
					elseif ($canAddNew && $this->security->isGranted(ContaoCorePermissions::DC_PREFIX.'tl_content', new CreateAction('tl_content', $this->addDynamicPtable(['pid' => $row['pid'], 'sorting' => $row['sorting'] + 1], 'tl_content', $row['ptable'])))) {
						$recordOperations->addSeparator();
						$recordOperations->addNewButton($operations::CREATE_AFTER, 'tl_content', $row['id'], $row['pid']);
					}
				}

				$record['operations'] = $recordOperations;
			}

			$label = $dataContainer->generateRecordLabel($row);

			$record['label'] = \is_array($label) ? ($label[0] ?? '') : $label;
			$record['preview'] = \is_array($label) ? trim($label[1] ?? '') : '';
			$record['state'] = \is_array($label) ? ($label[2] ?? '') : '';

			$records[] = $record;
		}

		return $records;
	}

	protected function addDynamicPtable(array $data, string $table, string $ptable): array {
		if (($GLOBALS['TL_DCA'][$table]['config']['dynamicPtable'] ?? false) && !isset($data['ptable'])) {
			$data['ptable'] = $ptable;
		}

		return $data;
	}

	private function generateButtons($arrRow, $strTable, $dc, $arrRootIds = [], $blnCircularReference = false, $arrChildRecordIds = null, $strPrevious = null, $strNext = null) {
		return $this->operationsBuilder->initializeWithButtons(
			$strTable,
			$arrRow,
			$dc,
			static function (DataContainerOperation $config) use ($arrRow, $strTable, $arrRootIds, $arrChildRecordIds, $blnCircularReference, $strPrevious, $strNext, $dc): void {
				trigger_deprecation('contao/core-bundle', '5.5', 'Using a button_callback without DataContainerOperation object is deprecated and will no longer work in Contao 6.');

				if (\is_array($config['button_callback'] ?? null)) {
					$callback = System::importStatic($config['button_callback'][0]);
					$config->setHtml($callback->{$config['button_callback'][1]}($arrRow, $config['href'] ?? null, $config['label'], $config['title'], $config['icon'] ?? null, $config['attributes'], $strTable, $arrRootIds, $arrChildRecordIds, $blnCircularReference, $strPrevious, $strNext, $dc));
				} elseif (\is_callable($config['button_callback'] ?? null)) {
					$config->setHtml($config['button_callback']($arrRow, $config['href'] ?? null, $config['label'], $config['title'], $config['icon'] ?? null, $config['attributes'], $strTable, $arrRootIds, $arrChildRecordIds, $blnCircularReference, $strPrevious, $strNext, $dc));
				}
			}
		);
	}
}
