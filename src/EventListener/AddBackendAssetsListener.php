<?php
namespace Lukasbableck\ContaoBetterElementgroupsBundle\EventListener;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener]
class AddBackendAssetsListener {
	public function __construct(private readonly ScopeMatcher $scopeMatcher) {
	}

	public function __invoke(RequestEvent $event): void {
		if (!$this->scopeMatcher->isBackendMainRequest($event)) {
			return;
		}

		$GLOBALS['TL_CSS'][] = 'bundles/contaobetterelementgroups/backend.css';
	}
}
