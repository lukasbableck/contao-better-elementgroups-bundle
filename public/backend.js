document.querySelectorAll("[data-disable-parent-limit-height]").forEach(el => {
	el.closest(".inside").removeAttribute("data-contao--limit-height-target");
});