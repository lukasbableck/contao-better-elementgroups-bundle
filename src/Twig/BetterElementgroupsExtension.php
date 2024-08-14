<?php
namespace Lukasbableck\ContaoBetterElementgroupsBundle\Twig;

use Contao\Controller;
use Contao\CoreBundle\Fragment\Reference\ContentElementReference;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BetterElementgroupsExtension extends AbstractExtension {
	public function __construct(private ScopeMatcher $scopeMatcher, private RequestStack $requestStack, private ContaoFramework $framework) {
	}

	public function getFilters(): array {
		return [
			new TwigFilter('ce_widget', [$this, 'renderWidget']),
		];
	}

	public function renderWidget($element): string {
		if ($this->scopeMatcher->isFrontendRequest($this->requestStack->getCurrentRequest())) {
			return $this->framework->getAdapter(Controller::class)->getContentElement($element);
		}
		dump($element);

		return '';
	}
}
