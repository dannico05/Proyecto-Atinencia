type EligibilityResult = 'eligible' | 'not_eligible' | 'technical_note' | 'no_catalog';

interface EligibilityAnnouncementDetail {
    message: string;
    result?: EligibilityResult;
}

const ensureLiveRegion = (): HTMLElement => {
    const existing = document.getElementById('eligibility-live-region');
    if (existing) return existing;

    const region = document.createElement('div');
    region.id = 'eligibility-live-region';
    region.className = 'sr-only';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    document.body.appendChild(region);

    return region;
};

document.addEventListener('eligibility:announce', (event: Event) => {
    const detail = (event as CustomEvent<EligibilityAnnouncementDetail>).detail;
    if (!detail?.message) return;

    const region = ensureLiveRegion();
    region.textContent = '';
    window.requestAnimationFrame(() => {
        region.textContent = detail.message;
    });
});

export type { EligibilityAnnouncementDetail, EligibilityResult };
